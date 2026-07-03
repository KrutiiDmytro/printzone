# Lessons

## CI / verification

- **Never trust `git show <ref>:<path> | grep` on this Windows/Git-Bash setup.**
  When the ref contains a `/` (e.g. `origin/develop`, `feat/order-service`) and/or
  the path starts with a dot (`.env`, `.gitlab-ci.yml`), MSYS path conversion
  silently mangles the argument and `git show` returns **empty** — grep then yields
  a false negative. This caused a wrong "ORDER_SERVICE_URL has no default" finding
  (it was committed at `.env:22` all along).
  - Use instead: `git grep -n <pattern> <ref> -- <path>` (no colon-path), or
    `git diff <ref> -- <path>`, or prefix with `MSYS_NO_PATHCONV=1`.
  - Same class of bug noted earlier for `git show feat/...:.gitlab-ci.yml`. If a
    `git show <ref>:dotfile` returns nothing, assume mangling, not absence.

- Before drawing a conclusion from "X is absent", confirm the *command itself*
  produced output. An empty result can mean "tool failed", not "fact is false".

## Incident triage

- **Confirm the environment (prod vs local) BEFORE acting on a "prod" report.**
  On a "products gone from prod" incident I dived into the local Docker stack and
  started fixing it (brought up order-service, fixed an N+1) — but that stack was
  local dev (Windows tree, `APP_ENV=dev`, service URLs = `host.docker.internal`,
  php bind-mounts `.:/var/www/html` with no named volumes → 9p stat-storm made
  `GET /` take 27s, a *local-only* artifact). The real prod cause was unrelated:
  network-name mismatch (`app_default` vs `task-25_default`) already fixed in commit
  `d88e283`, pending merge+deploy. Tells that a stack is local-not-prod: `dev.log`
  actively written, `TraceableAuthenticator` (profiler on), empty OS-env for
  `*_SERVICE_URL` (means running base compose, not `compose.prod`).
- A single root cause can produce two symptoms. Here the network mismatch caused
  BOTH "products disappeared" (catalog unreachable → `CatalogClient` returns `[]`)
  AND "payment pending" (order-service unreachable). Don't assume two reports = two bugs.

## Where commands run (prod vs local)

- **Always label WHERE a command block must run, and never hand over a multi-command
  git block when a prod SSH session may be open.** I gave a `git stash -u && git checkout
  develop && git branch -D … && git stash pop && git commit` block intended for the LOCAL
  Windows repo; the user pasted it into an open prod shell (`/var/www/app` on the droplet).
  It stashed prod's working-tree drift, switched branches, and the `stash pop` conflicted
  (untracked templates already existed + "deleted by them" entity files). Recovery was fine
  only because `stash pop` keeps the stash on conflict — nothing was lost.
  - Prefix any runnable block with an explicit target, e.g. `# LOCAL (Windows)` or
    `# PROD host /var/www/app`, and for destructive git on an unknown shell, give ONE
    observable step at a time and wait for output.
- **On this prod host the `/var/www/app` git checkout is a non-authoritative bystander.**
  Deploy is CI rsync (shell-runner on the prod host) writing files on top, and php runs from
  a pre-baked CI image — so the git branch/working-tree there does NOT affect running
  containers, and the next deploy rsyncs over it anyway. The one file that IS live from that
  tree is the nginx bind-mount `docker/nginx/default.conf`. So: git surgery there is low-stakes
  for runtime, but keep `default.conf` current (checking out an old branch could stale it).
- The prod checkout sat on legacy branch `main` (12 commits ahead of `origin/main`) with a
  working-tree drift = just the delta between stale `main` and the rsync'd current files
  (`.env` defaults, some templates, `verify_checkout.mjs`) — **no secrets** (those live in the
  rsync-excluded, gitignored `.env.local`). Safe to drop. Left it clean on `develop @ 9024a26`
  so the host tree mirrors the deployed tip (keeps the "host grep vs `nginx -T` grep" tell valid).
