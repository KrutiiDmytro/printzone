# Міграція CI/CD: GitLab CI → GitHub Actions

> Активний робочий план. Попередні плани (прод-інцидент 2026-08-05, OpenAPI, Export — Phase 7,
> Storage — MR !32, Notification та ін.) — у git-історії.

## Мета

Перевести збірку, тести й деплой продакшену з GitLab CI на GitHub Actions, зберігши
публічність репозиторію (посилання в CV) і не відкривши прод для коду з форків.

## Рішення (зафіксовані)

| Питання | Рішення |
|---|---|
| Раннер | **Self-hosted** GitHub Actions runner на тому ж дроплеті |
| Видимість репо | **Публічне** — потрібне клікабельне посилання в CV |
| Захист від форків | Джоби не запускаються на `pull_request` із форку + approval для зовнішніх |
| GitLab CI | Файл лишається, deploy-джоби переводяться в `when: manual` (шлях відкату) |
| Секрети | Скрипт GitLab API → `gh secret set`, значення не друкуються |

## ⚠️ Контекст, що змінює пріоритет

Навчання завершено, ментора немає. GitLab у Foxminded — **чужа закрита інсталяція**: доступ
можуть відкликати без попередження, і проєкт там усе одно ніхто ззовні не бачить. Єдине, що
звідти більше нізвідки не дістати чисто — **значення 22 CI-змінних**.

Тому **Phase 2 (секрети) виконується ПЕРШОЮ**, до раннера і workflow'ів. Решта фаз від
доступу до GitLab не залежить.

Запасний шлях, якщо доступ уже втрачено: ті самі значення живуть в оточенні контейнерів на
дроплеті — `docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' <container>`.

GitLab-репозиторій **не видаляти**: доки посилаються на `MR !32/!44/!47/!50/!51`, де записано,
*чому* прод влаштований саме так. Перевести в archived/read-only.

**Чому self-hosted, а не GitHub-hosted + SSH:** поточний деплой не використовує SSH — він
`rsync`-ає з робочої теки раннера в `/var/www/app` і піднімає `docker compose` локально,
а образи (`task-25/php:$SHA`) збираються теж локально й ніде не публікуються. Раннер і прод —
одна машина. GitHub-hosted раннер вимагав би Docker-реєстру + SSH-деплою, тобто іншої
архітектури, а не міграції.

## Мапінг GitLab → GitHub

| GitLab CI | GitHub Actions |
|---|---|
| `stages` + `needs` | окремі jobs + `needs` |
| `tags: [Веб-разработка]` | `runs-on: [self-hosted, printzone-prod]` |
| `$CI_PROJECT_DIR` | `$GITHUB_WORKSPACE` |
| `$CI_COMMIT_SHA` | `${{ github.sha }}` |
| `rules: $CI_COMMIT_BRANCH == "develop"` | `on.push.branches: [develop]` |
| `rules: merge_request_event` | `on.pull_request` + guard на форк |
| `environment: production` | GitHub Environment `production` |
| CI/CD Variables | Repository secrets (+ Environment secrets для прод) |
| один раннер = послідовність | `concurrency: group deploy-prod, cancel-in-progress: false` |

---

## Phase 0 — Розвідка на дроплеті (без змін)

- [x] Визначити користувача, від якого працює gitlab-runner, і власника `/var/www/app`
- [x] Перевірити наявність `rsync`, `docker`, `docker compose`, вільне місце під `_work`
- [x] Зафіксувати, чи є на хості інші проєкти (Task-28 архівний — не має заважати)

**Результат:** `/var/www/app` = `gitlab-runner:gitlab-runner` 775, **root-owned файлів немає**.
`ExecStart` містить `--user gitlab-runner` → джоби виконуються від нього, і він уже в групі
`docker` (988). rsync 3.2.7, compose v5.4.0, docker 29.7.2. Диск 16 ГБ вільно, RAM 1.9 ГБ
(available ~876 МБ). `api.github.com` → 200. 33 контейнери, жодного в `restarting`.

> ⚠️ Раннер GitHub **має працювати від того самого користувача**, що володіє `/var/www/app`
> (або тека переходить до нового). Інакше повторимо задокументований інцидент з `rsync`:
> «Operation not permitted» + «mkstemp Permission denied» (див. `docs/lesson.md`).

## Phase 1 — Self-hosted runner

- [x] Створити runner-токен (через UI `settings/actions/runners/new`)
- [x] Розгорнути раннер v2.336.0 у `/opt/actions-runner` з міткою `printzone-prod`
- [x] Встановити як systemd-сервіс: **`./svc.sh install gitlab-runner`** (аргумент = користувач!)
- [x] Перевірити: раннер `online`, мітки `self-hosted, Linux, X64, printzone-prod`
- [x] Smoke-workflow (`.github/workflows/smoke.yml`) — run 31736030992, **success**

