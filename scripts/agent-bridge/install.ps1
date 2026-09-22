param(
    [switch]$Force
)

$ErrorActionPreference = "Stop"

$BridgeDir = $PSScriptRoot
$ConfigExample = Join-Path $BridgeDir "config.example.json"
$ConfigLocal = Join-Path $BridgeDir "config.local.json"

$RepoRoot = Resolve-Path (Join-Path $BridgeDir "..\..")
$AgentSource = Join-Path $RepoRoot ".opencode\agents\bridge-worker.md"
$GlobalAgentDir = Join-Path $HOME ".config\opencode\agents"
$GlobalAgentTarget = Join-Path $GlobalAgentDir "bridge-worker.md"

Write-Host "Agent Bridge v1 preflight"

$null = Get-Command git -ErrorAction Stop
Write-Host "[OK] git"

$null = Get-Command gh -ErrorAction Stop
Write-Host "[OK] gh"

& gh auth status
if ($LASTEXITCODE -ne 0) {
    throw "GitHub CLI is not authenticated. Run: gh auth login"
}
Write-Host "[OK] gh authenticated"

if (-not (Test-Path $ConfigExample)) {
    throw "Missing config template: $ConfigExample"
}

if ((-not (Test-Path $ConfigLocal)) -or $Force) {
    Copy-Item $ConfigExample $ConfigLocal -Force
    Write-Host "[OK] Created local config: $ConfigLocal"
}
else {
    Write-Host "[OK] Local config already exists: $ConfigLocal"
}

if (-not (Test-Path $AgentSource)) {
    throw "Missing bridge worker definition: $AgentSource"
}

New-Item -ItemType Directory -Force -Path $GlobalAgentDir | Out-Null
Copy-Item $AgentSource $GlobalAgentTarget -Force
Write-Host "[OK] Installed global OpenCode agent: $GlobalAgentTarget"

$config = Get-Content -Raw $ConfigLocal | ConvertFrom-Json
$templateConfig = Get-Content -Raw $ConfigExample | ConvertFrom-Json
$configChanged = $false

$requiredConfigKeys = @(
    "trustedTaskAuthors",
    "maxTaskMinutesCap",
    "watchdogPollSeconds",
    "watchdogNoProgressMinutes",
    "watchdogRepeatThreshold"
)

foreach ($key in $requiredConfigKeys) {
    if (-not $config.PSObject.Properties.Name.Contains($key)) {
        $config | Add-Member -NotePropertyName $key -NotePropertyValue $templateConfig.$key
        $configChanged = $true
        Write-Host "[OK] Added $key to local config"
    }
}

if ([int]$config.maxTaskMinutes -eq 90) {
    $config.maxTaskMinutes = [int]$templateConfig.maxTaskMinutes
    $configChanged = $true
    Write-Host "[OK] Updated maxTaskMinutes to watchdog default: $($config.maxTaskMinutes)"
}

if ($configChanged) {
    $json = $config | ConvertTo-Json -Depth 10
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($ConfigLocal, $json + [Environment]::NewLine, $utf8NoBom)
}

if (-not (Test-Path $config.openCodeCli)) {
    throw "OpenCode CLI not found at configured path: $($config.openCodeCli)"
}
Write-Host "[OK] OpenCode CLI"

& $config.openCodeCli models | Select-String -Pattern "mimo-v2.5-free|MiMo-V2.5 Free" | Out-Host
if ($LASTEXITCODE -ne 0) {
    throw "Unable to list OpenCode models."
}

Write-Host ""
Write-Host "Review config.local.json before running the bridge."
Write-Host "Then test once with:"
Write-Host "  powershell -ExecutionPolicy Bypass -File .\scripts\agent-bridge\bridge.ps1 -Once"
