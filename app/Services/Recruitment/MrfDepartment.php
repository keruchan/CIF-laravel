<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class MrfDepartment
{
    public static function departmentMrfCoverage(array $input): array
    {
        $field = $input['date_field'] ?? 'submitted';
        $columns = ['submitted' => 'DATE(DATE_ADD(m.created_at, INTERVAL 8 HOUR))', 'approved' => 'ap.date_approved', 'due' => 'ap.hiring_due_date'];
        $deadline = $input['deadline'] ?? '';
        if (! is_string($field) || ! isset($columns[$field]) || ! is_string($deadline) || ! in_array($deadline, ['', 'overdue', 'upcoming', 'outstanding'], true)) {
            throw new InvalidArgumentException('Choose valid date and deadline filters.');
        }
        $where = [];
        $params = [];
        $dates = [];
        foreach (['date_from', 'date_to'] as $key) {
            $value = $input[$key] ?? '';
            if (! is_string($value)) {
                throw new InvalidArgumentException('Choose valid coverage dates.');
            }
            if ($value !== '') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Manila'));
                if (! $date || $date->format('Y-m-d') !== $value || $value < '1900-01-01' || $value > '9998-12-31') {
                    throw new InvalidArgumentException('Choose valid coverage dates.');
                }
            }
            $dates[$key] = $value;
        }
        if (($dates['date_from'] === '') !== ($dates['date_to'] === '') || ($dates['date_from'] !== '' && $dates['date_from'] > $dates['date_to'])) {
            throw new InvalidArgumentException('Choose both coverage dates, with From on or before To.');
        }
        if ($dates['date_from'] !== '') {
            $where[] = $columns[$field].' >= ?';
            $params[] = $dates['date_from'];
            $where[] = $columns[$field].' <= ?';
            $params[] = $dates['date_to'];
        }
        if ($deadline !== '') {
            $where[] = "ap.decision = 'Approved' AND m.status NOT IN ('Completed','Rejected','Cancelled') AND ap.hiring_due_date IS NOT NULL";
            $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
            if ($deadline === 'overdue') {
                $where[] = 'ap.hiring_due_date < ?';
                $params[] = $today->format('Y-m-d');
            }
            if ($deadline === 'upcoming') {
                $where[] = 'ap.hiring_due_date BETWEEN ? AND ?';
                $params[] = $today->format('Y-m-d');
                $params[] = $today->modify('+7 days')->format('Y-m-d');
            }
        }

        return [$where, $params];
    }

    public static function departmentMrfStatus(array $counts): string
    {
        $total = array_sum($counts);
        foreach (['Pending', 'Completed', 'Rejected', 'On Hold', 'Cancelled'] as $status) {
            if ($total > 0 && ($counts[$status] ?? 0) === $total) {
                return $status;
            }
        }

        return 'In Progress';
    }

    public static function departmentMrfRecord(PDO $pdo, array $record): array
    {
        // Project the HR headcount records into the person's single request.
        // No HR row/status/source data is altered or deleted.
        $query = $pdo->prepare('SELECT id, headcount_index, status, hr_notes, updated_at FROM manpower_requests WHERE batch_number = ? AND department_user_id = ? ORDER BY headcount_index');
        $query->execute([$record['batch_number'], $record['department_user_id']]);
        $files = $query->fetchAll();
        if (! $files) {
            throw new RuntimeException('Manpower request has no headcount records.');
        }
        if ((int) $record['id'] !== (int) $files[0]['id']) {
            $record = Mrf::loadMrf($pdo, (int) $files[0]['id'], (int) $record['department_user_id']);
        }
        $counts = array_fill_keys(Mrf::mrfStatuses(), 0);
        $notes = [];
        foreach ($files as $file) {
            $counts[$file['status']]++;
            if (trim((string) $file['hr_notes']) !== '') {
                $notes[] = count($files) > 1 ? 'Headcount '.$file['headcount_index'].': '.$file['hr_notes'] : $file['hr_notes'];
            }
        }
        $record['id'] = $files[0]['id'];
        $record['mrf_number'] = $record['batch_number'];
        $record['updated_at'] = max(array_column($files, 'updated_at'));
        $record['status'] = MrfDepartment::departmentMrfStatus($counts);
        $record['status_counts'] = $counts;
        $record['hr_notes'] = implode("\n\n", $notes);
        $query = $pdo->prepare('SELECT h.previous_status, h.status, h.hr_notes, h.created_at, a.name AS admin_name, m.headcount_index FROM manpower_request_history h JOIN manpower_requests m ON m.id = h.request_id JOIN admins a ON a.id = h.admin_id WHERE m.batch_number = ? AND m.department_user_id = ? ORDER BY h.created_at DESC, h.id DESC');
        $query->execute([$record['batch_number'], $record['department_user_id']]);
        $record['history'] = $query->fetchAll();
        unset($record['files'], $record['headcount_index'], $record['hr_assignment'], $record['hr_staff_options']);

        return $record;
    }

    public static function departmentMrfListSource(int $owner): string
    {
        // $owner is an integer from the authenticated session, never request text.
        // Give the computed status an explicit collation: native prepared parameters
        // can otherwise conflict with CASE literals on hosting database connections.
        return "(SELECT r.id, r.batch_number AS mrf_number, r.batch_number, r.legacy_mrf_number, r.department_user_id,
        r.requestor_name, r.position_title, r.department_name, r.site_location, r.headcount, r.created_at, totals.updated_at,
        CASE WHEN totals.pending_count = totals.file_count THEN 'Pending'
             WHEN totals.completed_count = totals.file_count THEN 'Completed'
             WHEN totals.rejected_count = totals.file_count THEN 'Rejected'
             WHEN totals.hold_count = totals.file_count THEN 'On Hold'
             WHEN totals.cancelled_count = totals.file_count THEN 'Cancelled' ELSE 'In Progress' END COLLATE utf8mb4_unicode_ci AS status
        FROM manpower_requests r JOIN (
            SELECT batch_number, department_user_id, COUNT(*) AS file_count,
                SUM(status = 'Pending') AS pending_count, SUM(status = 'Completed') AS completed_count,
                SUM(status = 'Rejected') AS rejected_count, SUM(status = 'On Hold') AS hold_count,
                SUM(status = 'Cancelled') AS cancelled_count, MAX(updated_at) AS updated_at
            FROM manpower_requests WHERE department_user_id = $owner GROUP BY batch_number, department_user_id
        ) totals ON totals.batch_number = r.batch_number AND totals.department_user_id = r.department_user_id
        WHERE r.headcount_index = 1 AND r.department_user_id = $owner) m";
    }
}
