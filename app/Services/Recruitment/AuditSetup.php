<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use PDO;
use Throwable;

final class AuditSetup
{
    public static function cifAuditDefinitions(): array
    {
        return [
            'admins' => ['Accounts', 'Account', 'id', 'email', ['email', 'name', 'role', 'account_status', 'access_version', 'revision']],
            'department_users' => ['Accounts', 'Requestor', 'id', 'username', ['username', 'name', 'position', 'department_name', 'group_name', 'account_status', 'archived_at', 'access_version', 'revision']],
            'invitations' => ['Invitations', 'Invitation', 'id', 'email', ['email', 'position', 'applicant_id', 'expires_at', 'redeemed_at', 'submitted_at', 'archived_at', 'access_enabled', 'access_version']],
            'candidates' => ['Candidates', 'Candidate', 'id', 'candidate_number', ['candidate_number', 'invitation_id', 'email', 'first_name', 'middle_name', 'last_name', 'position', 'status', 'submitted_at', 'archived_at']],
            'candidate_admin_revisions' => ['Candidates', 'CIF update', 'candidate_id', 'candidate_id', ['id', 'candidate_id', 'admin_id', 'created_at']],
            'candidate_mrf_assignments' => ['Assignments', 'Candidate MRF', 'candidate_id', 'candidate_id', ['revision_id', 'candidate_id', 'request_id']],
            'candidate_interview_stages' => ['Hiring', 'Assessment', 'candidate_id', 'stage', ['candidate_id', 'stage', 'decision', 'remarks', 'completed_at', 'revision', 'updated_by']],
            'candidate_assessment_requirements' => ['Hiring', 'Assessment requirements', 'candidate_id', 'candidate_id', ['candidate_id', 'department_required', 'ldt_required', 'reason', 'revision', 'updated_by']],
            'candidate_deployments' => ['Hiring', 'Deployment', 'candidate_id', 'candidate_id', ['candidate_id', 'request_id', 'date_hired', 'employee_id', 'position_title', 'completed_at', 'revision', 'updated_by']],
            'manpower_requests' => ['MRF', 'Manpower request', 'id', 'mrf_number', ['mrf_number', 'batch_number', 'headcount_index', 'department_user_id', 'requestor_name', 'position_title', 'department_name', 'site_location', 'headcount', 'status', 'hr_notes']],
            'manpower_request_imports' => ['MRF', 'Uploaded request', 'request_id', 'original_filename', ['request_id', 'admin_id', 'original_filename', 'mime_type', 'file_size', 'file_sha256', 'imported_at']],
            'manpower_request_approvals' => ['Approvals', 'Management decision', 'batch_number', 'batch_number', ['batch_number', 'request_id', 'decision', 'notes', 'date_approved', 'position_category', 'lead_time_days', 'hiring_due_date', 'revision', 'updated_by']],
            'mrf_hr_assignment_history' => ['Assignments', 'HR assignment', 'request_id', 'request_id', ['request_id', 'previous_hr_id', 'hr_admin_id', 'admin_id', 'source', 'reason', 'revision']],
            'hr_email_templates' => ['Email', 'Email template', 'id', 'name', ['name', 'category', 'subject', 'archived', 'revision', 'updated_by']],
            'hr_email_outbox' => ['Email', 'Email message', 'id', 'recipient_email', ['candidate_id', 'invitation_id', 'recipient_email', 'recipient_name', 'subject', 'status', 'sent_at', 'revision', 'created_by', 'updated_by']],
            'hr_email_archives' => ['Email', 'Email archive', 'message_id', 'message_id', ['message_id', 'archived_by', 'archived_at']],
            'hr_email_events' => ['Email', 'Email action', 'message_id', 'event', ['message_id', 'event', 'admin_id', 'created_at']],
            'hr_email_attempts' => ['Email', 'Sending attempt', 'message_id', 'message_id', ['message_id', 'status', 'provider_message_id', 'created_at']],
            'candidate_public_updates' => ['Hiring', 'Candidate update', 'candidate_id', 'stage', ['candidate_id', 'stage', 'email_message_id', 'published_by', 'published_at']],
            'candidate_public_state' => ['Hiring', 'Published progress', 'candidate_id', 'candidate_id', ['candidate_id', 'revision', 'published_by', 'published_at']],
            'hr_staff_profiles' => ['Accounts', 'HR profile', 'admin_id', 'admin_id', ['admin_id', 'updated_at']],
            'candidate_hr_activity' => ['Hiring', 'Hiring action', 'candidate_id', 'stage', ['id', 'candidate_id', 'stage', 'action', 'admin_id', 'decision', 'created_at']],
        ];
    }

