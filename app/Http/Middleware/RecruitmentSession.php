<?php

namespace App\Http\Middleware;

use App\Services\Recruitment\DepartmentAccount;
use App\Services\Recruitment\MrfImportStaging;
use App\Services\Recruitment\RecruitmentDatabase;
use App\Services\Recruitment\Security;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RecruitmentSession
{
    public function handle(Request $request, Closure $next): Response
    {
        // Directory URLs need their final slash for the original relative links.
        if ($request->isMethod('GET') && preg_match('~^/pages/(?:candidate|admin|portal|mrf-requestor|superadmin)$~D', $request->getPathInfo())) {
            return new RedirectResponse($request->getPathInfo().'/'.($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301);
        }
        $session = $request->session();
        foreach (['admin' => 'hr', 'superadmin' => 'superadmin'] as $key => $role) {
            if ($id = $session->get($key.'.id')) {
                $query = RecruitmentDatabase::pdo()->prepare('SELECT id, email, name, password_hash, role, account_status, access_version FROM admins WHERE id = ?');
                $query->execute([$id]);
                $account = $query->fetch();
                if (! $account || $account['role'] !== $role || $account['account_status'] !== 'Active'
                    || (int) $account['access_version'] !== (int) $session->get($key.'_access_version', 0)
                    || ! hash_equals(hash('sha256', $account['password_hash']), $session->get($key.'_password_signature', ''))) {
                    if ($key === 'admin') {
                        foreach ($session->get('mrf_imports', []) as $token => $stage) {
                            MrfImportStaging::discard($stage, $token);
                        }
                        $session->forget('mrf_imports');
                    }
                    $session->forget([$key, $key.'_password_signature', $key.'_access_version']);
                } else {
                    $session->put($key, Security::cifAdminProfile($account));
                }
            }
        }
        if ($id = $session->get('department.id')) {
            $query = RecruitmentDatabase::pdo()->prepare('SELECT * FROM department_users WHERE id = ?');
            $query->execute([$id]);
            $account = $query->fetch();
            if (! $account || ! DepartmentAccount::cifRequestorCanSignIn($account)
                || (int) $account['access_version'] !== (int) $session->get('department_access_version', 0)
                || ! hash_equals(hash('sha256', $account['password_hash']), $session->get('department_password_signature', ''))) {
                $session->forget(['department', 'department_access_version', 'department_password_signature']);
            } else {
                $session->put('department', DepartmentAccount::departmentProfile($account));
            }
        }

        if ($id = $session->get('invitation_id')) {
            $query = RecruitmentDatabase::pdo()->prepare('SELECT access_enabled,access_version FROM invitations WHERE id=?');
            $query->execute([$id]);
            $access = $query->fetch();
            if (! $access || ! $access['access_enabled'] || (int) $access['access_version'] !== (int) $session->get('candidate_access_version', 0)) {
                $session->forget(['invitation_id', 'candidate_until', 'candidate_code_hash', 'candidate_access_version']);
            }
        }
        // Connection-local trigger metadata is reset for every Laravel request.
        unset($GLOBALS['cif_audit_request'], $GLOBALS['cif_audit_targets'], $GLOBALS['cif_audit_failure_logged']);
        $audit = config('database.default') === 'mysql';
        if ($audit) {
            Security::cifAuditSessionContext(RecruitmentDatabase::pdo());
        }
        $response = $next($request);
        if ($audit && $response->getStatusCode() >= 400 && empty($GLOBALS['cif_audit_failure_logged'])) {
            try {
                $pdo = RecruitmentDatabase::pdo();
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                Security::cifAuditWrite($pdo, 'Access', 'Request declined', 'Request', (string) $request->query('action', ''), basename($request->path()).' / '.$request->query('action', ''), ['http_status' => $response->getStatusCode()], 'Failed');
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
