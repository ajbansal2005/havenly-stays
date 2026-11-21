@echo off
echo ===================================================
echo           Starting StayHub Vacation Rental
echo ===================================================
echo.

set PHP_BIN=C:\xampp\php\php.exe
if not exist "%PHP_BIN%" (
    set PHP_BIN=php
)

echo Initializing database if needed...
"%PHP_BIN%" database/setup.php

echo.
echo Starting StayHub web server at http://localhost:8000 ...
echo Press Ctrl+C in this terminal to stop the server.
echo.

start http://localhost:8000

"%PHP_BIN%" -S localhost:8000

