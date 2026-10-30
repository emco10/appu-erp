@echo off
setlocal
set "APPU_DB_NAME=db_appu_qa"
call "%~dp0run-smoke.cmd"
exit /b %errorlevel%
