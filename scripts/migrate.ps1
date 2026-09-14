param([string]$ProjectRoot = "C:\Users\budii\awareness-lab")

$envFile = Join-Path $ProjectRoot ".env"
$content = Get-Content $envFile -Raw
$lines = $content -split "`r?`n" | ForEach-Object { $_.Trim() }
$kv = @{}
foreach ($l in $lines) { if ($l -match "^([A-Z0-9_]+)=(.*)$") { $kv[$Matches[1]] = $Matches[2] } }

Copy-Item $envFile "$envFile.migrate-bak" -Force
try {
    $swapped = $content -replace "DB_USERNAME=.*", "DB_USERNAME=$($kv['DB_OWNER_USERNAME'])"
    $swapped = $swapped -replace "DB_PASSWORD=.*", "DB_PASSWORD=$($kv['DB_OWNER_PASSWORD'])"
    Set-Content $envFile -Value $swapped -NoNewline
    php artisan migrate --force
} finally {
    Copy-Item "$envFile.migrate-bak" $envFile -Force
    Remove-Item "$envFile.migrate-bak" -Force
}