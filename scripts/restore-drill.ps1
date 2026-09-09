param(
    [string]$ProjectRoot = "C:\Users\budii\awareness-lab",
    [string]$BackupRoot = "C:\backups\awareness",
    [string]$TestDb = "awareness_restore_test"
)

$exitCode = 0
try {
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
    $env:PGPASSWORD = $kv["DB_OWNER_PASSWORD"]
    $conn = @("-h", $kv["DB_HOST"], "-p", $kv["DB_PORT"], "-U", $kv["DB_OWNER_USERNAME"])

    function Invoke-Sql($db, $sql) {
        $r = & $psql @conn -d $db -t -A -c $sql 2>&1
        if ($LASTEXITCODE -ne 0) { throw "psql gagal di $db : $($r -join ' | ')" }
        return $r
    }

    $dump = Get-ChildItem $BackupRoot -Recurse -Filter *.dump | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $dump) { throw "Tidak ada dump di $BackupRoot" }
    Write-Host "Drill pakai dump: $($dump.FullName)"

    Invoke-Sql "postgres" "DROP DATABASE IF EXISTS $TestDb;" | Out-Null
    Invoke-Sql "postgres" "CREATE DATABASE $TestDb;" | Out-Null
    Write-Host "DB test dibuat: $TestDb"

    $r = & $pgRestore @conn -d $TestDb --no-owner --no-privileges $dump.FullName 2>&1
    if ($LASTEXITCODE -ne 0) { throw "pg_restore gagal: $($r -join ' | ')" }
    Write-Host "Restore selesai."

    $tables = Invoke-Sql $TestDb "SELECT relname FROM pg_stat_user_tables ORDER BY 1;"
    $mismatch = 0
    Write-Host ""
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
    Write-Host ""
    Write-Host "RESTORE DRILL: SEMUA TABEL COCOK"
} catch {
    Write-Host "DRILL GAGAL: $($_.Exception.Message)"
    $exitCode = 1
} finally {
    try {
        if ($env:PGPASSWORD) { Invoke-Sql "postgres" "DROP DATABASE IF EXISTS $TestDb;" | Out-Null; Write-Host "DB test dihapus." }
    } catch { Write-Host "Cleanup gagal: $($_.Exception.Message)" }
    Remove-Item Env:\PGPASSWORD -ErrorAction SilentlyContinue
}
exit $exitCode