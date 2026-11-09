<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use LogicException;
use PDO;
use PDOException;
use SplObjectStorage;

final class Security
{
    public static function cifSecuritySchema(PDO $pdo): void
    {
        static $ready;
        $ready ??= new SplObjectStorage;
        if (isset($ready[$pdo])) {
            return;
        }
        if ($pdo->inTransaction()) {
            throw new LogicException('Initialize security before account transactions.');
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS security_settings (id TINYINT PRIMARY KEY, schema_version INT NOT NULL, audit_started DATETIME(6) NULL, requestors_superadmin_only TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB');
        $version = $pdo->query('SELECT schema_version FROM security_settings WHERE id=1')->fetchColumn();
        if ((int) $version < 1) {
            foreach (['admins' => ['role' => "VARCHAR(20) NOT NULL DEFAULT 'hr'", 'account_status' => "VARCHAR(20) NOT NULL DEFAULT 'Active'", 'access_version' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0', 'revision' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0'],
                'invitations' => ['access_enabled' => 'TINYINT NOT NULL DEFAULT 1', 'access_version' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0']] as $table => $columns) {
                $existing = array_column($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC), 'Field');
                foreach ($columns as $column => $definition) {
                    if (! in_array($column, $existing, true)) {
                        try {
                            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
                        } catch (PDOException $e) {
                            if (($e->errorInfo[1] ?? 0) !== 1060) {
                                throw $e;
                            }
                        }
                    }
                }
            }
            $pdo->exec("CREATE TABLE IF NOT EXISTS audit_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,created_at DATETIME(6) NOT NULL,
            actor_role VARCHAR(20) NOT NULL,actor_id BIGINT UNSIGNED NULL,actor_name VARCHAR(255) NOT NULL,
            category VARCHAR(32) NOT NULL,action VARCHAR(80) NOT NULL,outcome VARCHAR(20) NOT NULL DEFAULT 'Success',
            entity_type VARCHAR(64) NOT NULL,entity_id VARCHAR(100) NOT NULL,entity_label VARCHAR(255) NOT NULL,
            before_json LONGTEXT NULL,after_json LONGTEXT NULL,request_id VARCHAR(32) NULL,ip_address VARCHAR(45) NULL,
            source VARCHAR(20) NOT NULL DEFAULT 'Live',legacy_key VARCHAR(120) NULL UNIQUE,
            INDEX audit_dates(created_at,id),INDEX audit_category(category,id),INDEX audit_actor(actor_role,actor_id,id),INDEX audit_entity(entity_type,entity_id,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec('INSERT IGNORE INTO security_settings(id,schema_version) VALUES(1,1)');
        }
        if ((int) $version < 3) {
            $columns = array_column($pdo->query('SHOW COLUMNS FROM audit_events')->fetchAll(PDO::FETCH_ASSOC), 'Field');
            foreach (['references_recorded' => 'TINYINT NOT NULL DEFAULT 0', 'previous_mrf_id' => 'BIGINT UNSIGNED NULL', 'mrf_id' => 'BIGINT UNSIGNED NULL', 'mrf_number' => 'VARCHAR(40) NULL', 'mrf_batch_number' => 'VARCHAR(32) NULL', 'candidate_id' => 'BIGINT UNSIGNED NULL', 'candidate_number' => 'VARCHAR(32) NULL', 'invitation_id' => 'BIGINT UNSIGNED NULL'] as $column => $definition) {
                if (! in_array($column, $columns, true)) {
                    try {
                        $pdo->exec("ALTER TABLE audit_events ADD COLUMN `$column` $definition");
                    } catch (PDOException $e) {
                        if (($e->errorInfo[1] ?? 0) !== 1060) {
                            throw $e;
                        }
                    }
                }
            }
            $indexes = array_column($pdo->query('SHOW INDEX FROM audit_events')->fetchAll(PDO::FETCH_ASSOC), 'Key_name');
            foreach (['audit_mrf' => 'mrf_id,id', 'audit_candidate' => 'candidate_id,id', 'audit_invitation' => 'invitation_id,id'] as $name => $fields) {
                if (! in_array($name, $indexes, true)) {
                    try {
                        $pdo->exec("ALTER TABLE audit_events ADD INDEX `$name` ($fields)");
                    } catch (PDOException $e) {
                        if (($e->errorInfo[1] ?? 0) !== 1061) {
                            throw $e;
                        }
                    }
                }
            }
            $pdo->exec('UPDATE security_settings SET schema_version=3 WHERE id=1');
        }
        $ready[$pdo] = true;
    }

    public static function cifAuditContext(PDO $pdo, string $role, ?int $id, string $name): void
    {
        unset($GLOBALS['cif_audit_targets']);
        $request = $GLOBALS['cif_audit_request'] ??= bin2hex(random_bytes(16));
        $q = $pdo->prepare("SET @cif_actor_role=?,@cif_actor_id=?,@cif_actor_name=?,@cif_request_id=?,@cif_ip=?,@cif_reason='',@cif_target_candidate_id=NULL,@cif_target_mrf_id=NULL,@cif_target_mrf_known=0,@cif_previous_mrf_id=NULL");
        $q->execute([$role, $id, mb_substr($name, 0, 255), $request, substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45)]);
    }

    public static function cifAuditTargets(PDO $pdo, ?int $candidateId, ?int $mrfId, ?int $previousMrfId = null): void
    {
        $GLOBALS['cif_audit_targets'] = ['candidate_id' => $candidateId, 'mrf_id' => $mrfId, 'previous_mrf_id' => $previousMrfId];
        $q = $pdo->prepare('SET @cif_target_candidate_id=?,@cif_target_mrf_id=?,@cif_target_mrf_known=1,@cif_previous_mrf_id=?');
        $q->execute([$candidateId, $mrfId, $previousMrfId]);
    }

    public static function cifAuditSessionContext(PDO $pdo): void
    {
        $endpoint = basename(request()->path());
        $action = request()->query('action', '');
        if ($endpoint === 'superadmin.php' && ! empty(session()->get('superadmin'))) {
            $a = session()->get('superadmin');
            Security::cifAuditContext($pdo, 'Superadmin', (int) $a['id'], $a['name']);
        } elseif ((in_array($endpoint, ['mrf-requestor.php', 'department.php'], true) || in_array(request()->query()['portal'] ?? '', ['requestor', 'department'], true)) && ! empty(session()->get('department'))) {
            $a = session()->get('department');
            Security::cifAuditContext($pdo, 'MRF Requestor', (int) $a['id'], $a['name']);
        } elseif (! empty(session()->get('invitation_id')) && ($endpoint === 'access.php' && in_array($action, ['candidate_draft', 'submit', 'candidate_progress', 'candidate_logout', 'verify'], true) || empty(session()->get('admin')))) {
            Security::cifAuditContext($pdo, 'Candidate', (int) session()->get('invitation_id'), 'Candidate access #'.(int) session()->get('invitation_id'));
        } elseif (! empty(session()->get('admin'))) {
            $a = session()->get('admin');
            Security::cifAuditContext($pdo, 'HR', (int) $a['id'], $a['name']);
        } else {
            Security::cifAuditContext($pdo, 'Guest', null, 'Not signed in');
        }
    }

    public static function cifAuditWrite(PDO $pdo, string $category, string $action, string $type, string $id, string $label, array $details = [], string $outcome = 'Success'): void
    {
        $references = ['candidate_id' => null, 'mrf_id' => null, 'previous_mrf_id' => null, 'candidate_number' => null, 'invitation_id' => null, 'mrf_number' => null, 'mrf_batch_number' => null];
        $references = array_replace($references, $GLOBALS['cif_audit_targets'] ?? []);
        if ($references['candidate_id'] !== null || ($type === 'Invitation' && ctype_digit($id))) {
            $column = $references['candidate_id'] !== null ? 'id' : 'invitation_id';
            $q = $pdo->prepare("SELECT id,candidate_number,invitation_id FROM candidates WHERE $column=?");
            $q->execute([$references['candidate_id'] ?? $id]);
            if ($candidate = $q->fetch(PDO::FETCH_ASSOC)) {
                $references['candidate_id'] = $candidate['id'];
                $references['candidate_number'] = $candidate['candidate_number'];
                $references['invitation_id'] = $candidate['invitation_id'];
            }
        }
        if ($type === 'Invitation' && ctype_digit($id)) {
            $references['invitation_id'] = (int) $id;
        }
        if ($references['mrf_id'] !== null) {
            $q = $pdo->prepare('SELECT mrf_number,batch_number FROM manpower_requests WHERE id=?');
            $q->execute([$references['mrf_id']]);
            if ($mrf = $q->fetch(PDO::FETCH_ASSOC)) {
                $references['mrf_number'] = $mrf['mrf_number'];
                $references['mrf_batch_number'] = $mrf['batch_number'];
            }
        }
        $q = $pdo->prepare('INSERT INTO audit_events(created_at,actor_role,actor_id,actor_name,category,action,outcome,entity_type,entity_id,entity_label,after_json,request_id,ip_address,references_recorded,'.implode(',', array_keys($references)).")
        VALUES(UTC_TIMESTAMP(6),COALESCE(@cif_actor_role,'System'),@cif_actor_id,COALESCE(@cif_actor_name,'System'),?,?,?,?,?,?,?,@cif_request_id,@cif_ip,1,".implode(',', array_fill(0, count($references), '?')).')');
        $q->execute([$category, $action, $outcome, $type, $id, mb_substr($label, 0, 255), json_encode($details, JSON_THROW_ON_ERROR), ...array_values($references)]);
        if ($outcome === 'Failed') {
            $GLOBALS['cif_audit_failure_logged'] = true;
        }
    }

    public static function cifAdminProfile(array $account): array
    {
        return ['id' => (int) $account['id'], 'email' => $account['email'], 'name' => $account['name'], 'role' => $account['role'] ?? 'hr', 'can_manage_requestors' => ($account['role'] ?? 'hr') === 'hr' && ! Security::cifSecurityRequestorsRestricted(RecruitmentDatabase::pdo())];
    }

    public static function cifSecurityRequestorsRestricted(PDO $pdo): bool
    {
        return (bool) $pdo->query("SELECT requestors_superadmin_only AND EXISTS(SELECT 1 FROM admins WHERE role='superadmin' AND account_status='Active') FROM security_settings WHERE id=1")->fetchColumn();
    }
}
