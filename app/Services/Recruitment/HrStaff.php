<?php
declare(strict_types=1);

namespace App\Services\Recruitment;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use PDO;

final class HrStaff
{
    public static function cifHrStages(): array
    {
        return ['cif' => 'CIF screening', 'initial_interview' => 'HR interview', 'department_interview' => 'Department interview', 'lie_detection' => 'Background check', 'job_offer' => 'Job offer', 'deployment' => 'Deployment'];
    }

    public static function trackCifHrPhase(PDO $pdo, int $candidateId, string $stage, int $adminId, bool $complete, ?string $decision, ?string $previousCompletedAt = null): void
    {
        if (! $pdo->inTransaction() || ! isset(HrStaff::cifHrStages()[$stage])) {
            throw new LogicException('Phase tracking requires a valid stage and save transaction.');
        }
        $query = $pdo->prepare('INSERT INTO candidate_hr_activity (candidate_id, stage, action, admin_id, decision, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())');
        $query->execute([$candidateId, $stage, $complete ? 'finished' : 'saved', $adminId, $decision]);
        $query = $pdo->prepare('INSERT INTO candidate_hr_phases (candidate_id, stage, started_by, started_at, updated_by, updated_at, completed_by, completed_at, decision) VALUES (?, ?, ?, UTC_TIMESTAMP(), ?, UTC_TIMESTAMP(), ?, COALESCE(?, IF(?, UTC_TIMESTAMP(), NULL)), ?) ON DUPLICATE KEY UPDATE updated_by = VALUES(updated_by), updated_at = VALUES(updated_at), completed_by = IF(completed_at IS NULL, VALUES(completed_by), completed_by), completed_at = COALESCE(completed_at, VALUES(completed_at)), decision = VALUES(decision)');
        // Editing a historically finished phase must not invent a finisher or KPI credit.
        $query->execute([$candidateId, $stage, $adminId, $adminId, $complete && ! $previousCompletedAt ? $adminId : null, $previousCompletedAt, (int) $complete, $decision]);
    }

    public static function renderCifHrCredits(int $candidateId): void
    {
        $query = RecruitmentDatabase::pdo()->prepare('SELECT p.*, a.name AS starter, b.name AS editor, d.name AS completer FROM candidate_hr_phases p JOIN admins a ON a.id = p.started_by JOIN admins b ON b.id = p.updated_by LEFT JOIN admins d ON d.id = p.completed_by WHERE p.candidate_id = ?');
        $query->execute([$candidateId]);
        $credits = [];
        foreach ($query->fetchAll() as $row) {
            $credits[$row['stage']] = $row;
        }
        foreach (HrStaff::cifHrStages() as $stage => $label) {
            $credit = $credits[$stage] ?? null;
            ?><div class="hr-phase-credit hide" data-hr-credit="<?= $stage ?>" aria-label="<?= CandidateView::cifEscape($label) ?> accountability"><strong><?= CandidateView::cifEscape($label) ?></strong><?php if ($credit) { ?><span>Started by <b><?= CandidateView::cifEscape($credit['starter']) ?></b></span><span>Last saved by <b><?= CandidateView::cifEscape($credit['editor']) ?></b></span><span>First finished by <b><?= CandidateView::cifEscape($credit['completer'] ?? ($credit['completed_at'] ? 'Historical / unattributed' : 'Not finished')) ?></b></span><?php } else { ?><small>No tracked HR action yet. Your first successful save starts accountability tracking for this phase.</small><?php } ?></div><?php
        }
    }

