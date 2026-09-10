param(
    [string]$ProjectRoot = "C:\Users\budii\awareness-lab",
    [string]$BackupRoot = "C:\backups\awareness",
    [string]$TestDb = "awareness_restore_test"
)

$exitCode = 0
$tempDump = $null
try {
    Import-Module (Join-Path $ProjectRoot "scripts\modules\Crypto.psm1") -Force
    $pgBin = (Get-Command pg_dump -ErrorAction SilentlyContinue).Source | Split-Path
    if (-not $pgBin) {
        $cand = Get-ChildItem "C:\Program Files\PostgreSQL\*\bin\pg_dump.exe" -ErrorAction SilentlyContinue | Select-Object -First 1
        $pgBin = Split-Path $cand.FullName
    }
    $psql = Join-Path $pgBin 'psql.exe'
    $pgRestore = Join-Path $pgBin 'pg_restore.exe'

    $lines = (Get-Content (Join-Path $ProjectRoot ".env") -Raw) -split "`r?`n" | ForEach-Object { $_.Trim() }
    $kv = @{}
    foreach ($l in $lines) { if ($l -match "^([A-Z0-9_]+)=(.*)$") { $kv[$Matches[1]] = $Matches[2] } }
    $keyHex = $kv["BACKUP_ENCRYPTION_KEY"]
    if (-not $keyHex) { throw "BACKUP_ENCRYPTION_KEY tidak ada di .env" }
    $env:PGPASSWORD = $kv["DB_OWNER_PASSWORD"]
    $conn = @("-h", $kv["DB_HOST"], "-p", $kv["DB_PORT"], "-U", $kv["DB_OWNER_USERNAME"])

    function Invoke-Sql($db, $sql) {
        $r = & $psql @conn -d $db -t -A -c $sql 2>&1
        if ($LASTEXITCODE -ne 0) { throw "psql gagal di $db : $($r -join ' | ')" }
        return $r
    }

    # --- Dump terenkripsi terbaru ---
    $dumpEnc = Get-ChildItem $BackupRoot -Recurse -Filter *.dump.enc | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $dumpEnc) { throw "Tidak ada dump .enc di $BackupRoot" }
    Write-Host "Drill pakai dump terenkripsi: $($dumpEnc.FullName)"

    # --- Decrypt ke TEMP (bukan ke folder backup) ---
    $tempDump = Join-Path $env:TEMP ("drill_" + [guid]::NewGuid().ToString("N") + ".dump")
    Unprotect-Backup -InputFile $dumpEnc.FullName -OutputFile $tempDump -KeyHex $keyHex

    # --- Setup DB test + restore ---
    Invoke-Sql "postgres" "DROP DATABASE IF EXISTS $TestDb;" | Out-Null
    Invoke-Sql "postgres" "CREATE DATABASE $TestDb;" | Out-Null
    $r = & $pgRestore @conn -d $TestDb --no-owner --no-privileges $tempDump 2>&1
    if ($LASTEXITCODE -ne 0) { throw "pg_restore gagal: $($r -join ' | ')" }
    Write-Host "Restore selesai."

    # --- Bandingkan row counts ---
    $tables = Invoke-Sql $TestDb "SELECT relname FROM pg_stat_user_tables ORDER BY 1;"
    $mismatch = 0
    Write-Host ("{0,-30} {1,10} {2,10}  {3}" -f "TABEL", "ASLI", "RESTORE", "STATUS")
    foreach ($t in $tables) {
        if ([string]::IsNullOrWhiteSpace($t)) { continue }
        $cOrig = Invoke-Sql $kv["DB_DATABASE"] "SELECT count(*) FROM ""$t"";"
        $cTest = Invoke-Sql $TestDb "SELECT count(*) FROM ""$t"";"
        $ok = ($cOrig -eq $cTest)
        if (-not $ok) { $mismatch++ }
        Write-Host ("{0,-30} {1,10} {2,10}  {3}" -f $t, $cOrig, $cTest, $(if ($ok) { "PASS" } else { "FAIL" }))
    }
    if ($mismatch -gt 0) { throw "$mismatch tabel mismatch!" }
    Write-Host "RESTORE DRILL (ENCRYPTED): SEMUA TABEL COCOK"
} catch {
    Write-Host "DRILL GAGAL: $($_.Exception.Message)"
    $exitCode = 1
} finally {
    # --- Cleanup: temp dump + DB test (JANGAN sisakan plaintext) ---
    if ($tempDump -and (Test-Path $tempDump)) { Remove-Item $tempDump -Force; Write-Host "Temp dump dihapus." }
    try {
        if ($env:PGPASSWORD) { Invoke-Sql "postgres" "DROP DATABASE IF EXISTS $TestDb;" | Out-Null; Write-Host "DB test dihapus." }
    } catch { Write-Host "Cleanup DB gagal: $($_.Exception.Message)" }
    Remove-Item Env:\PGPASSWORD -ErrorAction SilentlyContinue
}
exit $exitCode