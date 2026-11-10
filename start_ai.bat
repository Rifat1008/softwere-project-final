@echo off
rem Starts the Upohar gift AI (Python). Keep this window open while you use the website.
cd /d "%~dp0"

rem find Python: "python" first, then the "py" launcher (installed with every Python, no PATH tick needed)
set "PY="
python --version >nul 2>nul
if not errorlevel 1 set "PY=python"
if not defined PY (
  py -3 --version >nul 2>nul
  if not errorlevel 1 set "PY=py -3"
)
if not defined PY (
  echo.
  echo Python was not found on this computer.
  echo 1. Download it from https://www.python.org/downloads/ and install it.
  echo 2. Run this file again.
  echo.
  pause
  exit /b 1
)
echo Using: %PY%

if not exist venv (
  echo First run: creating a private Python environment ...
  %PY% -m venv venv
  if errorlevel 1 (
    echo Could not create the environment.
    pause
    exit /b 1
  )
)
call venv\Scripts\activate.bat
echo Checking packages (only slow the first time) ...
python -m pip install -q -r requirements.txt
if errorlevel 1 (
  echo Could not install the packages. Check your internet connection and run this file again.
  pause
  exit /b 1
)
python app.py
pause
