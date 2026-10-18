@echo off
chcp 65001 >nul
title AYNI - Panel de control
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0..\scripts\ayni.ps1" %*
exit /b %errorlevel%
