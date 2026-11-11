<#
Throwaway MariaDB for PHPUnit, without Docker or an installer. Run from site/:  powershell -File deploy/local/test-db.ps1
Downloads the portable MariaDB zip once into ..\..\tools-mariadb (outside the repo), starts it on port 3307 and creates
the test database and user. The database is emptied by the test suite: never point the suite at a real site.
Then set the variables the suite reads (they are printed at the end) and run: composer test
#>
$ErrorActionPreference = "Stop"
$version = "11.4.8"
$tools = Join-Path (Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $PSScriptRoot))) "tools-mariadb"
$home_ = Join-Path $tools "mariadb-$version-winx64"
if (-not (Test-Path $home_)) {
  New-Item -ItemType Directory -Force $tools | Out-Null
  $zip = Join-Path $tools "m.zip"
  Invoke-WebRequest "https://archive.mariadb.org/mariadb-$version/winx64-packages/mariadb-$version-winx64.zip" -OutFile $zip
  Expand-Archive $zip $tools -Force
}
$data = Join-Path $tools "data"
if (-not (Test-Path $data)) { & (Join-Path $home_ "bin\mariadb-install-db.exe") --datadir=$data --port=3307 | Out-Null }
if (-not (Get-Process mariadbd -ErrorAction SilentlyContinue)) {
  Start-Process (Join-Path $home_ "bin\mariadbd.exe") -ArgumentList "--datadir=$data", "--port=3307", "--bind-address=127.0.0.1" -WindowStyle Hidden
  Start-Sleep -Seconds 8
}
$pw = [Guid]::NewGuid().ToString("N")
& (Join-Path $home_ "bin\mariadb.exe") -uroot -P3307 -h127.0.0.1 -e "CREATE DATABASE IF NOT EXISTS wiw_test; CREATE USER IF NOT EXISTS 'wiw'@'127.0.0.1' IDENTIFIED BY '$pw'; ALTER USER 'wiw'@'127.0.0.1' IDENTIFIED BY '$pw'; GRANT ALL ON wiw_test.* TO 'wiw'@'127.0.0.1';"
Write-Host "Set these in your shell, then run composer test:"
Write-Host "`$env:WIW_TEST_DB_NAME='wiw_test'; `$env:WIW_TEST_DB_USER='wiw'; `$env:WIW_TEST_DB_PASSWORD='$pw'; `$env:WIW_TEST_DB_HOST='127.0.0.1:3307'"
