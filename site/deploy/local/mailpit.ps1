<#
Local mail catcher (Mailpit, no Docker). Run from site/:  powershell -File deploy/local/mailpit.ps1
Installs Mailpit with winget if missing, then listens for SMTP on 127.0.0.1:1025. Read the mail at http://127.0.0.1:8025.
Outside production, and with no SMTP account in the secrets file, the site sends all mail here.
#>
$ErrorActionPreference = "Stop"
if (-not (Get-Command mailpit -ErrorAction SilentlyContinue)) {
  winget install --id axllent.mailpit -e --accept-source-agreements --accept-package-agreements
  Write-Host "Mailpit installed. Open a new terminal so it is on PATH, then run this script again."
  exit 0
}
Start-Process mailpit -ArgumentList "--smtp", "127.0.0.1:1025", "--listen", "127.0.0.1:8025" -WindowStyle Hidden
Write-Host "Mailpit running: SMTP 127.0.0.1:1025, web http://127.0.0.1:8025"
