<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Services\Recruitment\AccessLimiter;
use App\Services\Recruitment\MrfRequestors;
use App\Services\Recruitment\RecruitmentDatabase;
use App\Services\Recruitment\Security;
use Illuminate\Http\Exceptions\HttpResponseException;

class MrfRequestorsController extends Controller
{
    public function __invoke()
    {
        try {
            if (empty(session()->get('admin'))) {
                return response()->json(['error' => 'Sign in as HR to view MRF requestors.'], 401);
            }
            if (request()->method() !== 'POST') {
                return response()->json(['error' => 'Method not allowed.'], 405);
            }
            $input = json_decode(request()->getContent(), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($input)) {
                return response()->json(['error' => 'Invalid request.'], 400);
            }
            $pdo = RecruitmentDatabase::pdo();
            if (Security::cifSecurityRequestorsRestricted($pdo)) {
                return response()->json(['error' => 'Requestor account management is available through Superadmin.'], 403);
            }
            $action = $input['action'] ?? 'list';
            if ($action === 'list') {
                return response()->json(MrfRequestors::cifMrfRequestorList($pdo, $input));
            }
            if ($action === 'create') {
                AccessLimiter::check('mrf-requestor-register-'.session()->get('admin'.'.'.'id'));

                return response()->json(MrfRequestors::cifCreateMrfRequestor($pdo, $input, (int) session()->get('admin'.'.'.'id')));
            }
            if ($action === 'edit') {
                AccessLimiter::check('mrf-requestor-edit-'.session()->get('admin'.'.'.'id'));
                $result = MrfRequestors::cifEditMrfRequestor($pdo, $input, (int) session()->get('admin'.'.'.'id'));
                $code = $result['code'];
                unset($result['code']);

                return response()->json($result, $code);
            }
            $result = MrfRequestors::cifManageMrfRequestor($pdo, $input, (int) session()->get('admin'.'.'.'id'));
            $code = $result['code'];
            unset($result['code']);

            return response()->json($result, $code);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\JsonException $e) {
            return response()->json(['error' => 'Invalid request data.'], 400);
        } catch (\Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            error_log('MRF requestors: '.$e->getMessage());

            return response()->json(['error' => 'Unable to load MRF requestors. Please refresh and try again.'], 500);
        }
    }
}
