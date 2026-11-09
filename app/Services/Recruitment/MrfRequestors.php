<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use InvalidArgumentException;
use PDO;
use PDOException;
use Throwable;

final class MrfRequestors
{
    public static function cifMrfRequestorEvent(PDO $pdo, int $id, ?int $actor, string $action, array $details): void
    {
        $q = $pdo->prepare('INSERT INTO mrf_requestor_events(requestor_id,admin_id,action,details_json,created_at) VALUES (?,?,?,?,UTC_TIMESTAMP())');
        $q->execute([$id, $actor, $action, json_encode($details, JSON_THROW_ON_ERROR)]);
    }

    public static function cifMrfRequestorList(PDO $pdo, array $input): array
    {
        $search = $input['search'] ?? '';
        $page = $input['page'] ?? 1;
        $archived = $input['archived'] ?? false;
        if (! is_string($search) || mb_strlen($search) > 150 || ! is_int($page) || $page < 1 || ! is_bool($archived)) {
            throw new InvalidArgumentException('Use a search of up to 150 characters and a valid account view.');
        }
        $where = ' WHERE archived_at IS '.($archived ? 'NOT NULL' : 'NULL')." AND LOCATE(?, CONCAT_WS(' ',name,username,department_name,group_name,position,account_status)) > 0";
        $query = $pdo->prepare('SELECT COUNT(*) FROM department_users'.$where);
        $query->execute([trim($search)]);
        $total = (int) $query->fetchColumn();
        $pages = max(1, (int) ceil($total / 8));
        $page = min($page, $pages);
        $query = $pdo->prepare('SELECT id,name,username,department_name,group_name,position,account_status,archived_at,revision FROM department_users'.$where.' ORDER BY department_name,name,id LIMIT 8 OFFSET '.(($page - 1) * 8));
        $query->execute([trim($search)]);

        return ['requestors' => $query->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public static function cifCreateMrfRequestor(PDO $pdo, array $input, int $actor): array
    {
        $values = [];
        foreach (['name', 'department_name', 'group_name', 'position'] as $key) {
            $value = $input[$key] ?? null;
            if (! is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > 150 || preg_match('/[\x00-\x1F]/', $value)) {
                throw new InvalidArgumentException('Complete the name, position, department and group (up to 150 characters each).');
            }
            $values[$key] = TextFormat::cifSavedText(trim($value), $key);
        }
        $email = $input['email'] ?? null;
        if (! is_string($email) || mb_strlen($email) > 150 || ! filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address of up to 150 characters.');
        }
        $email = strtolower(trim($email));
        $password = 'Ap!'.bin2hex(random_bytes(7)).'9a';
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('INSERT INTO department_users(username,name,position,department_name,group_name,password_hash) VALUES (?,?,?,?,?,?)');
            $q->execute([$email, $values['name'], $values['position'], $values['department_name'], $values['group_name'], password_hash($password, PASSWORD_DEFAULT)]);
            $id = (int) $pdo->lastInsertId();
            MrfRequestors::cifMrfRequestorEvent($pdo, $id, $actor, 'Registered', ['email' => $email] + $values);
            $pdo->commit();

            return ['id' => $id, 'username' => $email, 'default_password' => $password, 'message' => 'Requestor registered as Active. Share the sign-in details securely.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof PDOException && ($e->errorInfo[1] ?? null) === 1062) {
                throw new InvalidArgumentException('This email already has an account. Check the current and archived requestors.');
            }
            throw $e;
        }
    }

    public static function cifEditMrfRequestor(PDO $pdo, array $input, int $actor): array
    {
        $id = $input['id'] ?? null;
        $revision = $input['revision'] ?? null;
        $adminPassword = $input['admin_password'] ?? '';
        $reset = $input['reset_password'] ?? false;
        if (! is_int($id) || $id < 1 || ! is_int($revision) || $revision < 0 || ! is_bool($reset)) {
            throw new InvalidArgumentException('Choose a requestor to edit and refresh the list before trying again.');
        }
        if (! is_string($adminPassword) || $adminPassword === '' || strlen($adminPassword) > 72) {
            throw new InvalidArgumentException('Enter your own HR account password to save changes.');
        }
        $values = [];
        foreach (['name', 'position', 'department_name', 'group_name'] as $key) {
            $value = $input[$key] ?? null;
            if (! is_string($value) || mb_strlen(trim($value)) > 150 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
                throw new InvalidArgumentException('Use account details of up to 150 characters each.');
            }
            $values[$key] = TextFormat::cifSavedText(trim($value), $key);
            if (mb_strlen($values[$key]) > 150) {
                throw new InvalidArgumentException('An account detail exceeds 150 characters after formatting.');
            }
        }
        $username = $input['username'] ?? null;
        if (! is_string($username) || mb_strlen(trim($username)) > 150 || ! filter_var(trim($username), FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address of up to 150 characters.');
        }
        $values['username'] = strtolower(trim($username));
        $newPassword = $input['new_password'] ?? '';
        $confirmation = $input['confirm_password'] ?? '';
        if (! is_string($newPassword) || ! is_string($confirmation)) {
            throw new InvalidArgumentException('Enter valid new-password fields.');
        }
        if ($reset) {
            if (mb_strlen($newPassword) < 8) {
                throw new InvalidArgumentException('Choose a password with at least 8 characters.');
            }
            if (strlen($newPassword) > 72 || str_contains($newPassword, "\0")) {
                throw new InvalidArgumentException('This password is too long or contains unsupported characters. Please choose another.');
            }
            if (! hash_equals($newPassword, $confirmation)) {
                throw new InvalidArgumentException('The requestor\'s new passwords do not match.');
            }
        } elseif ($newPassword !== '' || $confirmation !== '') {
            throw new InvalidArgumentException('Select Reset requestor password before entering a new password.');
        }
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('SELECT * FROM department_users WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            $account = $q->fetch(PDO::FETCH_ASSOC);
            if (! $account) {
                $pdo->rollBack();

                return ['code' => 404, 'error' => 'Requestor not found.'];
            }
            $q = $pdo->prepare('SELECT password_hash FROM admins WHERE id=? FOR UPDATE');
            $q->execute([$actor]);
            $hash = $q->fetchColumn();
            if (! $hash || ! password_verify($adminPassword, $hash)) {
                throw new InvalidArgumentException('Your HR account password is incorrect. No changes were saved.');
            }
            if ((int) $account['revision'] !== $revision) {
                $pdo->rollBack();

                return ['code' => 409, 'error' => 'This account changed. Close this window and refresh the list before editing again.'];
            }
            foreach (['name', 'position', 'department_name', 'group_name'] as $key) {
                // Password-only resets must also work for older, incomplete profiles.
                if ($values[$key] === '' && ($key === 'name' || trim((string) $account[$key]) !== '')) {
                    throw new InvalidArgumentException('Complete the name, position, department and group. Existing blank details may be left unchanged.');
                }
            }
            if ($reset && password_verify($newPassword, $account['password_hash'])) {
                throw new InvalidArgumentException('Choose a new password different from the requestor\'s current password.');
            }
            $changes = [];
            foreach ($values as $key => $value) {
                if ($value !== $account[$key]) {
                    $changes[$key] = ['previous' => $account[$key], 'value' => $value];
                }
            }
            if (! $changes && ! $reset) {
                $pdo->commit();

                return ['code' => 200, 'message' => 'This requestor\'s details are already up to date.'];
            }
            $passwordHash = $reset ? password_hash($newPassword, PASSWORD_DEFAULT) : $account['password_hash'];
            $q = $pdo->prepare('UPDATE department_users SET name=?,position=?,department_name=?,group_name=?,username=?,password_hash=?,access_version=access_version+1,revision=revision+1 WHERE id=?');
            $q->execute([$values['name'], $values['position'], $values['department_name'], $values['group_name'], $values['username'], $passwordHash, $id]);
            MrfRequestors::cifMrfRequestorEvent($pdo, $id, $actor, 'Account edited', ['changes' => $changes, 'password_reset' => $reset]);
            $pdo->commit();

            return ['code' => 200, 'message' => $reset ? 'Requestor updated and password reset. Share the new password securely. They must sign in again.' : 'Requestor details updated. They must sign in again.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof PDOException && ($e->errorInfo[1] ?? null) === 1062) {
                throw new InvalidArgumentException('This email already belongs to another account. Check current and archived requestors.');
            }
            throw $e;
        }
    }

    public static function cifManageMrfRequestor(PDO $pdo, array $input, int $actor): array
    {
        $id = $input['id'] ?? null;
        $revision = $input['revision'] ?? null;
        $action = $input['action'] ?? '';
        if (! is_int($id) || $id < 1 || ! is_int($revision) || $revision < 0 || ! in_array($action, ['suspend', 'reactivate', 'archive', 'restore'], true)) {
            throw new InvalidArgumentException('Choose an available requestor action. Accounts cannot be deleted.');
        }
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('SELECT account_status,archived_at,revision FROM department_users WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            $account = $q->fetch(PDO::FETCH_ASSOC);
            if (! $account) {
                throw new InvalidArgumentException('Requestor not found.');
            }
            if ((int) $account['revision'] !== $revision) {
                $pdo->rollBack();

                return ['code' => 409, 'error' => 'This account changed. Refresh the list before trying again.'];
            }
            $archived = ! empty($account['archived_at']);
            if (($archived && $action !== 'restore') || (! $archived && $action === 'restore') || ($action === 'suspend' && $account['account_status'] !== 'Active') || ($action === 'reactivate' && $account['account_status'] !== 'Suspended')) {
                throw new InvalidArgumentException('This action is no longer available. Refresh the list.');
            }
            $status = match ($action) {
                'suspend' => 'Suspended','reactivate' => 'Active',default => $account['account_status']
            };
            $archiveSql = $action === 'archive' ? 'UTC_TIMESTAMP()' : ($action === 'restore' ? 'NULL' : 'archived_at');
            $q = $pdo->prepare("UPDATE department_users SET account_status=?,archived_at=$archiveSql,access_version=access_version+1,revision=revision+1 WHERE id=?");
            $q->execute([$status, $id]);
            MrfRequestors::cifMrfRequestorEvent($pdo, $id, $actor, ucfirst($action), ['previous_status' => $account['account_status'], 'previously_archived' => $archived, 'status' => $status]);
            $pdo->commit();

            return ['code' => 200, 'message' => match ($action) {
                'suspend' => 'Requestor suspended. Sign-in and existing sessions are blocked.','reactivate' => 'Requestor reactivated. They can sign in again.','archive' => 'Requestor archived. Access is blocked; existing MRFs are preserved.',default => 'Requestor restored. Account status: '.$status.'.'
            }];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            } throw $e;
        }
    }
}