    public static function cifAuditReferenceSql(string $table, string $row, array $columns): array
    {
        $candidate = 'NULL';
        $invitation = 'NULL';
        $mrf = 'NULL';
        if ($table === 'candidates') {
            $candidate = "$row.id";
            $invitation = "$row.invitation_id";
        } elseif (in_array('candidate_id', $columns, true)) {
            $candidate = "$row.candidate_id";
        }
        if ($table === 'invitations') {
            $invitation = "$row.id";
        } elseif (in_array('invitation_id', $columns, true)) {
            $invitation = "$row.invitation_id";
        }
        if (in_array($table, ['hr_email_archives', 'hr_email_events', 'hr_email_attempts'], true)) {
            $candidate = "(SELECT e.candidate_id FROM hr_email_outbox e WHERE e.id=$row.message_id)";
            $invitation = "(SELECT e.invitation_id FROM hr_email_outbox e WHERE e.id=$row.message_id)";
        }
        if ($invitation !== 'NULL' && $table !== 'candidates') {
            $candidate = "COALESCE($candidate,(SELECT c.id FROM candidates c WHERE c.invitation_id=$invitation))";
        }
        if ($invitation === 'NULL' && $candidate !== 'NULL') {
            $invitation = "(SELECT c.invitation_id FROM candidates c WHERE c.id=$candidate)";
        }
        if ($table === 'manpower_requests') {
            $mrf = "$row.id";
        } elseif (in_array('request_id', $columns, true)) {
            $mrf = "$row.request_id";
        } elseif ($table === 'manpower_request_approval_history') {
            $mrf = "(SELECT a.request_id FROM manpower_request_approvals a WHERE a.batch_number=$row.batch_number)";
        } elseif ($table === 'candidate_admin_revisions') {
            $mrf = "IF(@cif_target_mrf_known=1 AND @cif_target_candidate_id=$row.candidate_id,@cif_target_mrf_id,(SELECT a.request_id FROM candidate_mrf_assignments a WHERE a.revision_id=$row.id))";
        } elseif ($candidate !== 'NULL') {
            $mrf = "IF(@cif_target_mrf_known=1 AND @cif_target_candidate_id=$candidate,@cif_target_mrf_id,COALESCE((SELECT d.request_id FROM candidate_deployments d WHERE d.candidate_id=$candidate AND d.completed_at IS NOT NULL),(SELECT a.request_id FROM candidate_mrf_assignments a WHERE a.candidate_id=$candidate ORDER BY a.revision_id DESC LIMIT 1)))";
        }
        if ($candidate === 'NULL' && $mrf !== 'NULL') {
            $candidate = "IF(@cif_target_mrf_known=1 AND @cif_target_mrf_id=$mrf,@cif_target_candidate_id,NULL)";
        }
        if ($invitation === 'NULL' && $candidate !== 'NULL') {
            $invitation = "(SELECT c.invitation_id FROM candidates c WHERE c.id=$candidate)";
        }

        return [
            'references_recorded' => '1',
            'previous_mrf_id' => $table === 'candidate_deployments' && $row === 'OLD' ? "$row.request_id" : "IF(@cif_target_mrf_known=1 AND @cif_target_candidate_id=$candidate,@cif_previous_mrf_id,NULL)",
            'mrf_id' => $mrf,
            'mrf_number' => $table === 'manpower_requests' ? "$row.mrf_number" : ($mrf === 'NULL' ? 'NULL' : "(SELECT m.mrf_number FROM manpower_requests m WHERE m.id=$mrf)"),
            'mrf_batch_number' => in_array($table, ['manpower_requests', 'manpower_request_approvals', 'manpower_request_approval_history'], true) ? "$row.batch_number" : ($mrf === 'NULL' ? 'NULL' : "(SELECT m.batch_number FROM manpower_requests m WHERE m.id=$mrf)"),
            'candidate_id' => $candidate,
            'candidate_number' => $table === 'candidates' ? "$row.candidate_number" : ($candidate === 'NULL' ? ($table === 'invitations' ? "$row.applicant_id" : 'NULL') : "COALESCE((SELECT c.candidate_number FROM candidates c WHERE c.id=$candidate),(SELECT i.applicant_id FROM invitations i WHERE i.id=$invitation))"),
            'invitation_id' => $invitation,
        ];
    }

