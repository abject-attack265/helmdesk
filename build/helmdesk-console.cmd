@echo off
cd /d "%~dp0"
set "PATH=%~dp0;%PATH%"
title HelmDesk Console
echo.
echo HelmDesk command console
echo.
echo   Start:   helmdesk serve
echo   Status:  helmdesk status
echo   Stop:    press Ctrl+C in the console running serve
echo.
cmd.exe /K
