$php = "C:\php\php.exe"
if (-not (Test-Path $php)) { $php = "php" }
Set-Location (Split-Path $PSScriptRoot -Parent)
& $php -d extension_dir=C:\php\ext -d extension=pdo_mysql -d upload_max_filesize=8M -d post_max_size=16M -S 127.0.0.1:8080 -t public public/router.php