**Доведено smoke-прогоном:** джоба виконується від `uid=999(gitlab-runner)` у групі `docker`,
запис у `/var/www/app` працює, docker/compose/rsync доступні, секрети долітають з правильними
довжинами (`POSTGRES_PASSWORD` 9, `JWT_PASSPHRASE` 64, `OAUTH_GITHUB_CLIENT_SECRET` 40),
прод не зачеплено — 33 контейнери як були.

> `smoke.yml` — тимчасовий, видалити після Phase 4.

## Phase 2 — Секрети (22 шт.) — ⚡ ВИКОНУЄТЬСЯ ПЕРШОЮ

- [x] Написати `scripts/migrate-ci-secrets.ps1` (GitLab API → `gh secret set`, без друку значень)
- [x] Створити GitLab PAT — вистачило scope `read_api` (не `api`)
- [x] Прогнати з `-DryRun`, потім без нього
- [x] Звірити **лише імена**: `gh secret list` → 21 секрет ✅
- [x] Відкликати GitLab PAT
- [x] Прибрати ключ `nQavPaDX…` з GitLab CI Variables (був схожий на вставлений токен)

**Підсумок:** з 25 змінних GitLab перенесено 21. Пропущено як невживані:
`APP_PASSWORD` (Gmail-бридж покинуто), `DATABASE_URL` / `DATABASE_REPLICA_URL`
(`compose.prod.yaml:41` збирає URL сам з `${POSTGRES_PASSWORD}`) і ключ-сміття.
`DELIVERY_PROVIDER` у GitLab і не було — дефолт `fake` зашитий у
`services/delivery-service/compose.prod.yaml:23`.
- [ ] Перевірити повноту за списком нижче

`ADMIN_EMAIL`, `APP_SECRET`, `AWS_ACCESS_KEY_ID`, `AWS_S3_BUCKET`, `AWS_SECRET_ACCESS_KEY`,
`CART_DB_PASSWORD`, `CATALOG_DB_PASSWORD`, `DELIVERY_DB_PASSWORD`, `DELIVERY_PROVIDER`,
`EXPORT_DB_PASSWORD`, `GITHUB_CLIENT_SECRET`, `GOOGLE_CLIENT_SECRET`, `JWT_PASSPHRASE`,
`MAILER_DSN`, `NOVA_POSHTA_API_KEY`, `ORDER_DB_PASSWORD`, `PAYMENT_DB_PASSWORD`,
`POSTGRES_PASSWORD`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`,
`USER_DB_PASSWORD`

> ⚠️ `GITHUB_` — зарезервований префікс у GitHub Actions: секрет `GITHUB_CLIENT_SECRET`
> створити не вийде. Перейменувати на `OAUTH_GITHUB_CLIENT_SECRET` і додати мапінг у workflow
> (`GITHUB_CLIENT_SECRET: ${{ secrets.OAUTH_GITHUB_CLIENT_SECRET }}`), щоб `compose.prod.yaml`
> не чіпати.

## Phase 3 — Workflow: validate + build + test ✅

- [x] `.github/workflows/ci.yml` — джоби `validate`, `monolith`, `microservices`
- [x] Guard на форки в кожній джобі
- [x] 9 `build:*-service` + 9 `test:*-service` → одна матриця на 9 елементів
- [x] Прогін на `ci/github-actions`: **run 31736754761 — усі 11 джоб success**

**Відступи від дослівного порту (свідомі):**
- три монолітні тест-джоби GitLab об'єднано в одну з єдиним `composer install`
  замість трьох — на 1 vCPU це втричі менше зайвої роботи. `if: !cancelled()`
  зберігає поведінку «падіння PHPStan не ховає результати тестів».
- перед `actions/checkout` додано `busybox chown` workspace: контейнери пишуть
  туди як root, а checkout чистить теку від імені раннера. У GitLab це лікували
  через `GIT_CLEAN_FLAGS` + `composer audit --locked`.
- `actions/checkout@v5` (v4 тягне депрекейтнутий Node 20).

## Phase 4 — Workflow: deploy (написано, на проді ще НЕ виконувалось)

- [x] `.github/workflows/deploy.yml` + `.github/actions/deploy-service/action.yml`
- [x] Тільки `on: push: branches: [develop]`, `concurrency: deploy-prod`, без cancel
- [x] Environment `production` з URL `https://e-commerce.it.com`
- [x] Збережено обхідні кроки: права `public/`, `--force-recreate nginx`,
      `--skip-if-exists` для JWT, виключення `config/jwt` з rsync
- [ ] **Бойовий прогін** — мерж у `develop`

**Граф джоб (повторює `needs` з GitLab, порядок несучий):**

```
independent (cart, payment, delivery, export, notification, storage)
     ↓
order  ──→  monolith  ──→  dependent (catalog, user)
```

Моноліт дропає таблиці замовлень і кошика, тож ці сервіси мають бути живі до
його міграцій. Падіння будь-кого з тиру 1 зупиняє ланцюг і DROP не стається —
та сама властивість, що була в GitLab.

