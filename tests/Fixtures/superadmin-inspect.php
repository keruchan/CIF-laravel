<?php

declare(strict_types=1);
use App\Services\Recruitment\AuditSetup;
use App\Services\Recruitment\HrStaff;
use App\Services\Recruitment\Security;
use App\Services\Recruitment\Superadmin;
use Illuminate\Contracts\Console\Kernel;

if (PHP_SAPI !== 'cli' || ! preg_match('/^cif_mrf_test_[a-f0-9]{16}$/D', $argv[1] ?? '')) {
    http_response_code(404);
    exit;
}

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$pdo = new PDO('mysql:host=127.0.0.1;dbname='.$argv[1].';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
if (($argv[2] ?? '') === 'prepare') {
    $pdo->exec("UPDATE department_users SET username=CONCAT(username,'@example.test')");
    echo 'Prepared';
    exit;
}
if (($argv[2] ?? '') === 'upgrade') {
    $name = 'cif_audit_v1_'.substr(hash('sha256', 'department_usersUPDATE'), 0, 24);
    $before = (int) $pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();
    $pdo->exec("CREATE OR REPLACE TRIGGER `$name` AFTER UPDATE ON department_users FOR EACH ROW SET @cif_old_trigger=1");
    $upgraded = AuditSetup::cifInstallAuditTriggers($pdo);
    if ($upgraded['installed'] !== 1 || $upgraded['missing']) {
        throw new RuntimeException('Existing trigger did not upgrade safely: '.json_encode($upgraded));
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn() !== $before) {
        throw new RuntimeException('Trigger upgrade changed historical entries.');
    }echo 'Trigger upgraded; audit history preserved';
    exit;
}
$before = (int) $pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();
$pdo->beginTransaction();
Security::cifAuditContext($pdo, 'System', null, 'Atomic audit test');
$pdo->exec("UPDATE department_users SET group_name='Transactional audit test' WHERE id=1");
if ((int) $pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn() !== $before + 1) {
    throw new RuntimeException('Account change was not audited atomically.');
}$pdo->rollBack();
if ((int) $pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn() !== $before) {
    throw new RuntimeException('Rolled-back change left a misleading audit entry.');
}
foreach (['UPDATE audit_events SET action=\'Changed\' WHERE id=(SELECT id FROM (SELECT MIN(id) id FROM audit_events) x)', 'DELETE FROM audit_events WHERE id=(SELECT id FROM (SELECT MIN(id) id FROM audit_events) x)'] as $sql) {
    try {
        $pdo->exec($sql);
        throw new RuntimeException('Audit record mutation was allowed.');
    } catch (PDOException $e) {
        if ($e->getCode() !== '45000') {
            throw $e;
        }
    }
}
foreach (['utf8mb4_general_ci', 'utf8mb4_unicode_ci'] as $collation) {
    $pdo->exec('SET collation_connection='.$collation);
    Superadmin::cifSuperUsers($pdo, ['search' => '', 'page' => 1]);
    Superadmin::cifSuperAudit($pdo, ['category' => 'Approvals']);
}
// Changing a current association must not relabel the MRF recorded by an earlier hiring event.
$record = $pdo->query("SELECT * FROM audit_events WHERE action='Deployment completed' AND mrf_id IS NOT NULL ORDER BY id DESC LIMIT 1")->fetch();
if (! $record) {
    throw new RuntimeException('Deployment references were not captured.');
}
$pdo->beginTransaction();
$pdo->prepare('UPDATE candidate_mrf_assignments SET request_id=NULL WHERE candidate_id=?')->execute([$record['candidate_id']]);
$events = Superadmin::cifSuperAudit($pdo, ['candidate_ref' => (string) $record['candidate_id'], 'mrf_ref' => (string) $record['mrf_id'], 'category' => 'Hiring'])['events'];
$matching = array_values(array_filter($events, fn ($event) => (int) $event['id'] === (int) $record['id']));
if (! $matching || (int) $matching[0]['mrf_id'] !== (int) $record['mrf_id']) {
    throw new RuntimeException('Historical MRF reference changed with current assignment.');
}$pdo->rollBack();
// Earlier rows can be searched through their saved IDs without modifying the original entry.
$pdo->beginTransaction();
$q = $pdo->prepare("INSERT INTO audit_events(created_at,actor_role,actor_name,category,action,entity_type,entity_id,entity_label,after_json,source) VALUES(UTC_TIMESTAMP(),'System','Legacy reference check','Hiring','Earlier history','Assessment',?,'Legacy reference check',?,'Earlier history')");
$q->execute([(string) $record['candidate_id'], json_encode(['candidate_id' => $record['candidate_id'], 'request_id' => $record['mrf_id']])]);
$legacyId = (int) $pdo->lastInsertId();
$legacy = Superadmin::cifSuperAudit($pdo, ['search' => 'Legacy reference check', 'candidate_ref' => $record['candidate_number'], 'mrf_ref' => (string) $record['mrf_id']]);
if ((int) ($legacy['events'][0]['id'] ?? 0) !== $legacyId) {
    throw new RuntimeException('Saved legacy IDs were not searchable.');
}$pdo->rollBack();
$all = $pdo->query('SELECT before_json,after_json FROM audit_events')->fetchAll();
$json = json_encode($all, JSON_THROW_ON_ERROR);

$hr = HrStaff::cifHrMonitoring($pdo, ['period' => 'all']);
$superIds = $pdo->query("SELECT id FROM admins WHERE role='superadmin'")->fetchAll(PDO::FETCH_COLUMN);
if (array_intersect(array_column($hr['staff'], 'id'), $superIds)) {
    throw new RuntimeException('Superadmin entered HR monitoring choices.');
}
foreach (['"password_hash"', '"code_hash"', '"access_code"', 'Test!234', 'NewHR!23', 'Super!23'] as $secret) {
    if (str_contains(str_replace('\\"', '"', $json), $secret)) {
        throw new RuntimeException('Secret entered audit data.');
    }
}
foreach (['SELECT password_hash FROM admins', 'SELECT password_hash FROM department_users', 'SELECT code_hash FROM invitations', 'SELECT access_code FROM invitations'] as $query) {
    foreach ($pdo->query($query)->fetchAll(PDO::FETCH_COLUMN) as $secret) {
        if (is_string($secret) && $secret !== '' && str_contains($json, $secret)) {
            throw new RuntimeException('Saved credentials entered audit data.');
        }
    }
}
echo json_encode(['events' => $before, 'triggers' => count($pdo->query('SHOW TRIGGERS')->fetchAll()), 'atomic_immutable_redacted' => true], JSON_THROW_ON_ERROR);
