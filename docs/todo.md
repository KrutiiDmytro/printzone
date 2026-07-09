# Фаза 7 (крок 1) — Notification Service

> Активний робочий план. Попередні плани фаз — в git-історії та в `docs/microservices-architecture.md`.

## Мета

Виокремити **Notification Service** (`:8007`) — централізований stateless-споживач подій,
що надсилає email / SMS / push. Прибрати inline-розсилку листів з монолітного
`IntegrationEventHandler` (у коді прямо позначений як заглушка майбутнього Notification).

## Що вже є (заземлення)

- Моноліт `src/Messaging/Application/IntegrationEventHandler.php` — на `OrderPaid` шле
  Twig-лист `email/order_paid.html.twig`. **Це те, що сервіс забирає (cutover).**
- Delivery Service — свіжий шаблон greenfield-сервісу (compose/messenger/health/worker).
- `docs/event-catalog.md` — payload'и всіх подій; Notification — підписник на 9 з них.

## Ключові рішення

| Питання | Рішення |
|---|---|
| База даних | **Немає.** Сервіс stateless (як у роадмапі §4.7). Без outbox/relay. |
| Публікація подій | **Немає.** Тільки споживання. |
| Канал Email | Symfony Mailer + Twig; dev → Mailpit, prod → SES (той самий `MAILER_DSN`, що в моноліті). |
| Канали SMS / Push | **Log-only** у цій фазі (за dev-таблицею роадмапу). Абстракція є, провайдерів нема. |
| Транспорт | Вхідний `notification_events`, власна черга, прив'язана до потрібних routing-keys. |
| Спільний контракт | Копія `App\Messaging\Domain\IntegrationEvent` (FQCN як в інших сервісах) — декодує JSON. |
| Аутентифікація | Публічного API нема → без JWT-firewall; health-endpoint'и публічні. |
| MVP-набір подій | `OrderPaid`, `PaymentSucceeded`, `PaymentFailed`, `ShipmentCreated`, `ShipmentDelivered`, `UserRegistered`. Решта (`OrderCancelled`, `TrackingUpdated`, `Export*`) — прив'язка є, але шаблони/канали — наступний інкремент або разом з Export-фазою. |

## Кроки (кожен = окремий MR-розмір)

### Крок 1 — Scaffold + health  (гілка `feat/phase7-notification-service` від origin/develop) ✅
- [x] `services/notification-service/` каркас: composer.json (+lock), Dockerfile, bin/console,
      public/index.php, Kernel, config (framework, twig, routes, services, bundles)
- [x] compose.yaml — лише `notification-service` (:8007→80), **без db**, у мережі monolith
      (для SMTP-проби). Worker → перенесено в Крок 2 (транспорт ще не визначений).
- [x] `HealthController`: `/health/live` (200), `/health/ready` (SMTP TCP-проба, толерантна до `null://`).
      RabbitMQ-перевірку опущено (як delivery-service). Broker-check → Крок 2.
- [x] Спільний `IntegrationEvent` (копія FQCN) — для майбутнього consumer'а
- [x] `tests/Functional/HealthTest` — live 200, ready 200 (null smtp), ready 503 (недосяжний smtp), 404

### Крок 2 — Consumer + email-канал
- [ ] `messenger.yaml`: вхідний `notification_events`, binding_keys для MVP-набору;
      `when@test` → `in-memory://`
- [ ] `NotificationChannelInterface` + `EmailChannel` (Mailer+Twig), `SmsChannel`/`PushChannel` (log-only)
- [ ] `NotificationHandler` (`AsMessageHandler` на `IntegrationEvent`) → маршрутизує
      за `eventName` до білдера листа; невідома подія = no-op
- [ ] Twig-шаблони листів (перенести `order_paid`, додати receipt / shipment-created / delivered / welcome)
- [ ] `tests/Functional/*` — in-memory transport + асерти на надіслані листи (кожна MVP-подія)

### Крок 3 — Cutover моноліту + прод
- [ ] Прибрати email-гілку з монолітного `IntegrationEventHandler` (OrderPaid лист тепер у сервісі);
      узгодити messenger-binding моноліту, щоб не дублювати лист
- [ ] `compose.prod.yaml` + `.gitlab-ci.yml`: build/deploy `notification-service` + `notification-worker`,
      прод-env (`MAILER_DSN`/SES, `MESSENGER_EVENTS_DSN`, sender/`ADMIN_EMAIL`)
- [ ] Перевірити прод CI-змінні (ймовірно нових нема — SES creds уже є)

## Edge cases (rule 3)

- **At-least-once доставка** → дедуплікація за `eventId` (лог+skip повтору) або прийняти
  ідемпотентність email як «не критично» для MVP і лише логувати повтор.
- Порожній / невалідний `userEmail` у payload → skip + warning (як зараз у моноліті).
- Невідомий `eventName` на черзі → no-op (не падати, не ретраїти нескінченно).
- SMTP недоступний → Messenger retry (max_retries), потім failed-transport; health/ready → 503.
- Подвійна розсилка під час cutover (і моноліт, і сервіс слухають OrderPaid) → Крок 3 має
  вимкнути монолітний handler ДО ввімкнення сервісного на проді.

## Тест-кейси (rule 3)

- Health: live=200; ready=200 коли RabbitMQ+SMTP ок; ready=503 коли SMTP впав.
- Кожна MVP-подія → рівно один лист правильному отримувачу з правильним subject/шаблоном.
- `OrderPaid` без `userEmail` → жодного листа, warning.
- Невідома подія (`ProductDeleted`) на черзі → 0 листів, без винятку.
- SMS/Push подія → лог-запис, без винятку (провайдера нема).

## Огляд результатів

### Крок 1 (виконано)
- Каркас stateless-сервісу `services/notification-service/` (FrankenPHP, Symfony 7.4, :8007).
  Без БД/doctrine/lexik/security — публічного API немає, лише health.
- Повний рантайм-набір залежностей закладено одразу (messenger+amqp+mailer+twig+serializer),
  `composer.lock` згенеровано раз → кроки 2-3 лише додаватимуть код/конфіг.
- `/health/live`=200; `/health/ready` перевіряє SMTP TCP-пробою (толерантна до `null://`/`in-memory`).
- 4 функц. тести зелені (7 asserts). Живий smoke: `curl :8007/health/{live,ready}` → 200/200.

### Відхилення від плану (свідомі)
- **Worker перенесено в Крок 2.** `messenger:consume notification_events` крешив би без визначеного
  транспорту — тож у Кроці 1 лише web-сервіс. Транспорт+worker+хендлери йдуть разом у Кроці 2.
- **Ім'я SMTP-хоста = `mailer`** (не `mailpit`): у compose моноліту сервіс називається `mailer`
  (image `axllent/mailpit`, :1025). `MAILER_DSN=smtp://mailer:1025` у dev.
- **RabbitMQ не перевіряється в `/ready`** — за прецедентом delivery-service (тримаємо probe дешевою
  й self-contained). Реальна залежність від брокера з'явиться у worker'а (Крок 2).
