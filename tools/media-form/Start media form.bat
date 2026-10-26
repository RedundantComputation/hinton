@echo off
rem Starts the media form in the browser. Keep this window open while using the form; close it when finished.
cd /d "%~dp0"
where py >nul 2>nul
if %errorlevel%==0 (py media_form.py) else (python media_form.py)
pause
