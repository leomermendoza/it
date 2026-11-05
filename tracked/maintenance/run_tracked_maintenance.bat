@echo off
setlocal
set "PHP=C:\xampp\php\php.exe"
set "SCRIPT=C:\xampp\htdocs\tracked\maintenance\run_maintenance.php"
set "LOGDIR=C:\xampp\tracked_backups"
if not exist "%LOGDIR%" mkdir "%LOGDIR%"
"%PHP%" "%SCRIPT%" >> "%LOGDIR%\maintenance.log" 2>&1
endlocal
