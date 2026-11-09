<?php

namespace App\Services\Recruitment;

use PDO;
use RuntimeException;

final class RecruitmentSchema
{
    public static function manifest(): array
    {
        return json_decode(file_get_contents(database_path('schema/recruitment-schema.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function differences(PDO $pdo): array
    {
        $manifest = self::manifest();
        $existing = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $differences = [];
        foreach ($manifest['tables'] as $table => $expected) {
            if (! in_array($table, $existing, true)) {
                $differences[] = $table.': missing table';

                continue;
            }
            if (! preg_match('/^[a-z0-9_]+$/D', $table)) {
                throw new RuntimeException('Invalid schema contract table.');
            }
            $columns = [];
            foreach ($pdo->query('SHOW FULL COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $columns[] = ['name' => $column['Field'], 'type' => self::columnType($column['Type']),
                    'nullable' => $column['Null'] === 'YES', 'default' => self::columnDefault($column['Default']),
                    'extra' => strtolower(trim(str_replace('DEFAULT_GENERATED', '', $column['Extra']))), 'collation' => $column['Collation']];
            }
            $expected['columns'] = array_map(static function (array $column): array {
                $column['default'] = self::columnDefault($column['default']);

                return $column;
            }, $expected['columns']);
            $indexes = [];
            foreach ($pdo->query('SHOW INDEX FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC) as $index) {
                $indexes[] = ['name' => $index['Key_name'], 'column' => $index['Column_name'], 'unique' => ! (bool) $index['Non_unique'],
                    'sequence' => (int) $index['Seq_in_index'], 'prefix' => $index['Sub_part'] === null ? null : (int) $index['Sub_part'],
                    'type' => $index['Index_type'], 'order' => $index['Collation']];
            }
            usort($indexes, static fn (array $a, array $b): int => [$a['name'], $a['sequence']] <=> [$b['name'], $b['sequence']]);
            $status = $pdo->prepare('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $status->execute([$table]);
            $status = $status->fetch(PDO::FETCH_ASSOC);
            $create = $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
            $actual = ['engine' => strtolower($status['ENGINE']), 'collation' => $status['TABLE_COLLATION'],
                'columns' => $columns, 'indexes' => $indexes, 'foreign_keys' => self::foreignKeys($create)];
            // A table with no character columns has no stored collation-sensitive values.
            // Older security_settings inherits its server's default; preserve that metadata.
            if (! array_filter($columns, static fn (array $column): bool => $column['collation'] !== null)) {
                $actual['collation'] = $expected['collation'];
            }
            foreach ($expected as $aspect => $value) {
                if ($actual[$aspect] !== $value) {
                    $differences[] = $table.': '.$aspect.' differs';
                }
            }
        }
        $triggers = array_column($pdo->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC), null, 'Trigger');
        foreach ($manifest['triggers'] as $expected) {
            $name = $expected['Trigger'];
            $actual = $triggers[$name] ?? null;
            if (! $actual) {
                $differences[] = $name.': missing trigger';

                continue;
            }
            foreach (['Event', 'Table', 'Timing', 'Statement'] as $field) {
                if (self::triggerText($actual[$field]) !== self::triggerText($expected[$field])) {
                    $differences[] = $name.': '.$field.' differs';
                }
            }
        }

        return $differences;
    }

    private static function columnType(string $type): string
    {
        return preg_replace('/\b(tinyint|smallint|mediumint|bigint|int)\(\d+\)/', '$1', strtolower($type));
    }

    private static function columnDefault(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = (string) $value;

        return strtolower($value) === 'current_timestamp()' ? 'current_timestamp' : $value;
    }

    private static function foreignKeys(string $create): array
    {
        preg_match_all('/CONSTRAINT `([^`]+)` FOREIGN KEY \(([^)]+)\) REFERENCES (?:`[^`]+`\.)?`([^`]+)` \(([^)]+)\)([^,\n]*)/', $create, $matches, PREG_SET_ORDER);
        $keys = [];
        $names = static function (string $list): array {
            preg_match_all('/`([^`]+)`/', $list, $names);

            return $names[1];
        };
        foreach ($matches as $match) {
            $rule = static function (string $action) use ($match): string {
                preg_match('/ON '.$action.' (CASCADE|SET NULL|RESTRICT|NO ACTION)/', $match[5], $rule);

                return str_replace('NO ACTION', 'RESTRICT', $rule[1] ?? 'RESTRICT');
            };
            $keys[$match[1]] = ['columns' => $names($match[2]), 'table' => $match[3], 'references' => $names($match[4]),
                'delete' => $rule('DELETE'), 'update' => $rule('UPDATE')];
        }
        ksort($keys);

        return $keys;
    }

    private static function triggerText(string $value): string
    {
        return rtrim(trim(str_replace("\r\n", "\n", $value)), ';');
    }
}
