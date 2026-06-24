@echo off
title Sync to Live — Enterns Tech
color 0B

:: ============================================================
::  ENTERNS TECH — DIRECT FTP SYNC (via WinSCP)
::  Syncs theme + plugin + admin portal directly to Bluehost
::  No GitHub needed — changes go live in seconds
:: ============================================================
::
::  FIRST-TIME SETUP:
::  1. Open ftp-credentials.txt (in this folder)
::  2. Replace the placeholder values with your Bluehost FTP details
::  3. Save ftp-credentials.txt — then just double-click this file to sync
::
:: ============================================================

set SCRIPT_DIR=%~dp0

:: Read credentials from ftp-credentials.txt
for /f "tokens=1,2 delims==" %%A in (%SCRIPT_DIR%ftp-credentials.txt) do (
    if "%%A"=="FTP_SERVER" set FTP_SERVER=%%B
    if "%%A"=="FTP_USER"   set FTP_USER=%%B
    if "%%A"=="FTP_PASS"   set FTP_PASS=%%B
)

:: Check credentials are filled in
if "%FTP_USER%"=="your_ftp_username" (
    echo.
    echo  ERROR: Please fill in your FTP credentials first.
    echo  Open ftp-credentials.txt and replace the placeholder values.
    echo.
    pause
    exit /b 1
)

:: Find WinSCP
set WINSCP=""
if exist "C:\Program Files (x86)\WinSCP\WinSCP.com" set WINSCP="C:\Program Files (x86)\WinSCP\WinSCP.com"
if exist "C:\Program Files\WinSCP\WinSCP.com"       set WINSCP="C:\Program Files\WinSCP\WinSCP.com"

if %WINSCP%=="" (
    echo.
    echo  ERROR: WinSCP not found. Download it from https://winscp.net
    echo.
    pause
    exit /b 1
)

echo.
echo  ============================================================
echo   Connecting to %FTP_SERVER% ...
echo  ============================================================
echo.

:: Build WinSCP script on the fly
set TMPSCRIPT=%TEMP%\enterns_sync.txt
(
echo open ftp://%FTP_USER%:%FTP_PASS%@%FTP_SERVER%/
echo option batch continue
echo option confirm off
echo.
echo # --- Theme ---
echo synchronize remote -delete -criteria=size "%SCRIPT_DIR%wp-content\themes\enternstech" "/public_html/wp-content/themes/enternstech"
echo.
echo # --- Plugin ---
echo synchronize remote -delete -criteria=size "%SCRIPT_DIR%wp-content\plugins\enterns-portal" "/public_html/wp-content/plugins/enterns-portal"
echo.
echo # --- Admin Portal (skip config.php) ---
echo synchronize remote -delete -criteria=size -filemask="^config.php" "%SCRIPT_DIR%admin-portal" "/public_html/admin-portal"
echo.
echo close
echo exit
) > "%TMPSCRIPT%"

%WINSCP% /script="%TMPSCRIPT%" /log="%TEMP%\winscp_sync.log"

if %ERRORLEVEL%==0 (
    echo.
    echo  ============================================================
    echo   Sync complete! Your changes are now live on enternstech.com
    echo  ============================================================
) else (
    echo.
    echo  Sync failed. Check log: %TEMP%\winscp_sync.log
)

del "%TMPSCRIPT%" 2>nul
echo.
pause
