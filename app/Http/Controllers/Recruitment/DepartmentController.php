<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Services\Recruitment\AccessLimiter;
use App\Services\Recruitment\DepartmentAccount;
use App\Services\Recruitment\MrfRequestors;
use App\Services\Recruitment\RecruitmentDatabase;
use App\Services\Recruitment\Security;
use App\Services\Recruitment\TextFormat;
use Illuminate\Http\Exceptions\HttpResponseException;
use InvalidArgumentException;
use JsonException;
use Throwable;

final class DepartmentController extends Controller
{
    public function __invoke()
    {

        try {
            $action = request()->query()['action'] ?? '';
            if (request()->method() === 'GET' && $action === 'state') {
                return response()->json(['csrf' => session()->token(), 'requestor' => session()->get('department') ?? null, 'department' => session()->get('department') ?? null]);
            }
            if (request()->method() !== 'POST') {
                return response()->json(['error' => 'Method not allowed.'], 405);
            }

            $input = json_decode(request()->getContent(), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($input)) {
                return response()->json(['error' => 'Invalid request.'], 400);
            }
            if ($action === 'login') {
                AccessLimiter::check('department');
                $emailInput = $input['email'] ?? $input['username'] ?? '';
                $username = strtolower(trim(is_string($emailInput) ? $emailInput : ''));
                if (strlen($username) > 150 || ! filter_var($username, FILTER_VALIDATE_EMAIL)) {
                    return response()->json(['error' => 'Enter a valid email address.'], 422);
                }
                $password = is_string($input['password'] ?? null) ? $input['password'] : '';

                $query = RecruitmentDatabase::pdo()->prepare('SELECT id, username, name, position, department_name, group_name, account_status, archived_at, access_version, password_hash FROM department_users WHERE username = ?');
                $query->execute([$username]);
                $account = $query->fetch();
                if (! $account || ! password_verify($password, $account['password_hash'])) {
                    Security::cifAuditWrite(RecruitmentDatabase::pdo(), 'Access', 'Requestor sign-in failed', 'Requestor', '', $username, [], 'Failed');

                    return response()->json(['error' => 'Incorrect email or password.'], 401);
                }
                if (! DepartmentAccount::cifRequestorCanSignIn($account)) {
                    return response()->json(['error' => 'This requestor account is suspended or archived. Contact HR to restore access.'], 403);
                }
                session()->migrate(true);
                session()->put('department', DepartmentAccount::departmentProfile($account));
                session()->put('department_password_signature', hash('sha256', $account['password_hash']));
                session()->put('department_access_version', (int) $account['access_version']);
                Security::cifAuditContext(RecruitmentDatabase::pdo(), 'MRF Requestor', (int) $account['id'], $account['name']);
                Security::cifAuditWrite(RecruitmentDatabase::pdo(), 'Access', 'Requestor signed in', 'Requestor', (string) $account['id'], $username);

                return response()->json(['requestor' => session()->get('department'), 'department' => session()->get('department')]);
            }
            if (empty(session()->get('department'))) {
                return response()->json(['error' => 'Sign in as an MRF requestor to continue.'], 401);
            }
            if ($action === 'logout') {
                Security::cifAuditWrite(RecruitmentDatabase::pdo(), 'Access', 'Requestor signed out', 'Requestor', (string) session()->get('department'.'.id'), session()->get('department'.'.username'));
                session()->forget(['department', 'department_password_signature', 'department_access_version']);
                session()->migrate(true);

                return response()->json(['ok' => true]);
            }
            if ($action === 'settings_save') {
                foreach (array_keys($input) as $key) {
                    if (! in_array($key, ['group_name', 'current_password', 'new_password', 'confirm_password'], true)) {
                        return response()->json(['error' => 'Only your group and password can be changed. Contact HR about your other account details.'], 422);
                    }
                }
                $group = is_string($input['group_name'] ?? null) ? TextFormat::cifSavedText(trim($input['group_name']), 'group_name') : '';
                $current = is_string($input['current_password'] ?? null) ? $input['current_password'] : '';
                $next = is_string($input['new_password'] ?? null) ? $input['new_password'] : '';
                $confirmation = is_string($input['confirm_password'] ?? null) ? $input['confirm_password'] : '';
                if ($group === '' || mb_strlen($group) > 150 || preg_match('/[\x00-\x1F]/', $group)) {
                    return response()->json(['error' => 'Enter a group of up to 150 characters.'], 422);
                }
                if ($current === '' || strlen($current) > 72) {
                    return response()->json(['error' => 'Enter your current password.'], 422);
                }
                if ($next !== '' || $confirmation !== '') {
                    if (mb_strlen($next) < 12 || strlen($next) > 72 || str_contains($next, "\0")) {
                        return response()->json(['error' => 'Use at least 12 characters and no more than 72 bytes for the new password.'], 422);
                    }
                    if (! hash_equals($next, $confirmation)) {
                        return response()->json(['error' => 'The new passwords do not match.'], 422);
                    }
                }
                AccessLimiter::check('requestor-settings-'.session()->get('department'.'.id'));
                $pdo = RecruitmentDatabase::pdo();
                $pdo->beginTransaction();
                $query = $pdo->prepare('SELECT * FROM department_users WHERE id = ? FOR UPDATE');
                $query->execute([session()->get('department'.'.id')]);
                $account = $query->fetch();
                if (! $account || ! DepartmentAccount::cifRequestorCanSignIn($account) || (int) $account['access_version'] !== (int) (session()->get('department_access_version') ?? 0)) {
                    $pdo->rollBack();

                    return response()->json(['error' => 'Your account access changed. Sign in again or contact HR.'], 401);
                }
                if (! password_verify($current, $account['password_hash'])) {
                    $pdo->rollBack();

                    return response()->json(['error' => 'Your current password is incorrect.'], 422);
                }
                if ($next !== '' && password_verify($next, $account['password_hash'])) {
                    $pdo->rollBack();

                    return response()->json(['error' => 'Choose a new password different from your current password.'], 422);
                }
                if ($next === '' && $group === $account['group_name']) {
                    $pdo->commit();

                    return response()->json(['requestor' => session()->get('department'), 'department' => session()->get('department'), 'message' => 'Your settings are already up to date.']);
                }
                $hash = $next !== '' ? password_hash($next, PASSWORD_DEFAULT) : $account['password_hash'];
                $query = $pdo->prepare('UPDATE department_users SET group_name = ?, password_hash = ?, revision=revision+1 WHERE id = ?');
                $query->execute([$group, $hash, session()->get('department'.'.id')]);
                MrfRequestors::cifMrfRequestorEvent($pdo, (int) $account['id'], null, 'Settings changed', ['previous_group' => $account['group_name'], 'group' => $group, 'password_changed' => $next !== '']);
                $pdo->commit();
                session()->put('department'.'.group_name', $group);
                session()->put('department_password_signature', hash('sha256', $hash));
                if ($next !== '') {
                    session()->migrate(true);
                }

                return response()->json(['requestor' => session()->get('department'), 'department' => session()->get('department'), 'message' => $next !== '' ? 'Settings updated. Other sessions have been signed out.' : 'Your group has been updated.']);
            }

            return response()->json(['error' => 'Unknown action.'], 404);
        } catch (InvalidArgumentException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return response()->json(['error' => $e->getMessage()], 422);
        } catch (JsonException $e) {
            return response()->json(['error' => 'Invalid request data.'], 400);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('CIF MRF requestor: '.$e->getMessage());

            return response()->json(['error' => 'Unable to complete the request. Please try again or contact HR.'], 500);
        }

    }
}
