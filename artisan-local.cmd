@echo off
setlocal
set "LOCAL_PHP=C:\Laragon\bin\php\php-8.4.27-Win32-vs17-x64\php.exe"
if not exist "%LOCAL_PHP%" (
    echo PHP 8.4.27 tidak ditemukan pada instalasi Laragon lokal. 1>&2
    exit /b 1
)
pushd "%~dp0"
"%LOCAL_PHP%" artisan %*
set "LOCAL_EXIT=%ERRORLEVEL%"
popd
exit /b %LOCAL_EXIT%
