<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Services\Recruitment\AccessLimiter;
use App\Services\Recruitment\CifSuperConflict;
use App\Services\Recruitment\RecruitmentDatabase;
use App\Services\Recruitment\Security;
use App\Services\Recruitment\Superadmin;
use Illuminate\Http\Exceptions\HttpResponseException;
use InvalidArgumentException;
use JsonException;
use PDO;
use Throwable;

final class SuperadminController extends Controller
{
    public function __invoke()
    {

        try {
            $action = request()->query()['action'] ?? 'state';
            $pdo = RecruitmentDatabase::pdo();
            if (request()->method() === 'GET' && $action === 'state') {
                return response()->json(['csrf' => session()->token(), 'superadmin' => session()->get('superadmin') ?? null]);
            }
            if (request()->method() !== 'POST') {
                return response()->json(['error' => 'Method not allowed.'], 405);
            }
            $input = json_decode(request()->getContent(), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($input)) {
                throw new InvalidArgumentException('Invalid request.');
            }
            if ($action === 'login') {
                AccessLimiter::check('superadmin');
                $login = strtolower(Superadmin::cifSuperText($input['login'] ?? '', 254));
                $password = $input['password'] ?? '';
                $q = $pdo->prepare("SELECT * FROM admins WHERE email=? AND role='superadmin' AND account_status='Active'");
                $q->execute([$login]);
                $account = $q->fetch(PDO::FETCH_ASSOC);
                if (! $account || ! is_string($password) || strlen($password) > 72 || ! password_verify($password, $account['password_hash'])) {
                    Security::cifAuditWrite($pdo, 'Access', 'Superadmin sign-in failed', 'Account', '', $login, [], 'Failed');

                    return response()->json(['error' => 'Incorrect sign-in details or inactive account.'], 401);
                }
                // Do not clear an independently authenticated HR or MRF Requestor role in another tab.
                session()->migrate(true);
                session()->put('superadmin', Security::cifAdminProfile($account));
                session()->put('superadmin_password_signature', hash('sha256', $account['password_hash']));
                session()->put('superadmin_access_version', (int) $account['access_version']);
                Security::cifAuditContext($pdo, 'Superadmin', (int) $account['id'], $account['name']);
                Security::cifAuditWrite($pdo, 'Access', 'Superadmin signed in', 'Account', (string) $account['id'], $account['email']);

                return response()->json(['superadmin' => session()->get('superadmin')]);
            }
            if (empty(session()->get('superadmin'))) {
                return response()->json(['error' => 'Sign in as Superadmin to continue.'], 401);
            }
            if ($action === 'logout') {
                Security::cifAuditWrite($pdo, 'Access', 'Superadmin signed out', 'Account', (string) session()->get('superadmin'.'.id'), session()->get('superadmin'.'.email'));
                session()->forget(['superadmin', 'superadmin_access_version', 'superadmin_password_signature']);
                session()->migrate(true);

                return response()->json(['ok' => true]);
            }

            if ($action === 'users') {
                return response()->json(Superadmin::cifSuperUsers($pdo, $input));
            }
            if ($action === 'save_user') {
                AccessLimiter::check('superadmin-save-'.session()->get('superadmin'.'.id'), 100);

                return response()->json(Superadmin::cifSuperSaveUser($pdo, $input, (int) session()->get('superadmin'.'.id')));
            }
            if ($action === 'audit') {
                return response()->json(Superadmin::cifSuperAudit($pdo, $input));
            }
            if ($action === 'overview') {
                $users = $pdo->query('SELECT role,account_status,COUNT(*) AS count FROM admins GROUP BY role,account_status')->fetchAll(PDO::FETCH_ASSOC);
                $settings = $pdo->query('SELECT audit_started,requestors_superadmin_only FROM security_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);

                return response()->json(['accounts' => $users, 'requestors' => (int) $pdo->query('SELECT COUNT(*) FROM department_users')->fetchColumn(), 'candidate_access' => (int) $pdo->query('SELECT COUNT(*) FROM invitations')->fetchColumn(), 'events' => (int) $pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn(), 'settings' => $settings]);
            }

            return response()->json(['error' => 'Action not available.'], 404);
        } catch (CifSuperConflict $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (JsonException $e) {
            return response()->json(['error' => 'Invalid request.'], 400);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }error_log('Superadmin: '.$e->getMessage());

            return response()->json(['error' => 'Unable to complete this action. Refresh or contact your administrator.'], 500);
        }

    }
}
