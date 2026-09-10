param(
    [string]$ProjectRoot = "C:\Users\budii\awareness-lab",
    [string]$BackupRoot = "C:\backups\awareness",
    [int]$RetentionDays = 14
)

$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$backupDir = Join-Path $BackupRoot $timestamp
New-Item -ItemType Directory -Force -Path $backupDir | Out-Null
$logEntries = @("## Backup $timestamp (encrypted)")
$exitCode = 0

try {
    Import-Module (Join-Path $ProjectRoot "scripts\modules\Crypto.psm1") -Force
    Import-Module (Join-Path $ProjectRoot "scripts\modules\Alert.psm1") -Force

    # --- 1. Locate pg_dump ---
    $pgDump = (Get-Command pg_dump -ErrorAction SilentlyContinue).Source
    if (-not $pgDump) {
        $cand = Get-ChildItem "C:\Program Files\PostgreSQL\*\bin\pg_dump.exe" -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($cand) { $pgDump = $cand.FullName }
    }
    if (-not $pgDump) { throw "pg_dump tidak ditemukan" }

    # --- 2. Parse .env ---
    $envFile = Join-Path $ProjectRoot ".env"
    $lines = (Get-Content $envFile -Raw) -split "`r?`n" | ForEach-Object { $_.Trim() }
    $kv = @{}
    foreach ($l in $lines) { if ($l -match "^([A-Z0-9_]+)=(.*)$") { $kv[$Matches[1]] = $Matches[2] } }

    $keyHex = $kv["BACKUP_ENCRYPTION_KEY"]
    if (-not $keyHex) { throw "BACKUP_ENCRYPTION_KEY tidak ada di .env (fail closed)" }

    # --- 3. DB dump (plaintext sementara) ---
    $dumpPlain = Join-Path $backupDir ("db_" + $kv["DB_DATABASE"] + ".dump")
    $backupUser = if ($kv["BACKUP_DB_USERNAME"]) { $kv["BACKUP_DB_USERNAME"] } else { $kv["DB_OWNER_USERNAME"] }
    $backupPass = if ($kv["BACKUP_DB_PASSWORD"]) { $kv["BACKUP_DB_PASSWORD"] } else { $kv["DB_OWNER_PASSWORD"] }
    $env:PGPASSWORD = $backupPass
    $out = & $pgDump -h $kv["DB_HOST"] -p $kv["DB_PORT"] -U $backupUser `
        -Fc --no-owner --no-privileges $kv["DB_DATABASE"] -f $dumpPlain 2>&1
    Remove-Item Env:\PGPASSWORD
    if ($LASTEXITCODE -ne 0) { throw "pg_dump exit $LASTEXITCODE : $($out -join ' | ')" }

    # Verifikasi SEBELUM encrypt
    $pgRestore = Join-Path (Split-Path $pgDump) 'pg_restore.exe'
    $verify = & $pgRestore --list $dumpPlain 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Verifikasi dump gagal: $($verify -join ' | ')" }

    # --- 4. .env copy (plaintext sementara) ---
    $envPlain = Join-Path $backupDir ".env"
    Copy-Item $envFile $envPlain -Force

    # --- 5. Storage ZIP (plaintext sementara) ---
    $zipPlain = $null
    $storagePath = Join-Path $ProjectRoot "storage\app"
    if (Test-Path $storagePath) {
        $zipPlain = Join-Path $backupDir "storage.zip"
        Compress-Archive -Path $storagePath -DestinationPath $zipPlain -Force
    }

    # --- 6. ENCRYPT semua, hapus plaintext ---
    Protect-Backup -InputFile $dumpPlain -OutputFile "$dumpPlain.enc" -KeyHex $keyHex
    Remove-Item $dumpPlain -Force
    $logEntries += "- DB dump encrypted: $([math]::Round((Get-Item "$dumpPlain.enc").Length/1KB,1)) KB"

    Protect-Backup -InputFile $envPlain -OutputFile "$envPlain.enc" -KeyHex $keyHex
    Remove-Item $envPlain -Force
    $logEntries += "- .env encrypted"

    if ($zipPlain) {
        Protect-Backup -InputFile $zipPlain -OutputFile "$zipPlain.enc" -KeyHex $keyHex
        Remove-Item $zipPlain -Force
        $logEntries += "- Storage ZIP encrypted"
    }
} catch {
    $logEntries += "- FAILED: $($_.Exception.Message)"
    $exitCode = 1
} finally {
    # --- 7. Alert ---
    if ($exitCode -eq 0) {
        Write-BackupEvent -Status Success -Message "Backup selesai: $($logEntries -join ' | ')" -ExitCode 0
    } else {
        Write-BackupEvent -Status Failure -Message "Backup GAGAL: $($logEntries -join ' | ')" -ExitCode 1
    }

    # --- 8. Retensi ---
    $cutoff = (Get-Date).AddDays(-$RetentionDays)
    $old = Get-ChildItem $BackupRoot -Directory -ErrorAction SilentlyContinue | Where-Object { $_.CreationTime -lt $cutoff }
    foreach ($d in $old) { Remove-Item $d.FullName -Recurse -Force }
    if ($old) { $logEntries += "- Deleted $($old.Count) old backups" }

    # --- 9. Log ---
    $logFile = Join-Path $ProjectRoot "docs\ops\BACKUP-LOG.md"
    if (-not (Test-Path $logFile)) { "# Backup Log`n`n" | Set-Content $logFile }
    Add-Content $logFile -Value (($logEntries -join "`n") + "`n")
    Write-Host ($logEntries -join "`n")
}

exit $exitCode