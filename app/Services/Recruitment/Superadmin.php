<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PDOException;
use Throwable;

final class Superadmin
{
    public static function cifSuperText(mixed $value, int $length = 150, bool $required = true, bool $multiline = false): string
    {
        if (! is_string($value) || mb_strlen(trim($value)) > $length || preg_match($multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/', $value) || ($required && trim($value) === '')) {
            throw new InvalidArgumentException('Complete the required details using valid text.');
        }

        return trim($value);
    }

    public static function cifSuperPassword(array $input, string $role): ?string
    {
        $password = $input['new_password'] ?? '';
        $confirmation = $input['confirm_password'] ?? '';
        if (! is_string($password) || ! is_string($confirmation)) {
            throw new InvalidArgumentException('Enter valid password fields.');
        }
        if ($password === '' && $confirmation === '') {
            return null;
        }
        $minimum = 8;
        if (mb_strlen($password) < $minimum || strlen($password) > 72 || str_contains($password, "\0") || ! hash_equals($password, $confirmation)) {
            throw new InvalidArgumentException('Use at least '.$minimum.' characters and enter the same password twice.');
        }

        return $password;
    }

    public static function cifSuperConfirm(PDO $pdo, array $input, int $actor): string
    {
        // Lock every active Superadmin in a stable order before account changes.
        $pdo->query("SELECT id FROM admins WHERE role='superadmin' AND account_status='Active' ORDER BY id FOR UPDATE")->fetchAll();
        $q = $pdo->prepare("SELECT password_hash,access_version FROM admins WHERE id=? AND role='superadmin' AND account_status='Active' FOR UPDATE");
        $q->execute([$actor]);
        $account = $q->fetch(PDO::FETCH_ASSOC);
        $password = $input['current_password'] ?? '';
        if (! $account || ! is_string($password) || strlen($password) > 72 || ! password_verify($password, $account['password_hash']) || (int) $account['access_version'] !== (int) (session()->get('superadmin_access_version') ?? -1)) {
            throw new InvalidArgumentException('Your Superadmin password is incorrect or your session changed. Sign in again if needed.');
        }
        $reason = Superadmin::cifSuperText($input['reason'] ?? '', 2000, true, true);
        $q = $pdo->prepare('SET @cif_reason=?');
        $q->execute([$reason]);

        return $reason;
    }

    public static function cifSuperUsers(PDO $pdo, array $input): array
    {
        $search = Superadmin::cifSuperText($input['search'] ?? '', 150, false);
        $role = $input['role'] ?? '';
        $status = $input['status'] ?? '';
        $page = $input['page'] ?? 1;
        if ($role === 'Department') {
            $role = 'MRF Requestor';
        } // Accept older client filters during the rename.
        if (! in_array($role, ['', 'HR', 'Superadmin', 'MRF Requestor', 'Candidate'], true) || ! in_array($status, ['', 'Active', 'Inactive', 'Archived'], true) || ! is_int($page) || $page < 1) {
            throw new InvalidArgumentException('Choose valid user filters.');
        }
        $s = static fn (string $expr): string => 'CONVERT('.$expr.' USING utf8mb4) COLLATE utf8mb4_unicode_ci';
        $union = "SELECT 'admin' AS kind,id,".$s('name').' AS name,'.$s('email').' AS login,'.$s("IF(role='superadmin','Superadmin','HR')").' AS role,'.$s("IF(account_status='Active','Active','Inactive')").' AS status,revision,'.$s("''").' AS position,'.$s("''").' AS department_name,'.$s("''")." AS group_name FROM admins
        UNION ALL SELECT 'requestor',id,".$s('name').','.$s('username').','.$s("'MRF Requestor'").','.$s("IF(archived_at IS NOT NULL,'Archived',IF(account_status='Active','Active','Inactive'))").',revision,'.$s('position').','.$s('department_name').','.$s('group_name')." FROM department_users
        UNION ALL SELECT 'candidate',i.id,".$s("COALESCE(CONCAT(c.first_name,' ',c.last_name),NULLIF(CONCAT_WS(' ',i.first_name,i.last_name),''),i.email)").','.$s('i.email').','.$s("'Candidate'").','.$s("IF(i.access_enabled=1,'Active','Inactive')").',i.access_version,'.$s('COALESCE(i.position,\'\')').','.$s("''").','.$s("COALESCE(i.applicant_id,CONCAT('Invitation #',i.id))").' FROM invitations i LEFT JOIN candidates c ON c.invitation_id=i.id';
        $where = ['LOCATE(?,CONCAT_WS(\' \',name,login,department_name,group_name))>0'];
        $params = [$search];
        if ($role !== '') {
            $where[] = 'role=?';
            $params[] = $role;
        }if ($status !== '') {
            $where[] = 'status=?';
            $params[] = $status;
        }
        $from = ' FROM ('.$union.') users WHERE '.implode(' AND ', $where);
        $q = $pdo->prepare('SELECT COUNT(*)'.$from);
        $q->execute($params);
        $total = (int) $q->fetchColumn();
        $pages = max(1, (int) ceil($total / 12));
        $page = min($page, $pages);
        $q = $pdo->prepare('SELECT *'.$from.' ORDER BY role,name,id LIMIT 12 OFFSET '.(($page - 1) * 12));
        $q->execute($params);

        return ['users' => $q->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public static function cifSuperSaveUser(PDO $pdo, array $input, int $actor): array
    {
        $operation = $input['operation'] ?? '';
        $kind = $input['kind'] ?? '';
        $id = $input['id'] ?? 0;
        $revision = $input['revision'] ?? 0;
        if ($kind === 'department') {
            $kind = 'requestor';
        }
        if (! in_array($operation, ['create', 'edit', 'activate', 'deactivate', 'reset_code'], true) || ! in_array($kind, ['admin', 'requestor', 'candidate'], true) || ! is_int($id) || ! is_int($revision) || $revision < 0 || ($operation !== 'create' && $id < 1)) {
            throw new InvalidArgumentException('Choose an available account action.');
        }
        $pdo->beginTransaction();
        try {
            Superadmin::cifSuperConfirm($pdo, $input, $actor);
            $table = ['admin' => 'admins', 'requestor' => 'department_users', 'candidate' => 'invitations'][$kind];
            $account = null;
            if ($operation !== 'create') {
                $q = $pdo->prepare("SELECT * FROM `$table` WHERE id=? FOR UPDATE");
                $q->execute([$id]);
                $account = $q->fetch(PDO::FETCH_ASSOC);
                if (! $account) {
                    throw new InvalidArgumentException('Account not found.');
                }$version = $kind === 'candidate' ? 'access_version' : 'revision';
                if ((int) $account[$version] !== $revision) {
                    throw new CifSuperConflict('This account changed. Refresh the list before trying again.');
                }
            }
            if ($kind === 'candidate') {
                if (! in_array($operation, ['activate', 'deactivate', 'reset_code'], true)) {
                    throw new InvalidArgumentException('Candidate access is created through HR invitations. Use access controls here.');
                }
                if ($operation === 'reset_code') {
                    $code = ApplicantAccess::generateCifInvitationCode($pdo);
                    $q = $pdo->prepare('UPDATE invitations SET code_hash=?,access_code=?,access_version=access_version+1 WHERE id=?');
                    $q->execute([hash('sha256', $code), $code, $id]);
                    // Preserve deadline, submission, saved draft, applicant ID and access-enabled state.
                    $pdo->commit();

                    return ['message' => 'Access code reset. Share the new code securely. Existing sessions are signed out.', 'access_code' => substr($code, 0, 4).'-'.substr($code, 4)];
                }
                $q = $pdo->prepare('UPDATE invitations SET access_enabled=?,access_version=access_version+1 WHERE id=?');
                $q->execute([(int) ($operation === 'activate'), $id]);
            } else {
                $role = $kind === 'requestor' ? 'MRF Requestor' : (($account['role'] ?? ($input['role'] ?? 'hr')) === 'superadmin' ? 'Superadmin' : 'HR');
                if ($operation === 'create' || $operation === 'edit') {
                    $name = Superadmin::cifSuperText(TextFormat::cifSavedText(Superadmin::cifSuperText($input['name'] ?? ''), 'name'));
                    $login = strtolower(Superadmin::cifSuperText($input['login'] ?? '', $kind === 'requestor' ? 150 : 254));
                    if (($kind === 'requestor' && ! filter_var($login, FILTER_VALIDATE_EMAIL)) || ($kind === 'admin' && ! preg_match('/^[a-z0-9_.@+\-]{3,254}$/D', $login))) {
                        throw new InvalidArgumentException('Use a valid sign-in name. MRF requestor accounts require an email address.');
                    }
                    $password = Superadmin::cifSuperPassword($input, $role);
                    if ($operation === 'create' && $password === null) {
                        throw new InvalidArgumentException('Set a password for the new account.');
                    }
                    if ($operation === 'create' && $kind === 'admin' && ! in_array($input['role'] ?? 'hr', ['hr', 'superadmin'], true)) {
                        throw new InvalidArgumentException('Choose HR or Superadmin.');
                    }
                    if ($kind === 'requestor') {
                        $details = [];
                        foreach (['position', 'department_name', 'group_name'] as $field) {
                            $details[$field] = Superadmin::cifSuperText(TextFormat::cifSavedText(Superadmin::cifSuperText($input[$field] ?? '', 150, $operation === 'create'), $field), 150, $operation === 'create');
                        }
                        [$position,$department,$group] = [$details['position'], $details['department_name'], $details['group_name']];
                        if ($operation === 'create') {
                            $q = $pdo->prepare('INSERT INTO department_users(username,name,position,department_name,group_name,password_hash) VALUES(?,?,?,?,?,?)');
                            $q->execute([$login, $name, $position, $department, $group, password_hash($password, PASSWORD_DEFAULT)]);
                        } else {
                            $q = $pdo->prepare('UPDATE department_users SET username=?,name=?,position=?,department_name=?,group_name=?,password_hash=?,access_version=access_version+1,revision=revision+1 WHERE id=?');
                            $q->execute([$login, $name, $position, $department, $group, $password !== null ? password_hash($password, PASSWORD_DEFAULT) : $account['password_hash'], $id]);
                        }
                    } elseif ($operation === 'create') {
                        $q = $pdo->prepare('INSERT INTO admins(email,name,role,password_hash) VALUES(?,?,?,?)');
                        $q->execute([$login, $name, $input['role'] ?? 'hr', password_hash($password, PASSWORD_DEFAULT)]);
                    } else {
                        $q = $pdo->prepare('UPDATE admins SET email=?,name=?,password_hash=?,access_version=access_version+1,revision=revision+1 WHERE id=?');
                        $q->execute([$login, $name, $password !== null ? password_hash($password, PASSWORD_DEFAULT) : $account['password_hash'], $id]);
                    }
                    if ($operation === 'create') {
                        $id = (int) $pdo->lastInsertId();
                    }
                } elseif (in_array($operation, ['activate', 'deactivate'], true)) {
                    if ($kind === 'admin' && $id === $actor && $operation === 'deactivate') {
                        throw new InvalidArgumentException('You cannot deactivate your own Superadmin account.');
                    }
                    if ($kind === 'admin' && $role === 'Superadmin' && $operation === 'deactivate' && (int) $pdo->query("SELECT COUNT(*) FROM admins WHERE role='superadmin' AND account_status='Active'")->fetchColumn() <= 1) {
                        throw new InvalidArgumentException('Keep at least one active Superadmin.');
                    }
                    $status = $operation === 'activate' ? 'Active' : 'Suspended';
                    $archive = $kind === 'requestor' && $operation === 'activate' ? ',archived_at=NULL' : '';
                    $q = $pdo->prepare("UPDATE `$table` SET account_status=?,access_version=access_version+1,revision=revision+1$archive WHERE id=?");
                    $q->execute([$status, $id]);
                } else {
                    throw new InvalidArgumentException('This account does not use invitation codes.');
                }
            }
            if ($kind === 'admin' && $id === $actor) {
                $q = $pdo->prepare('SELECT * FROM admins WHERE id=?');
                $q->execute([$id]);
                $me = $q->fetch(PDO::FETCH_ASSOC);
                session()->put('superadmin', Security::cifAdminProfile($me));
                session()->put('superadmin_password_signature', hash('sha256', $me['password_hash']));
                session()->put('superadmin_access_version', (int) $me['access_version']);
            }
            $pdo->commit();
            if ($kind === 'admin' && $id === $actor) {
                session()->migrate(true);
            }

            return ['message' => $operation === 'create' ? 'Account created. Share its sign-in details securely.' : 'Account updated. Previous sessions must sign in again.', 'superadmin' => session()->get('superadmin')];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }if ($e instanceof PDOException && ($e->errorInfo[1] ?? 0) === 1062) {
                throw new InvalidArgumentException('That sign-in name already belongs to an account.');
            }throw $e;
        }
    }

    public static function cifSuperAudit(PDO $pdo, array $input): array
    {
        $search = Superadmin::cifSuperText($input['search'] ?? '', 150, false);
        $category = $input['category'] ?? '';
        $outcome = $input['outcome'] ?? '';
        $page = $input['page'] ?? 1;
        $categories = ['Accounts', 'Access', 'Approvals', 'MRF', 'Assignments', 'Candidates', 'Hiring', 'Invitations', 'Email', 'Reports'];
        if (! in_array($category, ['', ...$categories], true) || ! in_array($outcome, ['', 'Success', 'Failed'], true) || ! is_int($page) || $page < 1) {
            throw new InvalidArgumentException('Choose valid audit filters.');
        }
        $where = [];
        $params = [];
        if ($search !== '') {
            $match = "LOCATE(?,CONVERT(CONCAT_WS(' ',actor_name,IF(actor_role='Department','MRF Requestor',actor_role),category,action,outcome,entity_label,entity_type,entity_id,mrf_id,previous_mrf_id,mrf_number,mrf_batch_number,candidate_id,candidate_number,invitation_id,request_id) USING utf8mb4) COLLATE utf8mb4_unicode_ci)>0";
            $params[] = $search;
            // A headcount file shares the approval of its original request, even when its own number differs.
            $lookup = 'm.mrf_number=?';
            $lookupParams = [$search];
            if (ctype_digit($search)) {
                $lookup .= ' OR m.id=?';
                $lookupParams[] = $search;
            }
            $match .= " OR (entity_type='Management decision' AND mrf_batch_number COLLATE utf8mb4_unicode_ci IN (SELECT CONVERT(m.batch_number USING utf8mb4) COLLATE utf8mb4_unicode_ci FROM manpower_requests m WHERE $lookup))";
            $where[] = '('.$match.')';
            array_push($params, ...$lookupParams);
        }
        foreach (['mrf_ref', 'candidate_ref'] as $field) {
            $value = Superadmin::cifSuperText($input[$field] ?? '', 100, false);
            if ($value === '') {
                continue;
            }
            if ($field === 'candidate_ref') {
                $where[] = '(candidate_id=? OR candidate_number=?)';
                array_push($params, $value, $value);
            } else {
                $match = 'mrf_id=? OR previous_mrf_id=? OR mrf_number=? OR mrf_batch_number=?';
                array_push($params, $value, $value, $value, $value);
                if (ctype_digit($value)) {
                    $match .= " OR (entity_type='Management decision' AND mrf_batch_number COLLATE utf8mb4_unicode_ci=(SELECT CONVERT(m.batch_number USING utf8mb4) COLLATE utf8mb4_unicode_ci FROM manpower_requests m WHERE m.id=?))";
                    $params[] = $value;
                }
                $where[] = '('.$match.')';
            }
        }
        if ($category !== '') {
            $where[] = 'category=?';
            $params[] = $category;
        }if ($outcome !== '') {
            $where[] = 'outcome=?';
            $params[] = $outcome;
        }
        foreach (['date_from' => '>=', 'date_to' => '<'] as $field => $operator) {
            $value = $input[$field] ?? '';
            if ($value === '') {
                continue;
            }if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) || $value < '1900-01-01' || $value > '9998-12-31') {
                throw new InvalidArgumentException('Choose valid audit dates.');
            }$date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Manila'));
            if (! $date || $date->format('Y-m-d') !== $value) {
                throw new InvalidArgumentException('Choose valid audit dates.');
            }if ($field === 'date_to') {
                $date = $date->modify('+1 day');
            }$where[] = 'created_at '.$operator.' ?';
            $params[] = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
        if (($input['date_from'] ?? '') !== '' && ($input['date_to'] ?? '') !== '' && $input['date_from'] > $input['date_to']) {
            throw new InvalidArgumentException('From must be on or before To.');
        }
        $where = $where ? ' WHERE '.implode(' AND ', $where) : '';
        $from = ' FROM '.AuditReferences::cifAuditReadSource();
        $q = $pdo->prepare('SELECT COUNT(*)'.$from.$where);
        $q->execute($params);
        $total = (int) $q->fetchColumn();
        $pages = max(1, (int) ceil($total / 20));
        $page = min($page, $pages);
        $q = $pdo->prepare('SELECT *'.$from.$where.' ORDER BY created_at DESC,id DESC LIMIT 20 OFFSET '.(($page - 1) * 20));
        $q->execute($params);
        $events = $q->fetchAll(PDO::FETCH_ASSOC);
        foreach ($events as &$event) {
            if ($event['actor_role'] === 'Department') {
                $event['actor_role'] = 'MRF Requestor';
            }
        }
        unset($event);

        return ['events' => $events, 'total' => $total, 'page' => $page, 'pages' => $pages, 'categories' => $categories];
    }
}
