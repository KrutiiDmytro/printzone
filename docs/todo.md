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

- [ ] Визначити користувача, від якого працює gitlab-runner, і власника `/var/www/app`
- [ ] Перевірити наявність `rsync`, `docker`, `docker compose`, вільне місце під `_work`
- [ ] Зафіксувати, чи є на хості інші проєкти (Task-28 архівний — не має заважати)

> ⚠️ Раннер GitHub **має працювати від того самого користувача**, що володіє `/var/www/app`
> (або тека переходить до нового). Інакше повторимо задокументований інцидент з `rsync`:
> «Operation not permitted» + «mkstemp Permission denied» (див. `docs/lesson.md`).

## Phase 1 — Self-hosted runner

- [ ] Створити runner-токен: `gh api -X POST repos/KrutiiDmytro/printzone/actions/runners/registration-token`
- [ ] Розгорнути раннер у `/opt/actions-runner` з міткою `printzone-prod`
- [ ] Встановити як systemd-сервіс (`svc.sh install <user>` + `svc.sh start`)
- [ ] Перевірити: раннер `online` у Settings → Actions → Runners
- [ ] Smoke-workflow (`hello.yml`): `docker version`, `rsync --version`, `id`, права на `/var/www/app`

## Phase 2 — Секрети (22 шт.) — ⚡ ВИКОНУЄТЬСЯ ПЕРШОЮ

- [x] Написати `scripts/migrate-ci-secrets.ps1` (GitLab API → `gh secret set`, без друку значень)
- [x] Створити GitLab PAT — вистачило scope `read_api` (не `api`)
- [x] Прогнати з `-DryRun`, потім без нього
- [x] Звірити **лише імена**: `gh secret list` → 21 секрет ✅
- [ ] Відкликати GitLab PAT
- [ ] З'ясувати, що за ключ `nQavPaDX…` лежить у GitLab CI Variables (схоже на вставлений токен)

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

## Phase 3 — Workflow: validate + build + test

- [ ] `.github/workflows/ci.yml`: `lint:php`, `audit:composer`, `cs:fixer`, `static:analysis`,
      `build:image`, `test:unit`, `test:functional`
- [ ] Guard на форки в кожній джобі
- [ ] Перенести 9 `build:*-service` + 9 `test:*-service` (матрицею, а не копіпастом)
- [ ] Прогнати на тестовій гілці через PR, порівняти результат із GitLab-пайплайном

## Phase 4 — Workflow: deploy

- [ ] `.github/workflows/deploy.yml`: моноліт + 9 сервісів, `needs` на тести
- [ ] Тільки `on: push: branches: [develop]`, `concurrency` проти паралельних деплоїв
- [ ] Environment `production` з URL `https://e-commerce.it.com`
- [ ] Зберегти всі задокументовані обхідні кроки: скидання прав `public/`, `--force-recreate nginx`,
      `--skip-if-exists` для JWT-ключів, виключення `config/jwt` з rsync

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

_Заповнюється після Phase 6._
