<?php

use App\Services\Recruitment\AuditSetup;
use App\Services\Recruitment\HrEmail;
use App\Services\Recruitment\Security;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Standalone fixture lifecycle. It cannot address a development/source database.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$operation = $argv[1] ?? '';
$database = $argv[2] ?? '';
if (PHP_SAPI !== 'cli' || ! in_array($operation, ['create', 'create-empty', 'drop'], true) || ! preg_match('/^cif_mrf_test_[a-f0-9]{16}$/D', $database)) {
    throw new InvalidArgumentException('Only a generated disposable fixture database is permitted.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, $e->getMessage());
    exit(1);
});
$config = config('database.connections.mysql');
$config['database'] = null;
$config['url'] = null;
$pdo = DB::connectUsing('fixture_server', $config, true)->getPdo();
if ($operation === 'drop') {
    $pdo->exec('DROP DATABASE `'.$database.'`');
    echo "Disposable fixture database removed.\n";
    exit;
}
$pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
if ($operation === 'create-empty') {
    echo "Empty disposable fixture database created.\n";
    exit;
}
$pdo->exec('USE `'.$database.'`');
$pdo->exec(file_get_contents(database_path('reference/legacy-mysql-schema.sql')));
Security::cifSecuritySchema($pdo);
$q = $pdo->prepare('INSERT INTO admins (email, name, password_hash) VALUES (?, ?, ?)');
$q->execute(['admin', 'HR Test', password_hash('123', PASSWORD_DEFAULT)]);
$q = $pdo->prepare('INSERT INTO department_users (username, name, position, department_name, password_hash) VALUES (?, ?, ?, ?, ?)');
foreach (['dept' => ['Test IT Requestor', 'IT Manager', 'IT'], 'otherdept' => ['Other Test Requestor', 'Finance Manager', 'Finance'], 'unconfigured' => ['Unconfigured Test Requestor', '', '']] as $username => $profile) {
    $q->execute([$username, ...$profile, password_hash('123', PASSWORD_DEFAULT)]);
}
HrEmail::cifMailSeed($pdo);
if (getenv('CIF_FIXTURE_DEFER_AUDIT') !== '1') {
    AuditSetup::cifInstallAuditTriggers($pdo);
}
echo "Disposable fixture database created.\n";
