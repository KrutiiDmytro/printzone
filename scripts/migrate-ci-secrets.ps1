<#
.SYNOPSIS
    Переносить CI/CD-змінні з GitLab у GitHub Actions secrets.

.DESCRIPTION
    Читає змінні проєкту через GitLab API і записує їх у GitHub через `gh secret set`.
    Значення ніде не друкуються — у консоль іде лише ім'я та статус.

    Запускати ОДИН раз, поки живий доступ до GitLab.

.PARAMETER GitLabToken
    Personal Access Token з правом `api` та роллю Maintainer/Owner на проєкті.
    Створити: https://git.foxminded.ua/-/user_settings/personal_access_tokens

.PARAMETER DryRun
    Показати, що буде зроблено, нічого не записуючи.

.EXAMPLE
    .\scripts\migrate-ci-secrets.ps1 -GitLabToken 'glpat-xxx' -DryRun
    .\scripts\migrate-ci-secrets.ps1 -GitLabToken 'glpat-xxx'
#>
param(
    [Parameter(Mandatory = $true)][string]$GitLabToken,
    [string]$GitLabHost = 'git.foxminded.ua',
    [string]$GitLabProject = 'foxmidedteam/printzone',
    [string]$GitHubRepo = 'KrutiiDmytro/printzone',
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

# GITHUB_ — зарезервований префікс в Actions, секрет з таким іменем створити не можна.
# Перейменовуємо, а в workflow підставимо назад під старим іменем, щоб не чіпати compose.prod.yaml.
$rename = @{ 'GITHUB_CLIENT_SECRET' = 'OAUTH_GITHUB_CLIENT_SECRET' }

# Не переносимо: жодна з цих змінних не використовується ані в compose.prod.yaml,
# ані в .gitlab-ci.yml — перевірено grep'ом 2026-08-13.
#   APP_PASSWORD          — Gmail App Password від покинутого SMTP-бриджа (прод на SES)
#   DATABASE_URL          — compose.prod.yaml сам збирає URL з ${POSTGRES_PASSWORD}
#   DATABASE_REPLICA_URL  — те саме
# Ключі, що виглядають як випадково вставлений токен (довгий рядок без підкреслень),
# відсіюються окремою евристикою нижче.
$skip = @('APP_PASSWORD', 'DATABASE_URL', 'DATABASE_REPLICA_URL')

$gh = Get-Command gh -ErrorAction SilentlyContinue
if (-not $gh) { $gh = "$env:ProgramFiles\GitHub CLI\gh.exe" } else { $gh = $gh.Source }
if (-not (Test-Path $gh)) { throw "gh CLI не знайдено" }

$proj = [uri]::EscapeDataString($GitLabProject)
$uri  = "https://$GitLabHost/api/v4/projects/$proj/variables?per_page=100"

Write-Host "Читаю змінні з $GitLabProject ..." -ForegroundColor Cyan
try {
    $vars = Invoke-RestMethod -Uri $uri -Headers @{ 'PRIVATE-TOKEN' = $GitLabToken }
} catch {
    throw "GitLab API відмовив: $($_.Exception.Message). Потрібна роль Maintainer/Owner і scope 'api'."
}

if (-not $vars) { throw "Змінних не повернуто — перевір права на проєкт." }

# Один ключ може мати кілька записів з різним environment_scope. Беремо '*', інакше перший.
$grouped = $vars | Group-Object key
Write-Host "Знайдено ключів: $($grouped.Count)`n" -ForegroundColor Cyan

$ok = 0; $skipped = 0; $failed = @()

foreach ($g in $grouped | Sort-Object Name) {
    $src = ($g.Group | Where-Object { $_.environment_scope -eq '*' } | Select-Object -First 1)
    if (-not $src) { $src = $g.Group[0] }

    $name  = $g.Name
    $value = $src.value
    $target = if ($rename.ContainsKey($name)) { $rename[$name] } else { $name }

    if ([string]::IsNullOrWhiteSpace($value)) {
        Write-Host ("  {0,-28} ПРОПУЩЕНО (порожнє значення)" -f $name) -ForegroundColor DarkYellow
        $skipped++
        continue
    }

    if ($skip -contains $name) {
        Write-Host ("  {0,-28} ПРОПУЩЕНО (не використовується)" -f $name) -ForegroundColor DarkYellow
        $skipped++
        continue
    }

    # Ім'я змінної оточення — ВЕЛИКІ літери й підкреслення. Усе інше (довгий
    # змішаний регістр без '_') — майже напевно токен, вставлений у поле "Key".
    if ($name -cnotmatch '^[A-Z][A-Z0-9_]*$') {
        Write-Host ("  {0,-28} ПРОПУЩЕНО (не схоже на ім'я змінної)" -f $name) -ForegroundColor DarkYellow
        $skipped++
        continue
    }

    $note = if ($target -ne $name) { " -> $target (перейменовано)" } else { "" }

    if ($DryRun) {
        Write-Host ("  {0,-28} буде записано ({1} симв.){2}" -f $name, $value.Length, $note) -ForegroundColor DarkGray
        $ok++
        continue
    }

    & $gh secret set $target --repo $GitHubRepo --body $value 2>&1 | Out-Null
    if ($LASTEXITCODE -eq 0) {
        Write-Host ("  {0,-28} OK{1}" -f $name, $note) -ForegroundColor Green
        $ok++
    } else {
        Write-Host ("  {0,-28} ПОМИЛКА" -f $name) -ForegroundColor Red
        $failed += $name
    }
}

Write-Host "`nЗаписано: $ok   Пропущено: $skipped   Помилок: $($failed.Count)" -ForegroundColor Cyan
if ($failed) { Write-Host "Не вдалось: $($failed -join ', ')" -ForegroundColor Red }
if (-not $DryRun) {
    Write-Host "`nПеревірка (лише імена):" -ForegroundColor Cyan
    & $gh secret list --repo $GitHubRepo
}
