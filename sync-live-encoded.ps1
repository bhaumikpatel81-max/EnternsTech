$projectDir = "d:\Project\Enterns Tech"
$winscp = "C:\Users\Admin\AppData\Local\Programs\WinSCP\WinSCP.com"
$tempScript = "$env:TEMP\sync_enterns.txt"

# Password with special characters - need to escape ! as %21 in URL
$ftpUser = "deploy@enternstech.com"
$ftpPass = "Nokia@78674067!!!"
# URL encode the password
$ftpPassEncoded = $ftpPass -replace '!', '%21'

$script = @"
open ftp://$ftpUser`:$ftpPassEncoded@ftp.enternstech.com/
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

Write-Host "Starting FTP sync to Bluehost (with URL encoding)..."
Write-Host "Syncing: theme, plugin, admin portal, diagnostic script"
Write-Host ""

& $winscp /script=$tempScript /log=$env:TEMP\winscp_sync.log

Write-Host ""
Write-Host "Checking result..."
$log = Get-Content $env:TEMP\winscp_sync.log -Tail 5
$log

Remove-Item $tempScript -Force -ErrorAction SilentlyContinue
