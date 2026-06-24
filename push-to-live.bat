@echo off
title Push to Live — Enterns Tech
color 0A

echo.
echo  ============================================================
echo   ENTERNS TECH — PUSH TO LIVE
echo  ============================================================
echo.
echo  This will deploy your changes to enternstech.com
echo  What was changed?  (press Enter to use "Update site")
echo.
set /p MSG="  Describe changes: "
if "%MSG%"=="" set MSG=Update site

echo.
echo  Preparing files...
git add "wp-content/themes/enternstech" "wp-content/plugins/enterns-portal" "admin-portal" ".github"

git diff --cached --quiet
if %ERRORLEVEL%==0 (
    echo.
    echo  No changes to push. Everything is already up to date.
    echo.
    pause
    exit /b 0
)

echo  Committing...
git commit -m "%MSG%"

echo  Pushing to GitHub...
git push origin main

echo.
echo  ============================================================
echo   Done! GitHub Actions is now deploying to the live site.
echo   Changes will be live in about 2 minutes.
echo.
echo   Watch progress:
echo   https://github.com/bhaumikpatel81-max/EnternsTech/actions
echo  ============================================================
echo.
pause
