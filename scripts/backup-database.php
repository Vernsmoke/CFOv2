<?php
// Local backup only; credentials and database contents are never printed.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = Illuminate\Support\Facades\DB::connection();
if ($connection->getDriverName() !== 'mysql') throw new RuntimeException('This backup script requires MySQL/MariaDB.');
$config = $connection->getConfig();
$directory = storage_path('app/private/backups');
if (! is_dir($directory)) mkdir($directory, 0700, true);
$target = $directory.'/cfo-'.date('Ymd-His').'.sql';
$binary = PHP_OS_FAMILY === 'Windows' ? 'C:/xampp/mysql/bin/mysqldump.exe' : 'mysqldump';
$process = new Symfony\Component\Process\Process([$binary, '--host='.$config['host'], '--port='.$config['port'], '--user='.$config['username'], '--single-transaction', '--skip-lock-tables', '--routines', '--triggers', '--result-file='.$target, $config['database']], null, ['MYSQL_PWD' => (string) ($config['password'] ?? '')]);
$process->setTimeout(120);
$process->mustRun();
if (! is_file($target) || filesize($target) === 0) throw new RuntimeException('Backup file was not written.');
echo 'Backup saved: '.$target.PHP_EOL;
echo 'SHA256: '.hash_file('sha256', $target).PHP_EOL;
