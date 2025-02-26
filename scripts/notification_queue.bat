@echo off
setlocal

REM 设置PHP路径（根据实际安装路径修改）
set PHP_PATH=C:\wamp64\bin\php\php8.1.0\php.exe

REM 设置应用根目录（根据实际路径修改）
set APP_ROOT=%~dp0..

REM 获取命令行参数
set ACTION=%1
if "%ACTION%"=="" set ACTION=run

if "%ACTION%"=="start" (
    echo Starting notification queue processor...
    start /B %PHP_PATH% %APP_ROOT%\src\Commands\ProcessNotificationQueue.php --action=run > %APP_ROOT%\logs\notification_queue.log 2>&1
    echo Queue processor started. Check logs\notification_queue.log for details.
) else if "%ACTION%"=="stop" (
    echo Stopping notification queue processor...
    %PHP_PATH% %APP_ROOT%\src\Commands\ProcessNotificationQueue.php --action=stop
    echo Queue processor stop signal sent.
) else if "%ACTION%"=="status" (
    if exist "%TEMP%\notification_queue.lock" (
        echo Queue processor is running.
    ) else (
        echo Queue processor is not running.
    )
) else (
    echo Usage: %~n0 [start^|stop^|status]
    echo   start  - Start the queue processor
    echo   stop   - Stop the queue processor
    echo   status - Check if the processor is running
) 