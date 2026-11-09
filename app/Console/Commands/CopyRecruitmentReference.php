<?php

namespace App\Console\Commands;

use App\Services\Recruitment\RecruitmentSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class CopyRecruitmentReference extends Command
{
    protected $signature = 'recruitment:copy-reference {--source-database=cif} {--activate : Preserve the private environment outside the webroot and update only DB_DATABASE}';

    protected $description = 'Create and verify a new isolated copy of a read-only source snapshot; never replace an existing database';

    public function handle(): int
    {
        $sourceName = $this->option('source-database');
        if (! app()->environment('local') || ! preg_match('/^[A-Za-z0-9_]+$/D', $sourceName)) {
            $this->error('Use this command only in the local development environment.');

            return self::FAILURE;
        }
        $targetName = 'cif_laravel_dev_'.date('Ymd').'_'.bin2hex(random_bytes(4));
        $config = config('database.connections.mysql');
        $config['url'] = null;
        $config['database'] = null;
        $server = DB::connectUsing('reference_copy_server', $config, true)->getPdo();
        // No IF NOT EXISTS: a collision must fail without modifying that database.
        $server->exec('CREATE DATABASE `'.$targetName.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $config['database'] = $sourceName;
        $source = DB::connectUsing('reference_copy_source', $config, true)->getPdo();
        $config['database'] = $targetName;
        $target = DB::connectUsing('reference_copy_target', $config, true)->getPdo();
        foreach ([$source, $target] as $pdo) {
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
        $source->exec('SET TRANSACTION READ ONLY');
        $source->beginTransaction();
        $infrastructure = ['users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations'];
        $tables = array_values(array_diff($source->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), $infrastructure));
        foreach ($tables as $table) {
            if (! preg_match('/^[A-Za-z0-9_]+$/D', $table)) {
                throw new \RuntimeException('Unexpected reference table name.');
            }
        }
        try {
            if ($this->call('migrate', ['--database' => 'reference_copy_target', '--force' => true]) !== self::SUCCESS) {
                throw new \RuntimeException('Native migration initialization failed. Existing databases are unchanged.');
            }
            // This unique new target contains no source records yet. Disable its
            // new audit triggers during the verified copy, then restore source triggers.
            foreach ($target->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC) as $trigger) {
                if (! preg_match('/^cif_audit_[a-z0-9_]+$/D', $trigger['Trigger'])) {
                    throw new \RuntimeException('Unexpected target trigger.');
                }
                $target->exec('DROP TRIGGER `'.$trigger['Trigger'].'`');
            }
            $target->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach ($tables as $table) {
                $definition = $source->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
                $target->exec('DROP TABLE IF EXISTS `'.$table.'`');
                $target->exec($definition);
                $rows = $source->query('SELECT * FROM `'.$table.'`');
                $insert = null;
                $sourceHashes = [];
                while ($row = $rows->fetch()) {
                    $insert ??= $target->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`', array_keys($row)).'`) VALUES ('.implode(',', array_fill(0, count($row), '?')).')');
                    $insert->execute(array_values($row));
                    ksort($row);
                    $sourceHashes[] = hash('sha256', serialize($row));
                }
                $targetHashes = [];
                $rows = $target->query('SELECT * FROM `'.$table.'`');
                while ($row = $rows->fetch()) {
                    ksort($row);
                    $targetHashes[] = hash('sha256', serialize($row));
                }
                sort($sourceHashes);
                sort($targetHashes);
                if ($sourceHashes !== $targetHashes || $target->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1] !== $definition) {
                    throw new \RuntimeException('Verification failed for '.$table.'. Existing databases are unchanged.');
                }
                $this->line($table.': '.count($sourceHashes).' rows verified');
            }
            $target->exec('SET FOREIGN_KEY_CHECKS=1');
            // Recreate only after copying, so triggers cannot duplicate the saved audit log.
            $triggers = $source->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($triggers as $trigger) {
                if (! preg_match('/^[A-Za-z0-9_]+$/D', $trigger['Trigger']) || ! in_array($trigger['Table'], $tables, true)) {
                    throw new \RuntimeException('Unexpected reference trigger.');
                }
                $target->exec('CREATE TRIGGER `'.$trigger['Trigger'].'` '.$trigger['Timing'].' '.$trigger['Event'].' ON `'.$trigger['Table'].'` FOR EACH ROW '.$trigger['Statement']);
            }
            if (count($target->query('SHOW TRIGGERS')->fetchAll()) !== count($triggers)) {
                throw new \RuntimeException('Trigger verification failed. The previous environment is unchanged.');
            }
            $this->line(count($triggers).' reference triggers preserved.');
            $differences = RecruitmentSchema::differences($target);
            if ($differences) {
                throw new \RuntimeException('Copied schema does not match native migrations: '.implode('; ', $differences));
            }
        } finally {
            if ($source->inTransaction()) {
                $source->rollBack();
            }
        }
        if ($this->option('activate')) {
            $environment = file_get_contents(base_path('.env'));
            if (! preg_match('/^DB_DATABASE=.*$/m', $environment) || preg_match('/^DB_URL=.+$/m', $environment)) {
                throw new \RuntimeException('Copy verified. Review the database environment setting before activation.');
            }
            $backup = dirname(base_path()).'/cif-migration-backups/'.date('Ymd-His').'-laravel-copy';
            if (! mkdir($backup, 0700, true) || file_put_contents($backup.'/environment-before.env', $environment) === false) {
                throw new \RuntimeException('Could not preserve the private environment. No settings were changed.');
            }
            file_put_contents(base_path('.env'), preg_replace('/^DB_DATABASE=.*$/m', 'DB_DATABASE='.$targetName, $environment));
            $this->info('Laravel now uses the verified copy. The previous database and private settings backup are preserved.');
        }
        $this->info('Created '.$targetName.'; '.count($tables).' recruitment tables verified. Source unchanged.');

        return self::SUCCESS;
    }
}