Дев'ять `deploy:*-service` зведено до одного composite action із трьома формами:
stateless (notification, storage), stateful (cart/payment/delivery/export/order),
stateful + seed-if-empty (catalog, user).

⚠️ `GITHUB_CLIENT_SECRET` експортується всередині кроку, а не через `env:` —
Actions резервує префікс `GITHUB_` і для секретів, і для змінних оточення,
а `compose.prod.yaml` інтерполює саме це ім'я.

## Phase 5 — Вимкнути деплой у GitLab

- [ ] Усі 10 `deploy:*` → `when: manual` + `allow_failure: true`
- [ ] Комент у `.gitlab-ci.yml`, що канонічний деплой тепер у GitHub Actions

## Phase 6 — Перевірка та документація

- [ ] Реальний деплой через GitHub Actions, `curl` головної + `/api`
- [ ] Перевірити, що секрети долетіли: жодного `WARN ... not set. Defaulting to blank`
- [ ] Оновити `CLAUDE.md` (розділ про CI) і `docs/lesson.md`
- [ ] Записати підсумок у розділ Review нижче

---

## Ризики

| Ризик | Пом'якшення |
|---|---|
| Раннер від іншого користувача ламає `rsync` у `/var/www/app` | Phase 0 визначає власника; раннер ставимо від нього |
| Порожні секрети → `fe_sendauth: no password supplied` | Явна перевірка на `WARN ... Defaulting to blank` у Phase 6 |
| Два пайплайни одночасно пишуть у `/var/www/app` | Phase 5 виконати одразу після першого успішного деплою |
| Код із форку на проді | Guard на `pull_request` + approval для зовнішніх |
| Перший деплой зламає прод | GitLab-джоби лишаються як `manual` — швидкий відкат |

## Review (результати)

**Міграцію завершено 2026-08-14.** Прод розкочується з GitHub Actions.

Бойовий прогін — run `31787206401` (коміт `fc87d85`): **21 джоба, усі success**
(11 CI + 10 deploy). Перевірено після деплою:

| Перевірка | Результат |
|---|---|
| `https://e-commerce.it.com` | HTTP 200, 0.86 с |
| `/shop` | HTTP 200, 0.68 с |
| `/api` | HTTP 401 (очікувано — потрібен JWT) |
| Health-check усіх 9 сервісів + моноліту | `... healthy`, `Application is healthy` |
| Міграції | `[OK] Already at the latest version` скрізь |
| Сидування | `users має 2 рядків`, `products має 10` → пропущено ✅ |
| `Defaulting to blank` у логах | **жодного** — усі 21 секрет долетіли |

### Що виявилось по дорозі

1. **Два окремі workflow не гейтяться тестами.** Спершу deploy жив у власному
   `deploy.yml`, але в Actions немає `needs` між workflow'ами — прод котився б
   незалежно від падіння PHPStan чи тестів. У GitLab це було неможливо
   (`deploy` мав `needs: [test:unit, test:functional]`). Злито в один пайплайн.
2. **`cancel-in-progress` на `develop` небезпечний** — обрив прогону посеред
   міграцій лишив би прод у півстані. Вимкнено саме для цієї гілки.
3. **`busybox chown` перед `checkout` — несучий крок, не косметика.** Доведено
   падінням: smoke-джоба без нього впала з `EACCES: permission denied, unlink
   '.phpunit.cache/test-results'`, бо контейнери лишили `vendor/` під root.
4. **Префікс `GITHUB_` зарезервований** — і для секретів, і для env. Обійдено
   через `OAUTH_GITHUB_CLIENT_SECRET` + `export` усередині кроку.
5. **Вставка довгих рядків у SSH-сесію ламається** — термінал переносить хвіст
   на новий рядок і рве heredoc/URL. Команди для дроплета давати короткими.

### Лишилось

- [x] Відкликати GitLab PAT
- [x] Прибрати ключ `nQavPaDX…` з GitLab CI Variables
- [x] Видалити злиту гілку `ci/github-actions`
- [x] README очищено від Foxminded-івської рамки «Task 24/25/26»
- [x] Branch protection на `develop` — заборонено force-push і видалення,
      `enforce_admins: true`. Обов'язкові checks/рев'ю **свідомо не вмикали**:
      пуш у `develop` і є деплоєм, а required checks заблокували б сам пуш до
      того, як джоби на цьому коміті встигнуть пройти — замкнений цикл.
- [x] Actions → Fork PR → `all_external_contributors` (було `first_time_contributors`)
- [ ] Оновити 4 доки, що ще посилаються на GitLab-пайплайн (`README.md` уже ні):
      `docs/lesson.md`, `docs/ops-2026-08-05-incident-and-handoff.md`,
      `docs/microservices-architecture.md`, `CLAUDE.md`

**Міграцію можна вважати закритою.** Останній пункт — косметика тексту, на
роботу пайплайну не впливає.
