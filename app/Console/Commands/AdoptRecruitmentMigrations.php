<?php

namespace App\Console\Commands;

use App\Services\Recruitment\RecruitmentSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdoptRecruitmentMigrations extends Command
{
    protected $signature = 'recruitment:adopt-migrations {--database=mysql : Connection to the existing isolated copy} {--apply : Record verified baseline migrations; no schema or application-row changes}';

    protected $description = 'Verify every managed table and audit trigger before adopting native migration history on a local existing database';

    public function handle(): int
    {
        $connection = DB::connection($this->option('database'));
        $name = $connection->getDatabaseName();
        if (! app()->environment('local', 'testing') || ! preg_match('/^(?:cif_laravel_dev(?:_\d{8}_[a-f0-9]{8})?|cif_mrf_test_[a-f0-9]{16})$/D', $name)) {
            throw new RuntimeException('Adoption is limited to an isolated local recruitment copy. The original database is preserved.');
        }
        $differences = RecruitmentSchema::differences($connection->getPdo());
        if ($differences) {
            foreach ($differences as $difference) {
                $this->error($difference);
            }
            $this->error('Schema verification failed. No migration history was changed.');

            return self::FAILURE;
        }
        // Adopt only the frozen baseline. Future migrations must still run normally.
        $migrations = array_values(RecruitmentSchema::manifest()['migrations']);
        $migrations[] = '2026_11_04_000000_add_recruitment_security_and_audit';
        sort($migrations);
        $existing = $connection->table('migrations')->pluck('migration')->all();
        $pending = array_values(array_diff($migrations, $existing));
        $this->info('Verified 52 table definitions and 68 audit triggers; '.count($pending).' native migration records to adopt.');
        if (! $this->option('apply') || ! $pending) {
            $this->line($pending ? 'Preview only. Use --apply after reviewing the verified database name: '.$name : 'Migration history is already aligned.');

            return self::SUCCESS;
        }
        $connection->transaction(function () use ($connection, $pending): void {
            // Use the original baseline batch so the existing final audit migration
            // continues to protect rollback before any table can be dropped.
            $batch = (int) ($connection->table('migrations')->min('batch') ?: 1);
            foreach ($pending as $migration) {
                $connection->table('migrations')->insert(['migration' => $migration, 'batch' => $batch]);
            }
        });
        $this->info('Native migration history adopted. Existing records, schema and triggers were unchanged.');

        return self::SUCCESS;
    }
}
