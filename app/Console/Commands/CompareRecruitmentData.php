<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class CompareRecruitmentData extends Command
{
    protected $signature = 'recruitment:compare-data {--source-database=cif : Read-only reference database on the configured MySQL server}';

    protected $description = 'Compare all recruitment table definitions and row hashes without displaying private data or writing either database';

    public function handle(): int
    {
        $config = config('database.connections.mysql');
        $sourceName = $this->option('source-database');
        $targetName = $config['database'];
        if (! preg_match('/^[a-zA-Z0-9_]+$/D', $sourceName) || $sourceName === $targetName || ! preg_match('/^cif_laravel_dev(?:_\d{8}_[a-f0-9]{8})?$/D', $targetName)) {
            $this->error('Use the isolated cif_laravel_dev target and a distinct reference database.');

            return self::FAILURE;
        }
        $config['url'] = null;
        $config['database'] = $sourceName;
        $source = DB::connectUsing('recruitment_reference', $config, true)->getPdo();
        $target = DB::connection('mysql')->getPdo();
        $infrastructure = ['users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations'];
        $tables = array_values(array_diff($source->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), $infrastructure));
        foreach ($tables as $table) {
            if (! preg_match('/^[A-Za-z0-9_]+$/D', $table)) {
                throw new \RuntimeException('Unexpected reference table name.');
            }
        }
        $mismatches = [];
        foreach ([$source, $target] as $pdo) {
            $pdo->exec('SET TRANSACTION READ ONLY');
            $pdo->beginTransaction();
        }
        try {
            foreach ($tables as $table) {
                $hashes = [];
                foreach ([$source, $target] as $pdo) {
                    $rows = [];
                    $query = $pdo->query('SELECT * FROM `'.$table.'`');
                    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                        ksort($row);
                        // Serialize preserves binary document bytes and null/string distinctions.
                        $rows[] = hash('sha256', serialize($row));
                    }
                    sort($rows, SORT_STRING);
                    $definition = $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
                    $hashes[] = ['count' => count($rows), 'rows' => hash('sha256', implode('', $rows)), 'schema' => hash('sha256', $definition)];
                }
                if ($hashes[0] !== $hashes[1]) {
                    $mismatches[] = $table;
                    $this->warn($table.': '.($hashes[0]['rows'] === $hashes[1]['rows'] ? 'schema differs' : 'data differs').' (reference '.$hashes[0]['count'].', target '.$hashes[1]['count'].')');
                }
            }
        } finally {
            foreach ([$source, $target] as $pdo) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
            DB::purge('recruitment_reference');
        }
        $this->info(count($tables).' recruitment tables compared; '.count($mismatches).' differences. No records changed.');

        return $mismatches ? self::FAILURE : self::SUCCESS;
    }
}
