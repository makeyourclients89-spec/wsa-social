@echo off
echo ========================================================
echo Setting up WSA Social Auto-Pilot Task in Windows Task Scheduler
echo ========================================================
schtasks /create /tn "WSASocialAutoPilot" /tr "\"C:\xampp\php\php.exe\" \"C:\xampp\htdocs\wsa-social\cron.php\"" /sc HOURLY /mo 1 /f
echo.
if %ERRORLEVEL% EQU 0 (
    echo [SUCCESS] Windows Scheduled Task 'WSASocialAutoPilot' has been created!
    echo It will run hourly and auto-share daily to your groups.
) else (
    echo [NOTE] If permission denied, please Right-Click this file and choose 'Run as Administrator'.
)
pause
