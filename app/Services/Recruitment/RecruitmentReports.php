<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class RecruitmentReports
{
    public static function cifReportFilters(array $input, int $adminId): array
    {
        $reports = $input['reports'] ?? [];
        if (! is_array($reports) || ! $reports || count($reports) > 3) {
            throw new \InvalidArgumentException('Select Candidates, MRFs or KPI.');
        }
        foreach ($reports as $report) {
            if (! is_string($report) || ! in_array($report, ['candidates', 'mrfs', 'kpi'], true)) {
                throw new \InvalidArgumentException('Select Candidates, MRFs or KPI.');
            }
        }
        $scope = $input['scope'] ?? 'all';
        $archives = $input['archives'] ?? 'active';
        if (! in_array($scope, ['all', 'mine'], true) || ! in_array($archives, ['active', 'archived', 'both'], true)) {
            throw new \InvalidArgumentException('Choose valid report filters.');
        }
        $dates = ['period' => 'monthly', 'date_from' => $input['date_from'] ?? '', 'date_to' => $input['date_to'] ?? ''];
        if ($dates['date_from'] !== '' || $dates['date_to'] !== '') {
            HrKpi::cifKpiCoverage($dates, new \DateTimeImmutable('today', new \DateTimeZone('Asia/Shanghai')));
        }

        return $dates + ['reports' => array_values(array_unique($reports)), 'scope' => $scope, 'staff_id' => $scope === 'mine' ? $adminId : 0, 'archives' => $archives];
    }

    public static function cifReportQuery(\PDO $pdo, string $sql, array $params): array
    {
        $q = $pdo->prepare($sql.' LIMIT 10001');
        $q->execute($params);
        $rows = $q->fetchAll(\PDO::FETCH_ASSOC);
        if (count($rows) > 10000) {
            throw new \InvalidArgumentException('This report has more than 10,000 rows. Choose a shorter date range and try again.');
        }

        return $rows;
    }

    public static function cifRecruitmentReports(\PDO $pdo, array $filters, string $preparedBy): array
    {
        $sheets = [];
        $from = $filters['date_from'];
        $to = $filters['date_to'];
        $staff = $filters['staff_id'];
        $coverage = $from ? $from.' to '.$to : 'All dates';
        $generated = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Shanghai')))->format('Y-m-d H:i').' (UTC+8)';
        $meta = ($staff ? 'My assigned data' : 'All HR data').' | '.$coverage.' | Prepared by '.$preparedBy.' | '.$generated;
        $window = static function (string $column, array &$params, bool $utc) use ($from, $to): string {
            if (! $from) {
                return '';
            }
            $zone = new \DateTimeZone('Asia/Shanghai');
            $start = new \DateTimeImmutable($from, $zone);
            $end = (new \DateTimeImmutable($to, $zone))->modify('+1 day');
            if ($utc) {
                $start = $start->setTimezone(new \DateTimeZone('UTC'));
                $end = $end->setTimezone(new \DateTimeZone('UTC'));
            }
            $params[] = $start->format($utc ? 'Y-m-d H:i:s' : 'Y-m-d');
            $params[] = $end->format($utc ? 'Y-m-d H:i:s' : 'Y-m-d');

            return " AND $column>=? AND $column<?";
        };
        $date = static fn (?string $value): string => $value ? (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Asia/Shanghai'))->format('Y-m-d H:i') : '';
        if (in_array('candidates', $filters['reports'], true)) {
            $params = [];
            $where = '1=1';
            if ($filters['archives'] !== 'both') {
                $where .= ' AND candidates.archived_at IS '.($filters['archives'] === 'active' ? 'NULL' : 'NOT NULL');
            }
            $where .= $window('candidates.submitted_at', $params, true);
            if ($staff) {
                $where .= ' AND ownership.hr_admin_id=?';
                $params[] = $staff;
            }
            $decisions = '';
            foreach (array_keys(CandidateListStages::cifCandidateListStageCatalogue()) as $key) {
                $decisions .= ','.CandidateListStages::cifCandidateListDecisionSql($key)." AS decision_$key";
            }
            $rows = RecruitmentReports::cifReportQuery($pdo, "SELECT candidates.id,candidates.candidate_number,candidates.first_name,candidates.last_name,candidates.email,candidates.position,candidates.status,candidates.submitted_at,candidates.archived_at,m.mrf_number,a.name AS hr_name$decisions
            FROM candidates
            LEFT JOIN candidate_admin_revisions r ON r.id=(SELECT MAX(latest.id) FROM candidate_admin_revisions latest WHERE latest.candidate_id=candidates.id)
            LEFT JOIN candidate_mrf_assignments link ON link.candidate_id=candidates.id AND link.revision_id=r.id
            LEFT JOIN candidate_deployments d ON d.candidate_id=candidates.id
            LEFT JOIN manpower_requests m ON m.id=COALESCE(d.request_id,link.request_id)
            LEFT JOIN mrf_hr_assignments ownership ON ownership.request_id=m.id LEFT JOIN admins a ON a.id=ownership.hr_admin_id
            WHERE $where ORDER BY candidates.submitted_at,candidates.id", $params);
            $data = [];
            foreach ($rows as $r) {
                $status = $r['status'] === 'Initial Interview' ? 'HR interview' : ($r['status'] === 'Deployed' ? 'Hired' : $r['status']);
                $decisions = [];
                foreach (CandidateListStages::cifCandidateListStageCatalogue() as $key => $stage) {
                    if (in_array($r['status'], $stage['statuses'], true)) {
                        $decisions[] = (str_contains($r['status'], ' & ') ? $stage['label'].': ' : '').$r['decision_'.$key];
                    }
                }
                $data[] = [(int) $r['id'], $r['candidate_number'], trim($r['first_name'].' '.$r['last_name']), $r['email'], $r['position'], $status, implode('; ', $decisions), $r['mrf_number'] ?? 'Unassigned', $r['hr_name'] ?? 'Unassigned', $date($r['submitted_at']), $r['archived_at'] ? 'Archived' : 'Active'];
            }
            $sheets[] = ['name' => 'Candidates', 'note' => 'Dates use application submission. '.ucfirst($filters['archives']).' candidates. My assigned data follows the current MRF owner.', 'headers' => ['Candidate ID', 'Applicant no.', 'Candidate', 'Email', 'Position', 'Stage', 'Stage status', 'MRF no.', 'Assigned HR', 'Submitted (UTC+8)', 'Record'], 'rows' => $data];
        }
        if (in_array('mrfs', $filters['reports'], true)) {
            $params = [];
            $where = '1=1'.$window('m.created_at', $params, true);
            if ($staff) {
                $where .= ' AND o.hr_admin_id=?';
                $params[] = $staff;
            }
            $rows = RecruitmentReports::cifReportQuery($pdo, "SELECT m.id,m.mrf_number,m.batch_number,m.position_title,m.department_name,m.site_location,m.requestor_name,m.status,m.created_at,ap.decision,ap.date_approved,ap.hiring_due_date,a.name AS hr_name
            FROM manpower_requests m JOIN manpower_request_approvals ap ON ap.batch_number=m.batch_number
            LEFT JOIN mrf_hr_assignments o ON o.request_id=m.id LEFT JOIN admins a ON a.id=o.hr_admin_id WHERE $where ORDER BY m.created_at,m.id", $params);
            $data = [];
            foreach ($rows as $r) {
                $data[] = [(int) $r['id'], $r['mrf_number'], $r['batch_number'], $r['position_title'], $r['department_name'], $r['site_location'], $r['requestor_name'], $r['decision'], $r['status'], $r['hr_name'] ?? 'Unassigned', $date($r['created_at']), $r['date_approved'], $r['hiring_due_date']];
            }
            $sheets[] = ['name' => 'MRFs', 'note' => 'Dates use request creation. One row is one headcount file; Batch no. groups files from the same request.', 'headers' => ['MRF ID', 'MRF no.', 'Batch no.', 'Position', 'Department', 'Site', 'Requestor', 'Approval', 'Recruitment progress', 'Assigned HR', 'Created (UTC+8)', 'Date approved', 'Due date'], 'rows' => $data];
        }
        if (in_array('kpi', $filters['reports'], true)) {
            $kpi = HrKpi::cifHrKpi($pdo, $filters, null, false);
            if ($kpi['total'] > 10000) {
                throw new \InvalidArgumentException('KPI contains more than 10,000 MRF files. Choose a shorter date range.');
            }
            $data = [];
            $metrics = [['name' => $staff ? 'My total' : 'All HR total'] + $kpi['totals'], ...$kpi['metrics']];
            if (! $staff) {
                $metrics[] = ['name' => 'Unassigned'] + $kpi['unassigned'];
            }
            foreach ($metrics as $m) {
                $data[] = [$m['name'], $m['needed'], $m['filled'], $m['fill_rate'], $m['timeliness_rate'], $m['average_days'], $m['hires'], $m['overdue'], $m['excluded']];
            }
            $sheets[] = ['name' => 'KPI summary', 'note' => 'Dates use approved MRF hiring due dates. Matches KPI calculations. On hold, cancelled and rejected files are excluded from targets. Rates are percentages; days are calendar days.', 'headers' => ['HR member / total', 'Due headcounts', 'Filled', 'Fill rate (%)', 'Timeliness (%)', 'Average days to fill', 'Hired', 'Overdue', 'Excluded files'], 'rows' => $data];
            $data = [];
            foreach ($kpi['rows'] as $r) {
                $data[] = [$r['mrf_number'], $r['position_title'], $r['department_name'], $r['hr_name'] ?? 'Unassigned', $r['status'], $r['date_approved'], $r['hiring_due_date'], $r['filled'] ? 'Filled' : 'Unfilled', $r['hires'], $r['first_hire_date'], $r['average_days'], $r['eligible'] ? 'Included' : 'Excluded'];
            }
            $sheets[] = ['name' => 'KPI MRF details', 'note' => 'Supporting headcount files for KPI summary. All matching files are included, across every table page.', 'headers' => ['MRF no.', 'Position', 'Department', 'Assigned HR', 'Progress', 'Approved', 'Due date', 'Filled', 'Hired', 'First hire date', 'Average days', 'KPI target'], 'rows' => $data];
        }
        foreach ($sheets as &$sheet) {
            $sheet['meta'] = $meta;
        }unset($sheet);

        return $sheets;
    }
}
