<?php

declare (strict_types=1);
require dirname(__DIR__, 2) . "/vendor/autoload.php";
$app = require dirname(__DIR__, 2) . "/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, $e::class . ": " . $e->getMessage());
    exit(1);
});
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$testDatabase = $argv[1] ?? '';
if (!preg_match('/^cif_mrf_test_[a-f0-9]{16}$/D', $testDatabase)) {
    throw new InvalidArgumentException('Only an isolated MRF test database is permitted.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=' . $testDatabase . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
function kpiAssert(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
$sample = $pdo->query('SELECT * FROM manpower_requests ORDER BY id LIMIT 1')->fetch();
if (!$sample) {
    throw new RuntimeException('Create the isolated candidate/MRF fixture first.');
}
$pdo->beginTransaction();
try {
    $q = $pdo->prepare('INSERT INTO admins (email,name,password_hash) VALUES (?,?,?)');
    $q->execute(['kpi-owner', 'Second HR Member', password_hash('123', PASSWORD_DEFAULT)]);
    $bob = (int) $pdo->lastInsertId();
    $request = function (string $label, string $approved, string $category, ?int $owner = null, int $headcount = 1, string $status = 'Pending', string $decision = 'Approved') use ($pdo, $sample): array {
        $timing = \App\Services\Recruitment\MrfApproval::cifMrfApprovalTiming(['date_approved' => $approved, 'position_category' => $category], new DateTimeImmutable('2027-12-31', new DateTimeZone('Asia/Shanghai')));
        $ids = [];
        $batch = 'KPI-' . $label;
        for ($index = 1; $index <= $headcount; $index++) {
            $row = $sample;
            unset($row['id']);
            $row['mrf_number'] = $batch . '-' . $index;
            $row['batch_number'] = $batch;
            $row['headcount'] = $headcount;
            $row['headcount_index'] = $index;
            $row['status'] = $status;
            $row['submission_key'] = hash('sha256', $label . ':' . $index);
            $q = $pdo->prepare('INSERT INTO manpower_requests (`' . implode('`,`', array_keys($row)) . '`) VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')');
            $q->execute(array_values($row));
            $ids[] = (int) $pdo->lastInsertId();
        }
        $q = $pdo->prepare('INSERT INTO manpower_request_approvals (batch_number,request_id,decision,date_approved,position_category,lead_time_days,hiring_due_date) VALUES (?,?,?,?,?,?,?)');
        $q->execute([$batch, $ids[0], $decision, ...array_values($timing)]);
        if ($owner !== null) {
            foreach ($ids as $id) {
                \App\Services\Recruitment\MrfOwnership::cifAssignMrfHr($pdo, $id, $owner, 1, 'approval', 'Assigned in isolated regression.');
            }
        }
        return $ids;
    };
    $hire = function (int $requestId, ?string $date, bool $complete = true, bool $archived = false) use ($pdo): int {
        $q = $pdo->prepare('INSERT INTO invitations (email,created_by) VALUES (?,1)');
        $q->execute(['kpi-' . bin2hex(random_bytes(5)) . '@example.test']);
        $invitation = (int) $pdo->lastInsertId();
        $q = $pdo->prepare('INSERT INTO candidates (candidate_number,invitation_id,email,first_name,last_name,form_data,status,archived_at) VALUES (?,?,?,?,?,?,?,?)');
        $q->execute(['KPI' . $invitation, $invitation, 'kpi-' . $invitation . '@example.test', 'CANDIDATE', 'RECORD', '{}', $complete ? 'Deployed' : 'Deployment', $archived ? '2026-10-22 00:00:00' : null]);
        $id = (int) $pdo->lastInsertId();
        $q = $pdo->prepare('INSERT INTO candidate_deployments (candidate_id,request_id,date_hired,position_title,completed_at,revision,updated_by,updated_at) VALUES (?,?,?, ?,?,1,1,UTC_TIMESTAMP())');
        $q->execute([$id, $requestId, $date, 'Engineering Support Staff', $complete ? '2026-10-22 00:00:00' : null]);
        return $id;
    };
    $ana = $request('ANA', '2026-09-01', 'A', 1, 2);
    $b = $request('BOB', '2026-09-10', 'B', $bob);
    $unassigned = $request('UNASSIGNED', '2026-09-21', 'A', null, 1, 'Completed');
    $held = $request('HELD', '2026-09-05', 'C-1', 1, 1, 'On Hold');
    $cancelled = $request('CANCELLED', '2026-09-05', 'C-1', $bob, 1, 'Cancelled');
    $request('REJECTED', '2026-09-05', 'C-1', 1, 1, 'Rejected');
    $nov = $request('NOVEMBER', '2026-09-10', 'D', 1);
    $request('DENIED', '2026-09-01', 'A', null, 1, 'Pending', 'Denied');
    $hire($ana[0], '2026-09-21', true, true);
    $hire($b[0], '2026-10-20');
    $hire($held[0], '2026-09-15');
    $hire($nov[0], '2026-09-20');
    $hire($unassigned[0], '2026-10-01', false);
    $input = ['period' => 'monthly', 'year' => 2026, 'month' => 10];
    $today = new DateTimeImmutable('2026-10-23', new DateTimeZone('Asia/Shanghai'));
    $result = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, $input, $today);
    $t = $result['totals'];
    kpiAssert($t['needed'] === 4 && $t['filled'] === 2 && $t['hires'] === 2, 'One needed per headcount file; only completed deployments fill a file; held/cancelled/rejected excluded.');
    kpiAssert($t['fill_rate'] === 50.0 && $t['average_days'] === 30.0 && $t['timeliness_rate'] === 45.24, 'Fill, average calendar days and average per-hire lead-time ratio are exact.');
    kpiAssert($t['excluded'] === 3 && $result['unassigned']['needed'] === 1, 'Team totals expose unassigned targets without crediting them to an HR member.');
    $charts = \App\Services\Recruitment\HrKpi::cifHrKpiCharts($pdo, $input, $today);
    kpiAssert(count($charts['trend']['points']) === 12, 'Monthly trends include January through December.');
    foreach (['fill_rate', 'timeliness_rate', 'average_days', 'hires'] as $key) {
        kpiAssert($charts['trend']['points'][9][$key] === $t[$key], 'October chart point matches its KPI card: ' . $key);
    }
    kpiAssert($charts['trend']['points'][0]['fill_rate'] === null && $charts['trend']['points'][0]['hires'] === 0, 'No-target months are gaps for rates, but zero for hires.');
    $yearCharts = \App\Services\Recruitment\HrKpi::cifHrKpiCharts($pdo, ['period' => 'yearly', 'year' => 2026], $today);
    kpiAssert(array_column($yearCharts['trend']['points'], 'key') === [2022, 2023, 2024, 2025, 2026] || array_column($yearCharts['trend']['points'], 'key') === ['2022', '2023', '2024', '2025', '2026'], 'Yearly trends cover five years ending in the selected year.');
    $coverage = ['date_from' => '2026-10-01', 'date_to' => '2026-10-15'];
    $rangeTotal = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, $coverage, $today);
    kpiAssert($rangeTotal['totals']['needed'] === 3 && $rangeTotal['totals']['filled'] === 2, 'Coverage includes both boundary dates and excludes later due files.');
    foreach (['weekly', 'monthly', 'yearly'] as $grouping) {
        $grouped = \App\Services\Recruitment\HrKpi::cifHrKpiCharts($pdo, $coverage + ['period' => $grouping], $today);
        kpiAssert(array_sum(array_column($grouped['trend']['points'], 'needed')) === $rangeTotal['totals']['needed'] && array_sum(array_column($grouped['trend']['points'], 'hires')) === $rangeTotal['totals']['hires'], 'Chart grouping never changes exact coverage totals.');
        kpiAssert($grouped['trend']['points'][0]['date_from'] === '2026-10-01' && end($grouped['trend']['points'])['date_to'] === '2026-10-15', 'First and last chart buckets are clipped to explicit coverage.');
    }
    $single = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, ['date_from' => '2026-10-01', 'date_to' => '2026-10-01'], $today);
    kpiAssert($single['totals']['needed'] === 2, 'A single date counts files on that date only.');
    $filtered = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, $input + ['staff_id' => 1], $today);
    kpiAssert($filtered['totals']['needed'] === 2 && $filtered['totals']['hires'] === 1 && $filtered['totals']['average_days'] === 20.0, 'HR ownership, not phase finisher, determines KPI credit.');
    $yearly = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, ['period' => 'yearly', 'year' => 2026], $today);
    kpiAssert($yearly['totals']['needed'] === 5 && $yearly['totals']['hires'] === 3, 'Yearly cohort includes November MRF even when the hire was in September.');
    $pdo->exec('SAVEPOINT kpi_duplicate');
    $hire($ana[0], '2026-10-01');
    $duplicate = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, $input, $today);
    kpiAssert($duplicate['totals']['filled'] === 2 && $duplicate['totals']['hires'] === 3 && $duplicate['totals']['overfilled'] === 1, 'Repeated candidates cannot inflate filled headcount.');
    $pdo->exec('ROLLBACK TO SAVEPOINT kpi_duplicate');
    $pdo->exec('SAVEPOINT kpi_invalid');
    $hire($ana[0], '2026-08-31');
    $invalid = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, $input, $today);
    kpiAssert($invalid['totals']['invalid_dates'] === 1 && $invalid['totals']['average_days'] === 30.0, 'Negative elapsed dates are flagged, never silently clamped to zero.');
    $pdo->exec('ROLLBACK TO SAVEPOINT kpi_invalid');
    $pdo->exec('SAVEPOINT kpi_long_hire');
    $long = $request('LONG-HIRE', '2026-09-01', 'A', $bob);
    $hire($long[0], '2026-11-30');
    $late = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, $input + ['staff_id' => $bob], new DateTimeImmutable('2026-12-31'));
    kpiAssert($late['totals']['timeliness_rate'] > 100, 'Timeliness is the requested duration ratio, not an inverted or capped on-time score.');
    $pdo->exec('ROLLBACK TO SAVEPOINT kpi_long_hire');
    $q = $pdo->prepare('SELECT date_approved,hiring_due_date FROM manpower_request_approvals WHERE request_id=?');
    $q->execute([$held[0]]);
    $original = $q->fetch();
    $pdo->prepare("UPDATE manpower_requests SET status='In Progress' WHERE id=?")->execute([$held[0]]);
    $resumed = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, $input, $today);
    kpiAssert($resumed['totals']['needed'] === 5 && $resumed['totals']['hires'] === 3 && $resumed['totals']['average_days'] === 23.33, 'Resumed file retains ten original elapsed days, not zero.');
    $q->execute([$held[0]]);
    kpiAssert($q->fetch() === $original, 'Hold/resume does not reset approval or deadline.');
    $pdo->prepare("UPDATE manpower_requests SET status='In Progress' WHERE id=?")->execute([$cancelled[0]]);
    kpiAssert(\App\Services\Recruitment\HrKpi::cifHrKpi($pdo, $input, $today)['totals']['needed'] === 6, 'Reinstatement restores the original target.');
    $auto = $request('AUTO', '2026-09-21', 'A');
    \App\Services\Recruitment\CandidateMrf::startCandidateMrf($pdo, $auto[0], 1, false, 'screening');
    kpiAssert(\App\Services\Recruitment\MrfOwnership::cifMrfHrAssignment($pdo, $auto[0])['hr_admin_id'] === null, 'Unpassed screening draft does not assign an HR owner.');
    \App\Services\Recruitment\CandidateMrf::startCandidateMrf($pdo, $auto[0], $bob, true, 'screening');
    $owned = \App\Services\Recruitment\MrfOwnership::cifMrfHrAssignment($pdo, $auto[0]);
    kpiAssert($owned['hr_admin_id'] === $bob && $owned['source'] === 'screening', 'Passed screening claims an unassigned MRF even if already In Progress.');
    \App\Services\Recruitment\CandidateMrf::startCandidateMrf($pdo, $auto[0], 1, true, 'screening');
    kpiAssert(\App\Services\Recruitment\MrfOwnership::cifMrfHrAssignment($pdo, $auto[0])['hr_admin_id'] === $bob, 'Automatic use never overwrites the assigned HR.');
    try {
        \App\Services\Recruitment\MrfOwnership::cifAssignMrfHr($pdo, $auto[0], 1, 1, 'manual', '', 1);
        throw new RuntimeException('Missing reason accepted.');
    } catch (InvalidArgumentException $expected) {
    }
    \App\Services\Recruitment\MrfOwnership::cifAssignMrfHr($pdo, $auto[0], 1, 1, 'manual', 'Work reassigned to HR.', 1);
    $owned = \App\Services\Recruitment\MrfOwnership::cifMrfHrAssignment($pdo, $auto[0]);
    kpiAssert($owned['revision'] === 2 && count($owned['history']) === 2, 'Assignment changes keep a separate audit history.');
    try {
        \App\Services\Recruitment\MrfOwnership::cifAssignMrfHr($pdo, $auto[0], $bob, 1, 'manual', 'Outdated request.', 1);
        throw new RuntimeException('Stale revision accepted.');
    } catch (\App\Services\Recruitment\CifMrfAssignmentConflict $expected) {
    }
    $cross = $request('YEAR-BOUNDARY', '2026-12-01', 'D', 1);
    $crossResult = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, ['period' => 'monthly', 'year' => 2027, 'month' => 1], new DateTimeImmutable('2027-02-01'));
    kpiAssert($crossResult['totals']['needed'] === 1 && $crossResult['rows'][0]['hiring_due_date'] === '2027-01-30', 'Category lead time determines the correct due month across year boundaries.');
    $none = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, ['period' => 'monthly', 'year' => 2025, 'month' => 1], $today);
    kpiAssert($none['totals']['needed'] === 0 && $none['totals']['fill_rate'] === null && $none['totals']['average_days'] === null, 'Empty periods use no-data values instead of misleading percentages.');
    $pdo->exec('SAVEPOINT table_controls');
    for ($i = 0; $i < 12; $i++) {
        $file = $request('TABLE-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), '2026-09-01', $i % 2 ? 'B' : 'A', 1, 1, $i % 4 === 0 ? 'Completed' : ($i % 2 ? 'Pending' : 'In Progress'))[0];
        $pdo->prepare('UPDATE manpower_requests SET position_title=? WHERE id=?')->execute([$i === 0 ? 'Zoology Assistant' : 'Accounting Assistant', $file]);
        if ($i % 4 === 0) {
            $hire($file, '2026-09-20');
        }
    }
    $base = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, $input, $today);
    $tableInput = $input + ['table_search' => 'KPI-TABLE'];
    $table = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, $tableInput + ['page' => 2], $today);
    kpiAssert($table['total'] === 12 && count($table['rows']) === 12 && $table['pages'] === 1 && $table['page'] === 1 && $table['per_page'] === 15, 'Table filters and pagination cover the full cohort before slicing.');
    kpiAssert($table['totals'] === $base['totals'] && $table['metrics'] === $base['metrics'], 'Table-only filters do not alter KPI totals or HR performance.');
    $unique = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, $input + ['table_search' => 'KPI-TABLE-11'], $today);
    kpiAssert($unique['total'] === 1 && str_contains($unique['rows'][0]['mrf_number'], 'KPI-TABLE-11'), 'Search can find a record outside the first unfiltered page.');
    $filled = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, $tableInput + ['table_filled' => 'filled', 'per_page' => 25], $today);
    kpiAssert($filled['total'] === 3 && $filled['totals'] === $base['totals'], 'Filled is a table filter only.');
    $category = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, $tableInput + ['table_category' => 'B', 'table_status' => 'Pending'], $today);
    kpiAssert($category['total'] === 6, 'Status/category table filters combine.');
    $large = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, $tableInput + ['per_page' => 50, 'page' => 999], $today);
    kpiAssert(count($large['rows']) === 12 && $large['page'] === 1 && $large['pages'] === 1, 'Fixed page size is enforced and out-of-range pages are clamped.');
    $emptyTable = \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, $input + ['table_search' => 'no matching headcount', 'page' => 999], $today);
    kpiAssert($emptyTable['total'] === 0 && $emptyTable['page'] === 1 && $emptyTable['totals'] === $base['totals'], 'No table matches never empties the KPI summary.');
    $chartFiltered = \App\Services\Recruitment\HrKpi::cifHrKpiCharts($pdo, $tableInput + ['table_filled' => 'unfilled'], $today);
    kpiAssert($chartFiltered['trend']['points'][9]['hires'] === $base['totals']['hires'], 'Chart data is not narrowed by table-only controls.');
    $pdo->exec('ROLLBACK TO SAVEPOINT table_controls');
    echo "PASS: table search across all pages, status/headcount/category filters, page sizes and clamping, and isolated table/card/chart scopes.\n";
    $pdo->exec('SAVEPOINT funnel_cases');
    $q = $pdo->prepare('INSERT INTO admins(email,name,password_hash) VALUES (?,?,?)');
    $q->execute(['funnel-owner', 'Funnel Owner', password_hash('123', PASSWORD_DEFAULT)]);
    $funnelOwner = (int) $pdo->lastInsertId();
    $funnelMrf = $request('FUNNEL', '2026-09-01', 'A', $funnelOwner)[0];
    $candidateIds = [];
    for ($i = 0; $i < 10; $i++) {
        $candidateIds[$i] = $hire($funnelMrf, '2026-10-10', $i === 5, $i === 5);
        $submitted = match ($i) {
            8 => '2026-09-30 15:59:59',
            9 => '2026-10-31 16:00:00',
            default => '2026-09-30 16:00:00',
        };
        $pdo->prepare('UPDATE candidates SET submitted_at=? WHERE id=?')->execute([$submitted, $candidateIds[$i]]);
        if ($i === 0 || $i >= 8) {
            continue;
        }
        $finish = $i !== 1;
        $decision = $i === 2 ? 'No Response' : ($i === 3 ? 'Failed' : 'Passed');
        $q = $pdo->prepare("INSERT INTO candidate_interview_stages(candidate_id,stage,decision,remarks,completed_at,revision,updated_by,updated_at) VALUES (?,'initial_interview',?,'',?,1,1,'2026-10-02 02:00:00')");
        $q->execute([$candidateIds[$i], $decision, $finish ? '2026-10-02 02:00:00' : null]);
        if ($i < 4) {
            continue;
        }
        $q = $pdo->prepare("INSERT INTO candidate_interview_stages(candidate_id,stage,decision,remarks,completed_at,revision,updated_by,updated_at) VALUES (?,'job_offer',?,'',?,1,1,'2026-10-04 02:00:00')");
        $q->execute([$candidateIds[$i], $i === 4 ? 'Declined' : 'Accepted', $i === 6 ? null : '2026-10-04 02:00:00']);
    }
    $funnel = \App\Services\Recruitment\HrKpi::cifHrKpiCharts($pdo, $input + ['staff_id' => $funnelOwner], $today)['funnel'];
    kpiAssert(array_column($funnel['stages'], 'count') === [8, 5, 3, 2, 1], 'Funnel counts distinct candidates, finished stages, accepted offers and completed hires; drafts/No Response and local date boundaries are handled.');
    kpiAssert($funnel['stages'][1]['percent_applied'] === 62.5 && $funnel['stages'][2]['percent_previous'] === 60.0 && $funnel['stages'][4]['percent_previous'] === 50.0, 'Both applicant-share and step-conversion percentages are exact.');
    $dayFunnel = \App\Services\Recruitment\HrKpi::cifHrKpiCharts($pdo, ['period' => 'weekly', 'date_from' => '2026-10-01', 'date_to' => '2026-10-01', 'staff_id' => $funnelOwner], $today)['funnel'];
    kpiAssert($dayFunnel['total'] === 8, 'Explicit single-date funnel includes its local midnight and excludes the previous local date.');
    $crossWeeks = \App\Services\Recruitment\HrKpi::cifHrKpiCharts($pdo, ['period' => 'weekly', 'date_from' => '2026-12-30', 'date_to' => '2027-01-06'], $today);
    kpiAssert(array_column($crossWeeks['trend']['points'], 'key') === ['2026-W53', '2027-W01'], 'Weekly chart keys correctly cross ISO years.');
    $pdo->prepare('UPDATE mrf_hr_assignments SET hr_admin_id=NULL WHERE request_id=?')->execute([$funnelMrf]);
    $emptyFunnel = \App\Services\Recruitment\HrKpi::cifHrKpiCharts($pdo, $input + ['staff_id' => $funnelOwner], $today)['funnel'];
    kpiAssert($emptyFunnel['total'] === 0 && $emptyFunnel['stages'][0]['percent_applied'] === null, 'Unassigned candidates are not credited to individual HR; empty funnels have no invented percentages.');
    $pdo->prepare('UPDATE mrf_hr_assignments SET hr_admin_id=? WHERE request_id=?')->execute([$funnelOwner, $funnelMrf]);
    // A candidate without deployment uses only the latest CIF revision's MRF, not any older assignment.
    $target = $candidateIds[0];
    $pdo->prepare('DELETE FROM candidate_deployments WHERE candidate_id=?')->execute([$target]);
    $q = $pdo->prepare("INSERT INTO candidate_admin_revisions(candidate_id,admin_id,form_data,hr_data) VALUES (?,1,'{}','{}')");
    $q->execute([$target]);
    $oldRevision = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO candidate_mrf_assignments(revision_id,candidate_id,request_id) VALUES (?,?,?)')->execute([$oldRevision, $target, $funnelMrf]);
    kpiAssert(\App\Services\Recruitment\HrKpi::cifHrKpiCharts($pdo, $input + ['staff_id' => $funnelOwner], $today)['funnel']['total'] === 8, 'Latest interview/screening MRF attributes a pre-deployment applicant.');
    $q->execute([$target]);
    $newRevision = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO candidate_mrf_assignments(revision_id,candidate_id,request_id) VALUES (?,?,NULL)')->execute([$newRevision, $target]);
    kpiAssert(\App\Services\Recruitment\HrKpi::cifHrKpiCharts($pdo, $input + ['staff_id' => $funnelOwner], $today)['funnel']['total'] === 7, 'Cleared latest MRF does not fall back to stale historical assignment.');
    kpiAssert(\App\Services\Recruitment\HrKpi::cifHrKpiCharts($pdo, ['period' => 'yearly', 'year' => 2026], $today)['trend']['points'][4]['hires'] === \App\Services\Recruitment\HrKpi::cifHrKpi($pdo, ['period' => 'yearly', 'year' => 2026], $today)['totals']['hires'], 'Yearly line values use exactly the same aggregation as yearly cards.');
    $pdo->exec('ROLLBACK TO SAVEPOINT funnel_cases');
    echo "PASS: monthly/yearly chart-card agreement, no-data gaps, unique sequential funnel counts, exact conversion percentages, completed/archived hires, UTC boundaries, current MRF ownership and cleared assignments.\n";
    echo "PASS: due-month/year cohorts, one-headcount targets, all four KPI formulas, owner attribution, archived hires, duplicate/invalid-date safeguards, continuous Hold/Resume and Cancel/Reinstate counting, optional/automatic ownership, no owner stealing, separate assignment revisions and history.\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
