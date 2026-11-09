<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use InvalidArgumentException;
use LogicException;
use PDO;

final class MrfOwnership
{
    public static function cifMrfHrStaff(PDO $pdo): array
    {
        $staff = $pdo->query("SELECT id,name,email AS username FROM admins WHERE role='hr' AND account_status='Active' ORDER BY name,id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($staff as &$person) {
            $person['id'] = (int) $person['id'];
        } unset($person);

        return $staff;
    }

    public static function cifAssignMrfHr(PDO $pdo, int $requestId, ?int $hrId, int $actor, string $source, string $reason = '', ?int $expectedRevision = null, bool $onlyIfEmpty = false): bool
    {
        if (! $pdo->inTransaction()) {
            throw new LogicException('MRF assignment requires a transaction.');
        }
        if (! in_array($source, ['approval', 'manual', 'screening', 'started'], true)) {
            throw new InvalidArgumentException('Invalid assignment source.');
        }
        if ($hrId !== null) {
            $q = $pdo->prepare("SELECT id FROM admins WHERE id=? AND role='hr' AND account_status='Active'");
            $q->execute([$hrId]);
            if ($hrId < 1 || ! $q->fetchColumn()) {
                throw new InvalidArgumentException('Choose an existing HR member.');
            }
        }
        $q = $pdo->prepare('SELECT batch_number FROM manpower_requests WHERE id=? FOR UPDATE');
        $q->execute([$requestId]);
        $batch = $q->fetchColumn();
        if (! $batch) {
            throw new InvalidArgumentException('MRF not found.');
        }
        $q = $pdo->prepare('SELECT decision FROM manpower_request_approvals WHERE batch_number=? FOR UPDATE');
        $q->execute([$batch]);
        if ($q->fetchColumn() !== 'Approved') {
            throw new InvalidArgumentException('Only approved MRFs can be assigned to HR.');
        }
        $q = $pdo->prepare('SELECT hr_admin_id,revision FROM mrf_hr_assignments WHERE request_id=? FOR UPDATE');
        $q->execute([$requestId]);
        $previous = $q->fetch(PDO::FETCH_ASSOC);
        $previousId = $previous && $previous['hr_admin_id'] !== null ? (int) $previous['hr_admin_id'] : null;
        $revision = $previous ? (int) $previous['revision'] : 0;
        if ($onlyIfEmpty && $previousId !== null) {
            return false;
        }
        if ($expectedRevision !== null && $expectedRevision !== $revision) {
            throw new CifMrfAssignmentConflict('Another HR member changed this assignment. Refresh before saving.');
        }
        if ($previousId === $hrId) {
            return false;
        }
        $reason = TextFormat::cifProperText(trim($reason));
        if (mb_strlen($reason) > 2000 || str_contains($reason, "\0")) {
            throw new InvalidArgumentException('Use an assignment reason of up to 2,000 characters.');
        }
        if ($source === 'manual' && $previousId !== null && $reason === '') {
            throw new InvalidArgumentException('Provide a reason for changing the assigned HR member.');
        }
        $q = $pdo->prepare('INSERT INTO mrf_hr_assignments (request_id,hr_admin_id,revision,assigned_by,assigned_at,source) VALUES (?,?,?,?,UTC_TIMESTAMP(),?) ON DUPLICATE KEY UPDATE hr_admin_id=VALUES(hr_admin_id),revision=VALUES(revision),assigned_by=VALUES(assigned_by),assigned_at=VALUES(assigned_at),source=VALUES(source)');
        $q->execute([$requestId, $hrId, $revision + 1, $actor, $source]);
        $q = $pdo->prepare('INSERT INTO mrf_hr_assignment_history (request_id,previous_hr_id,hr_admin_id,admin_id,revision,source,reason,created_at) VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP())');
        $q->execute([$requestId, $previousId, $hrId, $actor, $revision + 1, $source, $reason]);

        return true;
    }

    public static function cifMrfHrAssignment(PDO $pdo, int $requestId): array
    {
        $q = $pdo->prepare('SELECT o.hr_admin_id,o.revision,o.assigned_at,o.source,a.name,b.name AS assigned_by FROM mrf_hr_assignments o LEFT JOIN admins a ON a.id=o.hr_admin_id JOIN admins b ON b.id=o.assigned_by WHERE o.request_id=?');
        $q->execute([$requestId]);
        $record = $q->fetch(PDO::FETCH_ASSOC) ?: ['hr_admin_id' => null, 'revision' => 0, 'assigned_at' => null, 'source' => null, 'name' => null, 'assigned_by' => null];
        $record['hr_admin_id'] = $record['hr_admin_id'] === null ? null : (int) $record['hr_admin_id'];
        $record['revision'] = (int) $record['revision'];
        $q = $pdo->prepare('SELECT h.*,a.name AS previous_name,b.name AS name,c.name AS actor FROM mrf_hr_assignment_history h LEFT JOIN admins a ON a.id=h.previous_hr_id LEFT JOIN admins b ON b.id=h.hr_admin_id JOIN admins c ON c.id=h.admin_id WHERE h.request_id=? ORDER BY h.id DESC');
        $q->execute([$requestId]);
        $record['history'] = $q->fetchAll(PDO::FETCH_ASSOC);

        return $record;
    }
}
