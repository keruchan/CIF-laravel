<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class AuditReferences
{
    public static function cifAuditReadSource(): string
    {
        $json = static function (string $field): string {
            $values = [];
            foreach (['after_json', 'before_json'] as $column) {
                $values[] = "NULLIF(JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(a.$column),a.$column,'{}'),'$.$field')),'null')";
            }

            return 'COALESCE('.implode(',', $values).')';
        };
        $number = "IF(a.entity_id REGEXP '^[1-9][0-9]*$',CAST(a.entity_id AS UNSIGNED),NULL)";
        $candidateTypes = "'Candidate','CIF update','Candidate MRF','Assessment','Assessment requirements','Deployment','Candidate update','Published progress','Hiring action'";
        $candidate = 'COALESCE(a.candidate_id,'.$json('candidate_id').",IF(a.entity_type IN ($candidateTypes),$number,NULL))";
        $invitation = 'COALESCE(a.invitation_id,'.$json('invitation_id').",IF(a.entity_type='Invitation',$number,NULL),(SELECT c.invitation_id FROM candidates c WHERE c.id=$candidate))";
        $candidate = "COALESCE($candidate,(SELECT c.id FROM candidates c WHERE c.invitation_id=$invitation))";
        $mrf = 'COALESCE(a.mrf_id,'.$json('request_id').",IF(a.entity_type='Manpower request',$number,NULL),IF(a.entity_type='Management decision',(SELECT ap.request_id FROM manpower_request_approvals ap WHERE CONVERT(ap.batch_number USING utf8mb4) COLLATE utf8mb4_unicode_ci=a.entity_id COLLATE utf8mb4_unicode_ci),NULL))";
        $base = ['id', 'created_at', 'actor_role', 'actor_id', 'actor_name', 'category', 'action', 'outcome', 'entity_type', 'entity_id', 'entity_label', 'before_json', 'after_json', 'request_id', 'ip_address', 'source', 'legacy_key'];
        $select = array_map(static fn ($column) => 'a.'.$column, $base);
        $select[] = "$mrf AS mrf_id";
        $select[] = 'COALESCE(a.mrf_number,'.$json('mrf_number').",(SELECT m.mrf_number FROM manpower_requests m WHERE m.id=$mrf)) AS mrf_number";
        $select[] = 'COALESCE(a.mrf_batch_number,'.$json('batch_number').",IF(a.entity_type='Management decision',a.entity_id,NULL),(SELECT m.batch_number FROM manpower_requests m WHERE m.id=$mrf)) AS mrf_batch_number";
        $select[] = "$candidate AS candidate_id";
        $select[] = 'COALESCE(a.candidate_number,'.$json('candidate_number').",(SELECT c.candidate_number FROM candidates c WHERE c.id=$candidate)) AS candidate_number";
        $select[] = "$invitation AS invitation_id";
        // A recorded NULL means no related record at that time, not permission to use a later relationship.
        foreach ($select as &$expression) {
            if (preg_match('/^(.*) AS (mrf_id|mrf_number|mrf_batch_number|candidate_id|candidate_number|invitation_id)$/s', $expression, $match)) {
                $expression = 'IF(a.references_recorded=1,a.'.$match[2].','.$match[1].') AS '.$match[2];
            }
        }
        unset($expression);
        $select[] = 'a.references_recorded';
        $select[] = 'a.previous_mrf_id';

        return '(SELECT '.implode(',', $select).' FROM audit_events a) audit';
    }
}
