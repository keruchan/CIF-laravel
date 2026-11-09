<?php

namespace App\Services\Recruitment;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RecruitmentMigrationGuard
{
    public function handle(CommandStarting $event): void
    {
        if ($event->command !== 'migrate') {
            return;
        }
        $name = $event->input->getParameterOption('--database', null);
        $connection = DB::connection($name ?: null);
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        $schema = $event->input->getParameterOption('--schema-path', null);
        if (! $schema) {
            $base = database_path('schema/'.$connection->getName().'-schema');
            $schema = is_file($base.'.dump') ? $base.'.dump' : $base.'.sql';
        }
        if (! is_file($schema) && ! is_file(database_path('schema/recruitment-schema.json'))) {
            return;
        }
        // Imported tables must be explicitly verified and adopted before migration.
        $tables = $connection->getSchemaBuilder()->getTableListing($connection->getDatabaseName(), false);
        $repository = config('database.migrations.table', 'migrations');
        $repositoryTable = $connection->getTablePrefix().$repository;
        if (in_array($repositoryTable, $tables, true) && $connection->table($repository)->exists()) {
            return;
        }
        if (array_diff($tables, [$repositoryTable])) {
            throw new RuntimeException('Existing tables have no Laravel migration history. Refusing to load the empty recruitment schema. Review recruitment:adopt-migrations before running migrations.');
        }
    }
}
