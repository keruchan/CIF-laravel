<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;

final class RecruitmentDashboard
{
    public static function cifDashboardOutstandingCandidateSql(string $alias = 'c'): string
    {
        return "$alias.status NOT IN ('Deployed','Screening Failed','Screening Backout')
        AND NOT EXISTS (SELECT 1 FROM candidate_deployments deployed WHERE deployed.candidate_id=$alias.id AND deployed.completed_at IS NOT NULL)
        AND NOT EXISTS (SELECT 1 FROM candidate_interview_stages outcome
            LEFT JOIN candidate_assessment_requirements required ON required.candidate_id=outcome.candidate_id
            WHERE outcome.candidate_id=$alias.id AND outcome.completed_at IS NOT NULL
            AND outcome.decision IN ('Failed','Backout','Declined')
            AND (outcome.stage IN ('cif','initial_interview','job_offer')
              OR (outcome.stage='department_interview' AND COALESCE(required.department_required,1)=1)
              OR (outcome.stage='lie_detection' AND COALESCE(required.ldt_required,1)=1)))";
    }

    public static function cifDashboardCohort(PDO $pdo, string $metric, string $range, string $position, int $actor, ?DateTimeImmutable $today = null, ?array $coverage = null): array
    {
        $metrics = ['candidates_total', 'candidates_outstanding', 'candidates_screening', 'invitations_total', 'invitations_outstanding', 'mrf_total', 'mrf_outstanding', 'mrf_pending', 'assigned_total', 'assigned_due', 'positions_total', 'positions_progress'];
        if (! in_array($metric, $metrics, true) || ! in_array($range, ['all', '30', '90', '365'], true) || ($position !== '' && ! in_array($position, HiringPositions::cifAppliedPositions(), true))) {
            throw new InvalidArgumentException('Choose valid dashboard filters.');
        }
        $today = ($today ?? new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->setTime(0, 0);
        $allDates = ($coverage['all_dates'] ?? false) === true;
        $params = [];
        $where = [];
        $candidate = str_starts_with($metric, 'candidates_');
        $invitation = str_starts_with($metric, 'invitations_');
        $mine = str_starts_with($metric, 'assigned_');
        if ($candidate) {
            $source = 'candidates c';
            $alias = 'c';
            $created = 'submitted_at';
            $positionColumn = 'position';
            $where[] = 'c.archived_at IS NULL';
        } elseif ($invitation) {
            $source = 'invitations i';
            $alias = 'i';
            $created = 'created_at';
            $positionColumn = 'position';
            $where[] = 'i.archived_at IS NULL';
        } else {
            $source = 'manpower_requests m LEFT JOIN manpower_request_approvals ap ON ap.batch_number=m.batch_number LEFT JOIN mrf_hr_assignments owner ON owner.request_id=m.id';
            $alias = 'm';
            $created = 'created_at';
            $positionColumn = 'position_title';
        }
        // Every metric follows position/date filters. Due targets use hiring deadlines.
        if ($coverage !== null && ! $allDates && $metric !== 'assigned_due') {
            $where[] = "$alias.$created >= ? AND $alias.$created < ?";
            $params[] = $coverage['utc_from'];
            $params[] = $coverage['utc_to'];
        } elseif ($coverage === null && ! $mine) {
            if ($range !== 'all') {
                $where[] = "$alias.$created >= ?";
                $params[] = $today->modify('-'.((int) $range - 1).' days')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }
        }
        if ($position !== '') {
            $where[] = "$alias.$positionColumn = ?";
            $params[] = $position;
        }
        if ($metric === 'candidates_outstanding') {
            $where[] = RecruitmentDashboard::cifDashboardOutstandingCandidateSql();
        }
        if ($metric === 'candidates_screening') {
            $where[] = RecruitmentDashboard::cifDashboardOutstandingCandidateSql();
            $where[] = "c.status = 'Screening'";
        }
        if ($metric === 'invitations_outstanding') {
            $where[] = 'i.submitted_at IS NULL AND '.ApplicantAccess::cifInvitationCodeValidSql('i');
        }
        if (in_array($metric, ['mrf_outstanding', 'mrf_pending'], true)) {
            $where[] = "COALESCE(ap.decision,'Pending') <> 'Denied'";
            $where[] = "EXISTS (SELECT 1 FROM manpower_requests unfinished WHERE unfinished.batch_number=m.batch_number AND unfinished.status IN ('Pending','In Progress','On Hold'))";
            if ($metric === 'mrf_pending') {
                $where[] = "COALESCE(ap.decision,'Pending') = 'Pending'";
            }
        }
        if ($mine) {
            $where[] = "ap.decision='Approved' AND owner.hr_admin_id=?";
            $params[] = $actor;
            if ($metric === 'assigned_due') {
                // KPI needed-headcount rules, with the selected due-date coverage.
                $where[] = "m.status NOT IN ('On Hold','Cancelled','Rejected')";
                if ($allDates) {
                    $where[] = 'ap.hiring_due_date IS NOT NULL';
                } else {
                    $where[] = 'ap.hiring_due_date>=? AND ap.hiring_due_date<?';
                    $params[] = $coverage['date_from'] ?? $today->modify('first day of this month')->format('Y-m-d');
                    $params[] = $coverage['date_end'] ?? $today->modify('first day of next month')->format('Y-m-d');
                }
            }
        }
        if (str_starts_with($metric, 'positions_')) {
            $where[] = "ap.decision='Approved' AND m.status IN ('Pending','In Progress')";
            $where[] = 'NOT EXISTS (SELECT 1 FROM candidate_deployments filled WHERE filled.request_id=m.id AND filled.completed_at IS NOT NULL)';
            if ($metric === 'positions_progress') {
                $where[] = 'EXISTS (SELECT 1 FROM candidates c
                LEFT JOIN candidate_deployments draft ON draft.candidate_id=c.id
                LEFT JOIN candidate_mrf_assignments link ON link.candidate_id=c.id AND link.revision_id=(SELECT MAX(rev.id) FROM candidate_admin_revisions rev WHERE rev.candidate_id=c.id)
                WHERE c.archived_at IS NULL AND COALESCE(draft.request_id,link.request_id)=m.id AND '.RecruitmentDashboard::cifDashboardOutstandingCandidateSql().')';
            }
        }
        $sql = 'SELECT '.$alias.'.id'.(! $candidate && ! $invitation ? ',m.batch_number' : '').($metric === 'positions_total' ? ',m.position_title' : '').' FROM '.$source.($where ? ' WHERE '.implode(' AND ', $where) : '');
        $q = $pdo->prepare($sql);
        $q->execute($params);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        $ids = array_map('intval', array_column($rows, 'id'));
        $count = str_starts_with($metric, 'mrf_') ? count(array_unique(array_column($rows, 'batch_number'))) : count($ids);
        $result = ['count' => $count, 'ids' => $ids];
        if ($metric === 'positions_total') {
            $positions = [];
            foreach ($rows as $row) {
                $title = $row['position_title'];
                $positions[$title] = ($positions[$title] ?? 0) + 1;
            }
            $result['positions'] = [];
            foreach ($positions as $title => $available) {
                $result['positions'][] = ['position' => $title, 'count' => $available];
            }
            usort($result['positions'], static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strnatcasecmp($a['position'], $b['position']));
        }

        return $result;
    }

    public static function cifRecruitmentDashboardCards(PDO $pdo, string $range, string $position, int $actor, ?DateTimeImmutable $today = null, ?array $coverage = null): array
    {
        $counts = [];
        $openPositions = [];
        foreach (['candidates_total', 'candidates_outstanding', 'candidates_screening', 'invitations_total', 'invitations_outstanding', 'mrf_total', 'mrf_outstanding', 'mrf_pending', 'assigned_total', 'assigned_due', 'positions_total', 'positions_progress'] as $metric) {
            $cohort = RecruitmentDashboard::cifDashboardCohort($pdo, $metric, $range, $position, $actor, $today, $coverage);
            $counts[$metric] = $cohort['count'];
            if ($metric === 'positions_total') {
                $openPositions = $cohort['positions'];
            }
        }
        $today = $today ?? new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));

        return ['counts' => $counts, 'admin_id' => $actor, 'due_month' => $today->format('F Y'), 'open_positions' => $openPositions, 'coverage' => $coverage];
    }
}
