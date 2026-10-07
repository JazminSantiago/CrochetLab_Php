@echo off
rem Respaldo cifrado de CrochetLab. Lo ejecuta el Programador de tareas de Windows cada 24 h.
rem Ajusta PHP si no usas XAMPP en C:\xampp
set PHP=C:\xampp\php\php.exe
if not exist "%PHP%" set PHP=php
cd /d "%~dp0\.."
"%PHP%" database\respaldo.php
exit /b %ERRORLEVEL%
