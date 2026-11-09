<?php

use App\Services\Recruitment\HrEmail;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$database = getenv('CIF_DB_NAME');
if (PHP_SAPI !== 'cli' || ! preg_match('/^cif_mrf_test_[a-f0-9]{16}$/D', $database ?: '')) {
    throw new InvalidArgumentException('Only a disposable fixture database is permitted.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null]);
DB::purge('mysql');
HrEmail::cifMailSeed(DB::connection('mysql')->getPdo());
