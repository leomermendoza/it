@echo off
setlocal
set "TASK=TrackED Daily Log Maintenance"
set "RUNNER=C:\xampp\htdocs\tracked\maintenance\run_tracked_maintenance.bat"
echo Installing %TASK% at 12:15 AM daily...
schtasks /Create /TN "%TASK%" /TR "\"%RUNNER%\"" /SC DAILY /ST 00:15 /F
if errorlevel 1 (
  echo.
  echo Task creation failed. Run this file as Administrator.
  pause
  exit /b 1
)
echo.
echo Installed successfully.
echo You can test now with:
echo schtasks /Run /TN "%TASK%"
pause
endlocal
