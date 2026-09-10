param(
    [string]$ProjectRoot = "C:\Users\budii\awareness-lab",
    [string]$BackupDir = "",
    [string]$OutDir = ""
)

Import-Module (Join-Path $ProjectRoot "scripts\modules\Crypto.psm1") -Force

# Key dari .env
$lines = (Get-Content (Join-Path $ProjectRoot ".env") -Raw) -split "`r?`n" | ForEach-Object { $_.Trim() }
$kv = @{}
foreach ($l in $lines) { if ($l -match "^([A-Z0-9_]+)=(.*)$") { $kv[$Matches[1]] = $Matches[2] } }
$keyHex = $kv["BACKUP_ENCRYPTION_KEY"]
if (-not $keyHex) { throw "BACKUP_ENCRYPTION_KEY tidak ada di .env" }

# Backup dir: default = terbaru
if (-not $BackupDir) {
    $BackupDir = (Get-ChildItem "C:\backups\awareness" -Directory | Sort-Object Name -Descending | Select-Object -First 1).FullName
}
if (-not $OutDir) {
    $OutDir = Join-Path $BackupDir "decrypted"
}
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null

$files = Get-ChildItem $BackupDir -Filter "*.enc" -File
if (-not $files) { throw "Tidak ada file .enc di $BackupDir" }

foreach ($f in $files) {
    $outName = $f.Name -replace "\.enc$", ""
    Unprotect-Backup -InputFile $f.FullName -OutputFile (Join-Path $OutDir $outName) -KeyHex $keyHex
}

Write-Host ""
Write-Host "? Decrypted ke: $OutDir"
Get-ChildItem $OutDir | Select-Object Name, Length