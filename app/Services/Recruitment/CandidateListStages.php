<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use InvalidArgumentException;

final class CandidateListStages
{
    public static function cifCandidateListStageCatalogue(): array
    {
        return [
            'cif' => ['label' => 'CIF screening', 'statuses' => ['Screening', 'Screening Failed', 'Screening Backout'], 'decisions' => ['Pending Decision', ...CandidateInterviews::cifInterviewDecisions('cif')]],
            'initial_interview' => ['label' => 'HR interview', 'statuses' => ['Initial Interview'], 'decisions' => CandidateInterviews::cifInterviewDecisions('initial_interview')],
            'department_interview' => ['label' => 'Department interview', 'statuses' => ['Department Interview', 'Department Interview & Background Check'], 'decisions' => CandidateInterviews::cifInterviewDecisions('department_interview')],
            'lie_detection' => ['label' => 'Background check', 'statuses' => ['Background Check', 'Department Interview & Background Check'], 'decisions' => CandidateInterviews::cifInterviewDecisions('lie_detection')],
            'job_offer' => ['label' => 'Job offer', 'statuses' => ['Job Offer'], 'decisions' => CandidateInterviews::cifInterviewDecisions('job_offer')],
            'deployment' => ['label' => 'Deployment', 'statuses' => ['Deployment', 'Deployed'], 'decisions' => ['Not started', 'In Progress', 'Completed']],
        ];
    }

    public static function cifCandidateListStageSql(string $stage): string
    {
        $catalogue = CandidateListStages::cifCandidateListStageCatalogue();
        if (! isset($catalogue[$stage])) {
            throw new InvalidArgumentException('Choose an available stage.');
        }
        $sql = 'candidates.status IN ('.implode(',', array_map(static fn (string $status): string => "'".$status."'", $catalogue[$stage]['statuses'])).')';
        if (in_array($stage, ['department_interview', 'lie_detection'], true)) {
            $flag = $stage === 'department_interview' ? 'department_required' : 'ldt_required';
            $sql .= " AND COALESCE((SELECT required.$flag FROM candidate_assessment_requirements required WHERE required.candidate_id=candidates.id),1)=1";
        }

        return '('.$sql.')';
    }

    public static function cifCandidateListDecisionSql(string $stage): string
    {
        if (! isset(CandidateListStages::cifCandidateListStageCatalogue()[$stage])) {
            throw new InvalidArgumentException('Choose an available stage.');
        }
        if ($stage === 'deployment') {
            return "(CASE WHEN candidates.status='Deployed' OR EXISTS (SELECT 1 FROM candidate_deployments d WHERE d.candidate_id=candidates.id AND d.completed_at IS NOT NULL) THEN 'Completed'
            WHEN EXISTS (SELECT 1 FROM candidate_deployments d WHERE d.candidate_id=candidates.id AND d.revision>0) THEN 'In Progress' ELSE 'Not started' END) COLLATE utf8mb4_unicode_ci";
        }
        $fallback = $stage === 'cif' ? "CASE candidates.status WHEN 'Screening Failed' THEN 'Failed' WHEN 'Screening Backout' THEN 'Backout' ELSE 'Pending Decision' END" : "'Pending Decision'";
        $decision = $stage === 'job_offer' ? "CASE decision WHEN 'Passed' THEN 'Accepted' WHEN 'Failed' THEN 'Declined' WHEN 'No Response' THEN 'Pending Decision' WHEN 'For Schedule!' THEN 'Pending Decision' ELSE decision END" : "CASE decision WHEN 'For Schedule!' THEN 'Pending Decision' ELSE decision END";

        // Explicit collation is required for native prepared parameters on hosted MariaDB.
        return "COALESCE((SELECT $decision FROM candidate_interview_stages s WHERE s.candidate_id=candidates.id AND s.stage='$stage'),$fallback) COLLATE utf8mb4_unicode_ci";
    }

    public static function cifCandidateListStageFilter(mixed $stage, mixed $decision): array
    {
        $catalogue = CandidateListStages::cifCandidateListStageCatalogue();
        $decisions = [];
        foreach ($catalogue as $entry) {
            $decisions = array_merge($decisions, $entry['decisions']);
        }
        if (! is_string($stage) || ($stage !== '' && ! isset($catalogue[$stage])) || ! is_string($decision) || ($decision !== '' && ! in_array($decision, $decisions, true))) {
            throw new InvalidArgumentException('Choose an available stage and stage status.');
        }
        if ($stage === '' && $decision === '') {
            return [[], []];
        }
        $conditions = [];
        $params = [];
        foreach ($stage !== '' ? [$stage] : array_keys($catalogue) as $key) {
            $condition = CandidateListStages::cifCandidateListStageSql($key);
            if ($decision !== '') {
                $condition .= ' AND '.CandidateListStages::cifCandidateListDecisionSql($key).' = ?';
                $params[] = $decision;
            }
            $conditions[] = '('.$condition.')';
        }

        return [['('.implode(' OR ', $conditions).')'], $params];
    }

    public static function cifCandidateListStageRows(array $candidate): array
    {
        $stages = [];
        foreach (CandidateListStages::cifCandidateListStageCatalogue() as $key => $entry) {
            if (in_array($candidate['status'], $entry['statuses'], true) && (! isset($candidate['required_'.$key]) || (bool) $candidate['required_'.$key])) {
                $stages[] = ['key' => $key, 'label' => $entry['label'], 'decision' => $candidate['decision_'.$key]];
            }
        }

        return $stages;
    }
}