    public static function cifAuditTrigger(PDO $pdo, string $name, string $timing, string $event, string $table, string $body, array $existing): bool
    {
        if (isset($existing[$name]) && rtrim(trim($existing[$name]), ';') === rtrim(trim($body), ';')) {
            return false;
        }
        $definition = "TRIGGER `$name` $timing $event ON `$table` FOR EACH ROW ";
        if (isset($existing[$name]) && str_contains($pdo->getAttribute(PDO::ATTR_SERVER_VERSION), 'MariaDB')) {
            $pdo->exec('CREATE OR REPLACE '.$definition.$body);
        } elseif (isset($existing[$name])) {
            // MySQL operators should run upgrades during maintenance. Restore the old trigger if replacement fails.
            $pdo->exec("DROP TRIGGER `$name`");
            try {
                $pdo->exec('CREATE '.$definition.$body);
            } catch (Throwable $e) {
                $pdo->exec('CREATE '.$definition.$existing[$name]);
                throw $e;
            }
        } else {
            $pdo->exec('CREATE '.$definition.$body);
        }

        return true;
    }

    public static function cifInstallAuditTriggers(PDO $pdo): array
    {
        Security::cifSecuritySchema($pdo);
        $installed = [];
        $missing = [];
        $tables = array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0);
        $existing = array_column($pdo->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC), 'Statement', 'Trigger');
        foreach (AuditSetup::cifAuditDefinitions() as $table => [$category,$type,$idColumn,$labelColumn,$fields]) {
            if (! in_array($table, $tables, true)) {
                $missing[] = $table;

                continue;
            }
            $columns = array_column($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC), 'Field');
            $fields = array_values(array_intersect($fields, $columns));
            if (! in_array($idColumn, $columns, true) || ! in_array($labelColumn, $columns, true)) {
                $missing[] = $table;

                continue;
            }
            foreach (['INSERT', 'UPDATE', 'DELETE'] as $event) {
                $name = 'cif_audit_v1_'.substr(hash('sha256', $table.$event), 0, 24);
                $before = [];
                $after = [];
                $changed = [];
                foreach ($fields as $field) {
                    $before[] = "'$field',OLD.`$field`";
                    $after[] = "'$field',NEW.`$field`";
                    $changed[] = "NOT(BINARY OLD.`$field` <=> BINARY NEW.`$field`)";
                }
                $after[] = "'change_reason',COALESCE(@cif_reason,'')";
                if ($table === 'candidate_public_state') {
                    $before[] = "'next_schedule',JSON_EXTRACT(OLD.state_json,'$.next_schedule')";
                    $after[] = "'next_schedule',JSON_EXTRACT(NEW.state_json,'$.next_schedule')";
                    $changed[] = 'NOT(OLD.state_json<=>NEW.state_json)';
                }
                foreach (['password_hash' => 'password_changed', 'code_hash' => 'access_code_changed', 'form_data' => 'form_changed', 'hr_data' => 'assessment_answers_changed', 'body' => 'message_changed', 'photo' => 'photo_changed'] as $private => $flag) {
                    if (in_array($private, $columns, true)) {
                        $after[] = "'$flag',".($event === 'INSERT' ? 'TRUE' : "NOT(BINARY OLD.`$private` <=> BINARY NEW.`$private`)");
                        $changed[] = "NOT(BINARY OLD.`$private` <=> BINARY NEW.`$private`)";
                    }
                }
                $beforeSql = $event === 'INSERT' ? 'NULL' : 'JSON_OBJECT('.implode(',', $before).')';
                $afterSql = $event === 'DELETE' ? 'NULL' : 'JSON_OBJECT('.implode(',', $after).')';
                $row = $event === 'DELETE' ? 'OLD' : 'NEW';
                $action = $event === 'INSERT' ? "'Created'" : ($event === 'DELETE' ? "'Deleted'" : "'Updated'");
                $categorySql = "'$category'";
                if ($event === 'INSERT' && $table === 'manpower_request_approvals') {
                    $action = "'Awaiting approval'";
                }
                if ($event === 'UPDATE' && $table === 'manpower_request_approvals') {
                    $action = "CASE WHEN NEW.decision='Approved' AND NOT(OLD.decision<=>NEW.decision) THEN 'Approved' WHEN NEW.decision='Denied' AND NOT(OLD.decision<=>NEW.decision) THEN 'Denied' ELSE 'Updated' END";
                }
                if ($event === 'UPDATE' && in_array($table, ['admins', 'department_users'], true)) {
                    $action = "CASE WHEN NOT(OLD.account_status<=>NEW.account_status) THEN IF(NEW.account_status='Active','Activated','Deactivated') WHEN NOT(OLD.password_hash<=>NEW.password_hash) THEN 'Password changed' ELSE 'Updated' END";
                }
                if ($event === 'UPDATE' && $table === 'candidate_deployments') {
                    $action = "IF(OLD.completed_at IS NULL AND NEW.completed_at IS NOT NULL,'Deployment completed','Updated')";
                }
                if ($event === 'INSERT' && $table === 'candidate_deployments') {
                    $action = "IF(NEW.completed_at IS NOT NULL,'Deployment completed','Draft saved')";
                }
                if ($event !== 'DELETE' && $table === 'candidate_interview_stages') {
                    $action = "CONCAT(IF(NEW.completed_at IS NULL,'Saved: ','Finished: '),NEW.decision)";
                }
                if ($event !== 'DELETE' && $table === 'candidate_hr_activity') {
                    $action = "CONCAT(IF(NEW.action='finished','Finished: ','Saved: '),NEW.stage,IF(NEW.decision IS NULL,'',CONCAT(' / ',NEW.decision)))";
                }
                if ($event === 'UPDATE' && $table === 'manpower_requests') {
                    $action = "IF(NOT(OLD.status<=>NEW.status),CONCAT('Status: ',NEW.status),'Request updated')";
                }
                if ($event === 'UPDATE' && $table === 'hr_email_outbox') {
                    $action = "IF(NOT(OLD.status<=>NEW.status),CONCAT('Email: ',NEW.status),'Message updated')";
                }
                if ($event === 'UPDATE' && $table === 'invitations') {
                    $categorySql = "IF(NOT(OLD.access_enabled<=>NEW.access_enabled) OR NOT(OLD.code_hash<=>NEW.code_hash),'Accounts','Invitations')";
                    $action = "CASE WHEN NOT(OLD.access_enabled<=>NEW.access_enabled) THEN IF(NEW.access_enabled=1,'Access activated','Access deactivated') WHEN NOT(OLD.code_hash<=>NEW.code_hash) THEN 'Access code reset' WHEN OLD.submitted_at IS NULL AND NEW.submitted_at IS NOT NULL THEN 'CIF submitted' WHEN NOT(OLD.archived_at<=>NEW.archived_at) THEN IF(NEW.archived_at IS NULL,'Restored','Archived') ELSE 'Updated' END";
                }
                $labelSql = "CAST(COALESCE($row.`$labelColumn`,'') AS CHAR)";
                if ($table !== 'candidates' && in_array('candidate_id', $columns, true)) {
                    $labelSql = "COALESCE((SELECT CONCAT(c.candidate_number,' - ',c.first_name,' ',c.last_name) FROM candidates c WHERE c.id=$row.candidate_id),$labelSql)";
                } elseif ($table === 'mrf_hr_assignment_history' || $table === 'manpower_request_imports') {
                    $labelSql = "COALESCE((SELECT m.mrf_number FROM manpower_requests m WHERE m.id=$row.request_id),$labelSql)";
                } elseif ($table === 'hr_staff_profiles') {
                    $labelSql = "COALESCE((SELECT a.name FROM admins a WHERE a.id=$row.admin_id),$labelSql)";
                }
                $references = AuditSetup::cifAuditReferenceSql($table, $row, $columns);
                $insert = 'INSERT INTO audit_events(created_at,actor_role,actor_id,actor_name,category,action,entity_type,entity_id,entity_label,before_json,after_json,request_id,ip_address,'.implode(',', array_keys($references)).")
                VALUES(UTC_TIMESTAMP(6),COALESCE(@cif_actor_role,'System'),@cif_actor_id,COALESCE(@cif_actor_name,'System'),$categorySql,$action,'$type',CAST($row.`$idColumn` AS CHAR),LEFT($labelSql,255),$beforeSql,$afterSql,@cif_request_id,@cif_ip,".implode(',', $references).');';
                $body = $event === 'UPDATE' ? 'BEGIN IF '.implode(' OR ', $changed).' THEN '.$insert.' END IF; END' : $insert;
                if (AuditSetup::cifAuditTrigger($pdo, $name, 'AFTER', $event, $table, $body, $existing)) {
                    $installed[] = $name;
                }
            }
        }
        foreach (['UPDATE', 'DELETE'] as $event) {
            $name = 'cif_audit_readonly_'.strtolower($event);
            AuditSetup::cifAuditTrigger($pdo, $name, 'BEFORE', $event, 'audit_events', "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit records cannot be edited or deleted'", $existing);
        }
        $pdo->exec('UPDATE security_settings SET audit_started=COALESCE(audit_started,UTC_TIMESTAMP(6)) WHERE id=1');

        return ['installed' => count($installed), 'missing' => $missing];
    }

    public static function cifImportAuditHistory(PDO $pdo): int
    {
        $tables = array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0);
        $count = 0;
        $definitions = [
            'manpower_request_approval_history' => ['Approvals', 'Management decision', 'batch_number', ['batch_number', 'previous_decision', 'decision', 'notes', 'date_approved', 'hiring_due_date'], 'admin_id'],
            'manpower_request_history' => ['MRF', 'Manpower request', 'request_id', ['request_id', 'previous_status', 'status', 'hr_notes'], 'admin_id'],
            'mrf_hr_assignment_history' => ['Assignments', 'HR assignment', 'request_id', ['request_id', 'previous_hr_id', 'hr_admin_id', 'reason', 'source'], 'admin_id'],
            'candidate_interview_stage_history' => ['Hiring', 'Assessment', 'candidate_id', ['candidate_id', 'stage', 'decision', 'remarks', 'completed_at', 'revision'], 'admin_id'],
            'candidate_deployment_history' => ['Hiring', 'Deployment', 'candidate_id', ['candidate_id', 'request_id', 'date_hired', 'completed_at', 'revision'], 'admin_id'],
            'candidate_assessment_requirement_history' => ['Hiring', 'Assessment requirements', 'candidate_id', ['candidate_id', 'department_required', 'ldt_required', 'reason', 'revision'], 'admin_id'],
            'candidate_admin_revisions' => ['Candidates', 'CIF update', 'candidate_id', ['candidate_id', 'id', 'created_at'], 'admin_id'],
            'candidate_hr_activity' => ['Hiring', 'Hiring action', 'candidate_id', ['candidate_id', 'stage', 'action', 'decision'], 'admin_id'],
            'mrf_requestor_events' => ['Accounts', 'Requestor', 'requestor_id', ['requestor_id', 'action'], 'admin_id'],
            'hr_email_events' => ['Email', 'Email message', 'message_id', ['message_id', 'event'], 'admin_id'],
        ];
        foreach ($definitions as $table => [$category,$type,$entity,$fields,$actor]) {
            if (! in_array($table, $tables, true)) {
                continue;
            }
            $columns = array_column($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC), 'Field');
            if (! in_array('created_at', $columns, true)) {
                continue;
            }
            $json = [];
            foreach (array_intersect($fields, $columns) as $field) {
                $json[] = "'$field',h.`$field`";
            }
            $action = $table === 'manpower_request_approval_history' ? 'h.decision' : "'Earlier history'";
            $references = AuditSetup::cifAuditReferenceSql($table, 'h', $columns);
            $references['previous_mrf_id'] = 'NULL';
            // Stage histories have no historical MRF link; do not assign today's MRF to an earlier event.
            if (! in_array('request_id', $columns, true) && $table !== 'candidate_admin_revisions' && $table !== 'manpower_request_approval_history') {
                foreach (['mrf_id', 'mrf_number', 'mrf_batch_number'] as $field) {
                    $references[$field] = 'NULL';
                }
            }
            $q = $pdo->prepare('INSERT IGNORE INTO audit_events(created_at,actor_role,actor_id,actor_name,category,action,entity_type,entity_id,entity_label,after_json,source,legacy_key,'.implode(',', array_keys($references)).")
            SELECT h.created_at,'HR',h.`$actor`,COALESCE(a.name,'Earlier HR account'),'$category',$action,'$type',CAST(h.`$entity` AS CHAR),CAST(h.`$entity` AS CHAR),JSON_OBJECT(".implode(',', $json)."),'Earlier history',CONCAT('$table:',h.id),".implode(',', $references)."
            FROM `$table` h LEFT JOIN admins a ON a.id=h.`$actor` WHERE h.created_at<(SELECT audit_started FROM security_settings WHERE id=1)
            AND NOT EXISTS (SELECT 1 FROM audit_events existing WHERE existing.legacy_key=CONCAT('$table:',h.id))");
            $q->execute();
            $count += $q->rowCount();
        }

        return $count;
    }
}
