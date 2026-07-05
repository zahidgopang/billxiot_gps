@echo off
setlocal
cd /d "%~dp0"

set PHP=D:\laragon\bin\php\php-8.4.8-nts-Win32-vs17-x64\php.exe
set HTTPD=D:\laragon\bin\apache\httpd-2.4.66-260223-Win64-VS18\bin\httpd.exe
set VHOST=D:\laragon\etc\apache2\sites-enabled\auto.zahid.billxiot_gps.conf
set PATCH=%~dp0deploy\laragon-apache-reverb.conf

echo.
echo [Reverb] Stopping existing process on :8080...
for /f "tokens=5" %%p in ('netstat -ano ^| findstr ":8080" ^| findstr LISTENING') do taskkill /F /PID %%p >nul 2>&1

echo [Reverb] Clearing config...
"%PHP%" artisan config:clear >nul

echo [Reverb] Starting wss://zahid.billxiot_gps:8080 ...
start "Reverb" "%PHP%" artisan reverb:start

if exist "%VHOST%" (
    findstr /C:"RewriteRule ^/app/" "%VHOST%" >nul 2>&1
    if errorlevel 1 (
        echo [Apache] WebSocket proxy missing from vhost — merge deploy\laragon-apache-reverb.conf manually.
        echo         Laragon may overwrite auto.zahid.billxiot_gps.conf on restart.
    )
)

echo.
echo Browser connects to: wss://zahid.billxiot_gps:8080/app/{key}
echo Check: window.__reverbDebug^(^)
echo.