    public static function cifHrMonitoring(PDO $pdo, array $input): array
    {
        $period = $input['period'] ?? '30';
        $stage = $input['stage'] ?? '';
        $state = $input['state'] ?? '';
        if (! is_string($period) || ! in_array($period, ['today', '7', '30', '90', 'all'], true) || ! is_string($stage) || ($stage !== '' && ! isset(HrStaff::cifHrStages()[$stage])) || ! is_string($state) || ! in_array($state, ['', 'open', 'finished'], true)) {
            throw new InvalidArgumentException('Choose valid monitoring filters.');
        }
        $staffId = $input['staff_id'] ?? 0;
        $page = $input['page'] ?? 1;
        $search = $input['search'] ?? '';
        if (! is_int($staffId) || $staffId < 0 || ! is_int($page) || $page < 1 || ! is_string($search) || mb_strlen($search) > 150) {
            throw new InvalidArgumentException('Invalid monitoring request.');
        }

        $staff = $pdo->query("SELECT a.id, a.name, a.email AS username, COALESCE(p.photo, '') AS photo FROM admins a LEFT JOIN hr_staff_profiles p ON p.admin_id = a.id WHERE a.role='hr' ORDER BY a.name, a.id")->fetchAll();
        foreach ($staff as &$person) {
            $person['id'] = (int) $person['id'];
        } unset($person);
        if ($staffId && ! in_array($staffId, array_column($staff, 'id'), true)) {
            throw new InvalidArgumentException('Choose an existing HR staff member.');
        }
        // Calendar-day windows are in the portal timezone; stored timestamps are UTC.
        $from = null;
        if ($period !== 'all') {
            $from = new DateTimeImmutable('today', new DateTimeZone('Asia/Shanghai'));
            if ($period !== 'today') {
                $from = $from->modify('-'.((int) $period - 1).' days');
            }
            $from = $from->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
        $supportedStages = implode(',', array_map([$pdo, 'quote'], array_keys(HrStaff::cifHrStages())));
        $conditions = ["p.stage IN ($supportedStages)"];
        $params = [];
        if ($stage !== '') {
            $conditions[] = 'p.stage = ?';
            $params[] = $stage;
        }
        if (trim($search) !== '') {
            $conditions[] = "CONCAT_WS(' ', c.candidate_number, c.first_name, c.middle_name, c.last_name, c.email) LIKE ? ESCAPE '!'";
            $params[] = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($search)).'%';
        }
        $where = implode(' AND ', $conditions);
        $query = $pdo->prepare('SELECT p.* FROM candidate_hr_phases p JOIN candidates c ON c.id = p.candidate_id WHERE '.$where);
        $query->execute($params);
        $phases = $query->fetchAll();
        $metrics = [];
        foreach ($staff as $person) {
            if ($staffId && $staffId !== $person['id']) {
                continue;
            }
            $metrics[$person['id']] = $person + ['finished' => 0, 'open' => 0, 'candidates' => [], 'turnaround_seconds' => 0, 'deployments' => 0, 'by_stage' => array_fill_keys(array_keys(HrStaff::cifHrStages()), 0)];
            unset($metrics[$person['id']]['photo']); // Send image data once, in the staff list.
        }
        foreach ($phases as $phase) {
            if (! $phase['completed_at'] && isset($metrics[$phase['started_by']])) {
                $metrics[$phase['started_by']]['open']++;
            }
            if ($phase['completed_at'] && ($from === null || $phase['completed_at'] >= $from) && isset($metrics[$phase['completed_by']])) {
                $metric = &$metrics[$phase['completed_by']];
                $metric['finished']++;
                $metric['by_stage'][$phase['stage']]++;
                $metric['candidates'][$phase['candidate_id']] = true;
                $metric['turnaround_seconds'] += max(0, strtotime($phase['completed_at'].' UTC') - strtotime($phase['started_at'].' UTC'));
                if ($phase['stage'] === 'deployment') {
                    $metric['deployments']++;
                } unset($metric);
            }
        }
        $totals = ['finished' => 0, 'open' => 0, 'deployments' => 0, 'turnaround_seconds' => 0];
        foreach ($metrics as &$metric) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] += $metric[$key];
            }
            $metric['candidates'] = count($metric['candidates']);
            $metric['average_seconds'] = $metric['finished'] ? (int) round($metric['turnaround_seconds'] / $metric['finished']) : null;
            unset($metric['turnaround_seconds']);
        } unset($metric);
        $totals['average_seconds'] = $totals['finished'] ? (int) round($totals['turnaround_seconds'] / $totals['finished']) : null;
        unset($totals['turnaround_seconds']);
        if ($staffId) {
            $conditions[] = '(p.started_by = ? OR p.updated_by = ? OR p.completed_by = ?)';
            array_push($params, $staffId, $staffId, $staffId);
        }
        if ($from !== null) {
            $conditions[] = '(p.updated_at >= ? OR p.completed_at >= ? OR p.completed_at IS NULL)';
            array_push($params, $from, $from);
        }
        if ($state !== '') {
            $conditions[] = 'p.completed_at IS '.($state === 'open' ? '' : 'NOT ').'NULL';
        }
        $where = implode(' AND ', $conditions);
        $query = $pdo->prepare('SELECT COUNT(*) FROM candidate_hr_phases p JOIN candidates c ON c.id = p.candidate_id WHERE '.$where);
        $query->execute($params);
        $total = (int) $query->fetchColumn();
        $perPage = 10;
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $query = $pdo->prepare("SELECT p.*, c.candidate_number, CONCAT_WS(' ', c.first_name, c.middle_name, c.last_name) AS candidate_name, c.archived_at, a.name AS starter, a.email AS starter_username, b.name AS editor, b.email AS editor_username, d.name AS completer, d.email AS completer_username FROM candidate_hr_phases p JOIN candidates c ON c.id = p.candidate_id JOIN admins a ON a.id = p.started_by JOIN admins b ON b.id = p.updated_by LEFT JOIN admins d ON d.id = p.completed_by WHERE $where ORDER BY p.updated_at DESC, p.candidate_id DESC, p.stage LIMIT $perPage OFFSET $offset");
        $query->execute($params);
        $rows = $query->fetchAll();
        // Recent actual actions preserve every edit and repeat Finish, not just KPI credit.
        $activityConditions = ["h.stage IN ($supportedStages)"];
        $activityParams = [];
        if ($staffId) {
            $activityConditions[] = 'h.admin_id = ?';
            $activityParams[] = $staffId;
        }
        if ($stage !== '') {
            $activityConditions[] = 'h.stage = ?';
            $activityParams[] = $stage;
        }
        if ($from !== null) {
            $activityConditions[] = 'h.created_at >= ?';
            $activityParams[] = $from;
        }
        if (trim($search) !== '') {
            $activityConditions[] = "CONCAT_WS(' ', c.candidate_number, c.first_name, c.middle_name, c.last_name, c.email) LIKE ? ESCAPE '!'";
            $activityParams[] = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($search)).'%';
        }
        $query = $pdo->prepare('SELECT h.id, h.candidate_id, c.candidate_number, h.stage, h.action, h.decision, h.created_at, a.name, a.email AS username FROM candidate_hr_activity h JOIN candidates c ON c.id = h.candidate_id JOIN admins a ON a.id = h.admin_id WHERE '.implode(' AND ', $activityConditions).' ORDER BY h.id DESC LIMIT 20');
        $query->execute($activityParams);

        return ['staff' => $staff, 'metrics' => array_values($metrics), 'totals' => $totals, 'rows' => $rows, 'activity' => $query->fetchAll(), 'page' => $page, 'pages' => $pages, 'total' => $total, 'tracking_since' => $pdo->query('SELECT started_at FROM hr_tracking_settings WHERE id = 1')->fetchColumn(), 'stages' => HrStaff::cifHrStages()];
    }
}
