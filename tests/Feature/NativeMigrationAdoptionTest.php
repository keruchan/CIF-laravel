<?php

namespace Tests\Feature;

use App\Services\Recruitment\RecruitmentSchema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class NativeMigrationAdoptionTest extends TestCase
{
    public function test_reference_copy_uses_native_migrations_and_keeps_source_records_and_triggers(): void
    {
        $source = 'cif_mrf_test_'.bin2hex(random_bytes(8));
        $created = false;
        $target = null;
        config(['database.connections.mysql.database' => null, 'database.connections.mysql.url' => null]);
        DB::purge('mysql');
        $existing = DB::connection('mysql')->select('SHOW DATABASES');
        $existing = array_map(static fn ($row) => array_values((array) $row)[0], $existing);
        try {
            (new Process([PHP_BINARY, base_path('tests/Fixtures/database.php'), 'create', $source]))->setTimeout(30)->mustRun();
            $created = true;
            $this->app->instance('env', 'local');
            $code = Artisan::call('recruitment:copy-reference', ['--source-database' => $source]);
            $output = Artisan::output();
            preg_match('/Created (cif_laravel_dev_\d{8}_[a-f0-9]{8});/', $output, $match);
            $target = $match[1] ?? DB::connection('reference_copy_target')->getDatabaseName();
            $this->assertSame(0, $code, $output);
            $pdo = DB::connection('reference_copy_target')->getPdo();
            $this->assertSame([], RecruitmentSchema::differences($pdo));
            $this->assertSame(52, (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
            $this->assertSame('HR Test', $pdo->query('SELECT name FROM admins WHERE id=1')->fetchColumn());
            $this->assertSame(3, (int) $pdo->query('SELECT COUNT(*) FROM department_users')->fetchColumn());
        } finally {
            if ($target && preg_match('/^cif_laravel_dev_\d{8}_[a-f0-9]{8}$/D', $target) && ! in_array($target, $existing, true)) {
                $server = DB::connection('reference_copy_server');
                DB::disconnect('reference_copy_target');
                $server->statement('DROP DATABASE `'.$target.'`');
            }
            foreach (['reference_copy_source', 'reference_copy_target', 'reference_copy_server'] as $name) {
                DB::purge($name);
            }
            if ($created) {
                (new Process([PHP_BINARY, base_path('tests/Fixtures/database.php'), 'drop', $source]))->setTimeout(30)->mustRun();
            }
        }
    }

    public function test_existing_database_adoption_verifies_schema_preserves_records_and_keeps_rollback_protected(): void
    {
        $database = 'cif_mrf_test_'.bin2hex(random_bytes(8));
        $created = false;
        try {
            (new Process([PHP_BINARY, base_path('tests/Fixtures/database.php'), 'create', $database]))->setTimeout(30)->mustRun();
            $created = true;
            config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null]);
            DB::purge('mysql');
            $pdo = DB::connection()->getPdo();
            $this->assertSame([], RecruitmentSchema::differences($pdo));
            $fingerprint = function () use ($pdo): array {
                $hashes = [];
                foreach (array_keys(RecruitmentSchema::manifest()['tables']) as $table) {
                    if ($table === 'migrations') {
                        continue;
                    }
                    $rows = array_map(static fn (array $row): string => hash('sha256', serialize($row)), $pdo->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC));
                    sort($rows);
                    $hashes[$table] = $rows;
                }

                return $hashes;
            };
            $before = $fingerprint();
            $this->assertSame(0, Artisan::call('recruitment:adopt-migrations'));
            $this->assertStringContainsString('Preview only', Artisan::output());
            $this->assertSame(3, DB::table('migrations')->count());
            $pdo->exec('ALTER TABLE admins MODIFY name VARCHAR(149) NOT NULL');
            $this->assertSame(1, Artisan::call('recruitment:adopt-migrations', ['--apply' => true]));
            $this->assertStringContainsString('admins: columns differs', Artisan::output());
            $this->assertSame(3, DB::table('migrations')->count());
            $pdo->exec('ALTER TABLE admins MODIFY name VARCHAR(150) NOT NULL');
            $this->assertSame(0, Artisan::call('recruitment:adopt-migrations', ['--apply' => true]));
            $this->assertSame(52, DB::table('migrations')->count());
            $this->assertSame($before, $fingerprint());
            $this->assertSame([], RecruitmentSchema::differences($pdo));
            $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
            $this->assertStringContainsString('Nothing to migrate', Artisan::output());
            $this->assertSame(0, Artisan::call('recruitment:adopt-migrations', ['--apply' => true]));
            $this->assertStringContainsString('already aligned', Artisan::output());
            try {
                Artisan::call('migrate:rollback', ['--force' => true]);
                $this->fail('Existing audit rollback protection must remain intact.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Security and audit history are preserved', $e->getMessage());
            }
            $this->assertSame($before, $fingerprint());
            $this->assertSame(52, DB::table('migrations')->count());
        } finally {
            DB::disconnect('mysql');
            if ($created) {
                (new Process([PHP_BINARY, base_path('tests/Fixtures/database.php'), 'drop', $database]))->setTimeout(30)->mustRun();
            }
        }
    }
}
