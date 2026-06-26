$projectDir = "d:\Project\Enterns Tech"
$winscp = "C:\Users\Admin\AppData\Local\Programs\WinSCP\WinSCP.com"
$tempScript = "$env:TEMP\sync_enterns.txt"

$ftpUser = "deploy@enternstech.com"
$ftpPass = "Nokia@78674067!!"
$ftpServer = "ftp.enternstech.com"

$script = @"
open ftp://$ftpUser`:$ftpPass@$ftpServer/
option batch continue
option confirm off
synchronize remote -delete -criteria=size "$projectDir\wp-content\themes\enternstech" "/public_html/enternstech/wp-content/themes/enternstech"
synchronize remote -delete -criteria=size "$projectDir\wp-content\plugins\enterns-portal" "/public_html/enternstech/wp-content/plugins/enterns-portal"
synchronize remote -delete -criteria=size -filemask="^config.php" "$projectDir\admin-portal" "/public_html/admin-portal"
put "$projectDir\diagnose-deployment.php" "/public_html/diagnose.php"
close
exit
"@

$script | Out-File -Encoding ASCII $tempScript

Write-Host "Starting FTP sync to Bluehost..."
Write-Host "Syncing: theme, plugin, admin portal, diagnostic script"
Write-Host ""

& $winscp /script=$tempScript /log=$env:TEMP\winscp_sync.log

Write-Host ""
Write-Host "Checking deployment status..."
$log = Get-Content $env:TEMP\winscp_sync.log | Select-String -Pattern "(Synchronizing|File|Directory|Complete|failed)" | Select-Object -Last 20
$log

Write-Host ""
$result = Get-Content $env:TEMP\winscp_sync.log | Select-String "Login authentication failed"
if ($result) {
    Write-Host "ERROR: Authentication failed" -ForegroundColor Red
} else {
    Write-Host "SUCCESS: Deployment completed!" -ForegroundColor Green
}

Remove-Item $tempScript -Force -ErrorAction SilentlyContinue
