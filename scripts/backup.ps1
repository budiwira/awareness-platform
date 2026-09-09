param(
    [string]$ProjectRoot = "C:\Users\budii\awareness-lab",
    [string]$BackupRoot = "C:\backups\awareness",
    [int]$RetentionDays = 14
)

$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$backupDir = Join-Path $BackupRoot $timestamp
New-Item -ItemType Directory -Force -Path $backupDir | Out-Null
$logEntries = @("## Backup $timestamp")
$exitCode = 0

try {
    # --- 1. Locate pg_dump ---
    $pgDump = (Get-Command pg_dump -ErrorAction SilentlyContinue).Source
    if (-not $pgDump) {
        $cand = Get-ChildItem "C:\Program Files\PostgreSQL\*\bin\pg_dump.exe" -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($cand) { $pgDump = $cand.FullName }
    }
    if (-not $pgDump) { throw "pg_dump tidak ditemukan di PATH maupun C:\Program Files\PostgreSQL" }
    $logEntries += "- pg_dump: $pgDump (owner user, bypass RLS)"

    # --- 2. Parse .env dengan trim CRLF ---
    $envFile = Join-Path $ProjectRoot ".env"
    $lines = (Get-Content $envFile -Raw) -split "`r?`n" | ForEach-Object { $_.Trim() }
    $kv = @{}
    foreach ($l in $lines) {
        if ($l -match "^([A-Z0-9_]+)=(.*)$") { $kv[$Matches[1]] = $Matches[2] }
    }

    # --- 3. DB dump sebagai OWNER (bypass RLS secara sah) ---
    $dumpFile = Join-Path $backupDir ("db_" + $kv["DB_DATABASE"] + ".dump")
    $env:PGPASSWORD = $kv["DB_OWNER_PASSWORD"]
    $out = & $pgDump -h $kv["DB_HOST"] -p $kv["DB_PORT"] -U $kv["DB_OWNER_USERNAME"] `
        -Fc --no-owner --no-privileges $kv["DB_DATABASE"] -f $dumpFile 2>&1
    Remove-Item Env:\PGPASSWORD
    if ($LASTEXITCODE -ne 0) { throw "pg_dump exit $LASTEXITCODE : $($out -join ' | ')" }
    $logEntries += "- DB dump OK: $([math]::Round((Get-Item $dumpFile).Length/1KB,1)) KB"

    # --- 3b. Verifikasi integritas dump ---
    $pgRestore = Join-Path (Split-Path $pgDump) 'pg_restore.exe'
    $verify = & $pgRestore --list $dumpFile 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Verifikasi dump gagal: $($verify -join ' | ')" }
    $logEntries += '- Verifikasi pg_restore --list OK'

    # --- 4. .env arsip ---
    Copy-Item $envFile (Join-Path $backupDir ".env") -Force
    $logEntries += "- .env copied"

    # --- 5. Storage ZIP ---
    $storagePath = Join-Path $ProjectRoot "storage\app"
    if (Test-Path $storagePath) {
        $zipFile = Join-Path $backupDir "storage.zip"
        Compress-Archive -Path $storagePath -DestinationPath $zipFile -Force
        $logEntries += "- Storage ZIP: $([math]::Round((Get-Item $zipFile).Length/1KB,1)) KB"
    } else {
        $logEntries += "- Storage kosong (skip)"
    }
} catch {
    $logEntries += "- FAILED: $($_.Exception.Message)"
    $exitCode = 1
} finally {
    # --- 6. Retensi ---
    $cutoff = (Get-Date).AddDays(-$RetentionDays)
    $old = Get-ChildItem $BackupRoot -Directory -ErrorAction SilentlyContinue | Where-Object { $_.CreationTime -lt $cutoff }
    foreach ($d in $old) { Remove-Item $d.FullName -Recurse -Force }
    if ($old) { $logEntries += "- Deleted $($old.Count) old backups" }

    # --- 7. Log SELALU ditulis ---
    $logFile = Join-Path $ProjectRoot "docs\ops\BACKUP-LOG.md"
    if (-not (Test-Path $logFile)) { "# Backup Log`n`n" | Set-Content $logFile }
    Add-Content $logFile -Value (($logEntries -join "`n") + "`n")
    Write-Host ($logEntries -join "`n")
}

exit $exitCode