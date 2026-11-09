<?php

namespace Tests\Feature;

use App\Services\Recruitment\RecruitmentSchema;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FreshRecruitmentInstallationTest extends TestCase
{
    public function test_real_artisan_install_seeding_and_first_superadmin_preserve_existing_data_on_repeat(): void
    {
        $database = 'cif_mrf_test_'.bin2hex(random_bytes(8));
        $credentials = sys_get_temp_dir().DIRECTORY_SEPARATOR.$database.'-installation.txt';
        $created = false;
        $environment = ['APP_ENV' => 'local', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database, 'DB_URL' => '',
            'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'CIF_MAIL_ENABLED' => 'false',
            // Native migrations need PDO, not a mysql executable on PATH.
            'PATH' => dirname(PHP_BINARY)];
        $command = function (array $arguments, ?string $input = null) use ($environment): Process {
            $process = new Process([PHP_BINARY, base_path('artisan'), ...$arguments], base_path(), $environment);
            $process->setTimeout(60)->setInput($input)->mustRun();

            return $process;
        };
        try {
            (new Process([PHP_BINARY, base_path('tests/Fixtures/database.php'), 'create-empty', $database]))->setTimeout(30)->mustRun();
            $created = true;
            $migrationOutput = $command(['migrate', '--force'])->getOutput();
            $this->assertStringContainsString('Running migrations', $migrationOutput);
            $this->assertStringNotContainsString('Loading stored database schemas', $migrationOutput);
            config(['database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null]);
            DB::purge('mysql');
            $pdo = DB::connection('mysql')->getPdo();
            $this->assertCount(52, $pdo->query('SHOW TABLES')->fetchAll());
            $this->assertCount(68, $pdo->query('SHOW TRIGGERS')->fetchAll());
            $this->assertSame([], RecruitmentSchema::differences($pdo), 'Native migrations must preserve every column, index, foreign key, collation and trigger.');
            $this->assertSame(52, (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
            foreach (['admins', 'department_users', 'candidates', 'invitations', 'manpower_requests'] as $table) {
                $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn());
            }
            $this->assertStringContainsString('Nothing to migrate', $command(['migrate', '--force'])->getOutput());
            $command(['db:seed', '--class=RecruitmentSeeder', '--force']);
            $this->assertSame(8, (int) $pdo->query('SELECT COUNT(*) FROM hr_email_templates')->fetchColumn());
            $pdo->exec("UPDATE hr_email_templates SET subject='Preserve installation-test customization' WHERE id=(SELECT id FROM (SELECT MIN(id) id FROM hr_email_templates) t)");
            $command(['db:seed', '--class=RecruitmentSeeder', '--force']);
            $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM hr_email_templates WHERE subject='Preserve installation-test customization'")->fetchColumn());
            $first = $command(['recruitment:setup-superadmin', '--password-stdin', '--credentials-file='.$credentials], 'InstallationFixture123');
            $this->assertStringContainsString('Separate Superadmin created', $first->getOutput());
            $this->assertFileExists($credentials);
            $account = $pdo->query("SELECT * FROM admins WHERE role='superadmin'")->fetch(\PDO::FETCH_ASSOC);
            $this->assertTrue(password_verify('InstallationFixture123', $account['password_hash']));
            $savedCredentials = hash_file('sha256', $credentials);
            $repeat = $command(['recruitment:setup-superadmin', '--password-stdin', '--credentials-file='.$credentials], 'MustNotReplace123');
            $this->assertStringContainsString('Existing Superadmin preserved', $repeat->getOutput());
            $this->assertStringContainsString('Audit triggers installed: 0', $repeat->getOutput());
            $this->assertSame($savedCredentials, hash_file('sha256', $credentials));
            $this->assertSame($account['password_hash'], $pdo->query("SELECT password_hash FROM admins WHERE role='superadmin'")->fetchColumn());
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn());
        } finally {
            DB::disconnect('mysql');
            if ($created) {
                (new Process([PHP_BINARY, base_path('tests/Fixtures/database.php'), 'drop', $database]))->setTimeout(30)->mustRun();
            }
            if (is_file($credentials)) {
                unlink($credentials);
            }
        }
    }

    public function test_migrate_refuses_to_load_the_empty_baseline_over_untracked_existing_records(): void
    {
        $database = 'cif_mrf_test_'.bin2hex(random_bytes(8));
        $created = false;
        try {
            (new Process([PHP_BINARY, base_path('tests/Fixtures/database.php'), 'create-empty', $database]))->setTimeout(30)->mustRun();
            $created = true;
            config(['database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null]);
            DB::purge('mysql');
            $pdo = DB::connection('mysql')->getPdo();
            $pdo->exec('CREATE TABLE candidates (id INT PRIMARY KEY, marker VARCHAR(50))');
            $pdo->exec("INSERT INTO candidates VALUES (1, 'Preserve existing source records')");
            $process = new Process([PHP_BINARY, base_path('artisan'), 'migrate', '--database=mysql', '--force'], base_path(), [
                'APP_ENV' => 'local', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database, 'DB_URL' => '',
                'MAIL_MAILER' => 'array', 'CIF_MAIL_ENABLED' => 'false',
            ]);
            $process->setTimeout(30)->run();
            $this->assertFalse($process->isSuccessful());
            $this->assertStringContainsString('Refusing to load the empty recruitment schema', $process->getOutput().$process->getErrorOutput());
            $this->assertSame('Preserve existing source records', $pdo->query('SELECT marker FROM candidates WHERE id=1')->fetchColumn());
            $this->assertSame(['candidates'], $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN));
            $pretend = new Process([PHP_BINARY, base_path('artisan'), 'migrate', '--pretend'], base_path(), [
                'APP_ENV' => 'local', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database, 'DB_URL' => '',
                'MAIL_MAILER' => 'array', 'CIF_MAIL_ENABLED' => 'false',
            ]);
            $pretend->setTimeout(30)->run();
            $this->assertFalse($pretend->isSuccessful());
            $this->assertStringContainsString('Refusing to load the empty recruitment schema', $pretend->getOutput().$pretend->getErrorOutput());
            $this->assertSame(['candidates'], $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN));
        } finally {
            DB::disconnect('mysql');
            if ($created) {
                (new Process([PHP_BINARY, base_path('tests/Fixtures/database.php'), 'drop', $database]))->setTimeout(30)->mustRun();
            }
        }
    }
}
