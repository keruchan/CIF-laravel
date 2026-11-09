<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Services\Recruitment\AccessLimiter;
use App\Services\Recruitment\Mrf;
use App\Services\Recruitment\MrfDocument;
use App\Services\Recruitment\MrfImportStaging;
use App\Services\Recruitment\RecruitmentDatabase;
use App\Services\Recruitment\RecruitmentSession;
use Illuminate\Http\Exceptions\HttpResponseException;

class MrfImportController extends Controller
{
    public function __invoke()
    {
        try {
            if (empty(session()->get('admin'))) {
                return response()->json(['error' => 'Sign in as HR admin to upload requests.'], 401);
            }
            $action = request()->query()['action'] ?? '';
            $pdo = RecruitmentDatabase::pdo();
            if ($action === 'download' && request()->method() === 'GET') {
                $id = filter_var(request()->query()['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (! $id) {
                    return response()->json(['error' => 'Invalid request.'], 422);
                }
                $query = $pdo->prepare('SELECT i.original_filename, i.mime_type, i.original_file FROM manpower_requests m JOIN manpower_requests origin ON origin.batch_number = m.batch_number AND origin.headcount_index = 1 JOIN manpower_request_imports i ON i.request_id = origin.id WHERE m.id = ?');
                $query->execute([$id]);
                $source = $query->fetch();
                if (! $source) {
                    return response()->json(['error' => 'Uploaded document not found.'], 404);
                }

                return response($source['original_file'], 200, [
                    'Content-Type' => $source['mime_type'],
                    'Content-Disposition' => "attachment; filename=\"mrf-source\"; filename*=UTF-8''".rawurlencode($source['original_filename']),
                    'Content-Length' => (string) strlen($source['original_file']),
                ]);
            }
            if (request()->method() !== 'POST') {
                return response()->json(['error' => 'Method not allowed.'], 405);
            }
            foreach (session()->get('mrf_imports') ?? [] as $token => $stage) {
                if ($stage['expires'] < time() || $stage['admin_id'] !== (int) session()->get('admin'.'.'.'id')) {
                    MrfImportStaging::discard($stage, $token);
                    session()->forget('mrf_imports'.'.'.$token);
                }
            }
            if ($action === 'extract') {
                MrfImportStaging::prune();
                AccessLimiter::check('mrf-import-'.session()->get('admin'.'.'.'id'));
                $upload = request()->file('document');
                $source = MrfDocument::readMrfUpload($upload ? [
                    'error' => $upload->getError(),
                    'tmp_name' => $upload->getPathname(),
                    'name' => $upload->getClientOriginalName(),
                    'size' => $upload->getSize(),
                ] : []);
                $query = $pdo->prepare('SELECT i.request_id,m.mrf_number FROM manpower_request_imports i JOIN manpower_requests m ON m.id=i.request_id WHERE i.file_sha256 = ?');
                $query->execute([$source['sha256']]);
                $existing = $query->fetch();
                if ($existing) {
                    return response()->json(['error' => 'This document already belongs to MRF '.$existing['mrf_number'].'. Check the existing MRF instead of importing it again.', 'existing_id' => (int) $existing['request_id']], 409);
                }
                $recognized = MrfDocument::recognizeMrfText($source['text']);
                if (! $recognized['recognized']) {
                    return response()->json(['error' => 'No completed MRF fields were recognized. This may be a blank template or a different document. Upload a completed, searchable MRF.'], 422);
                }
                $existingRequest = MrfDocument::existingMrfDocumentRequest($pdo, $source['text']);
                if ($existingRequest && $existingRequest['same_request']) {
                    return response()->json(['error' => 'MRF number '.$existingRequest['source_number'].' already exists and the request details match. Open the existing MRF instead of creating a duplicate.', 'existing_id' => $existingRequest['id']], 409);
                }
                $accounts = $pdo->query("SELECT id, username, name, position, department_name, group_name FROM department_users WHERE account_status='Active' AND archived_at IS NULL ORDER BY department_name,group_name,name,id")->fetchAll();
                $matches = array_values(array_filter($accounts, static fn ($account) => mb_strtolower(trim($account['department_name'])) === mb_strtolower(trim($recognized['form']['department_name'])) && trim($account['department_name']) !== '' && (trim($recognized['form']['group_name'] ?? '') === '' || mb_strtolower(trim($account['group_name'])) === mb_strtolower(trim($recognized['form']['group_name'])))));
                $token = bin2hex(random_bytes(32));
                $source['admin_id'] = (int) session()->get('admin'.'.'.'id');
                // At most three bounded uploads are staged in the private PHP session.
                if (count(session()->get('mrf_imports') ?? []) >= 3) {
                    $imports = session()->get('mrf_imports');
                    $oldest = array_key_first($imports);
                    MrfImportStaging::discard($imports[$oldest], $oldest);
                    RecruitmentSession::shift('mrf_imports');
                }
                // Keep binary content out of JSON/database session payloads.
                $source = MrfImportStaging::store($source, (int) session()->get('admin.id'), $token);
                session()->put('mrf_imports'.'.'.$token, [...$source, 'expires' => time() + 1200, 'request_id' => null]);

                return response()->json([...$recognized, 'token' => $token, 'filename' => $source['filename'], 'text' => $source['text'], 'accounts' => $accounts, 'owner_id' => count($matches) === 1 ? (int) $matches[0]['id'] : null, 'number_conflict' => $existingRequest]);
            }
            $input = json_decode(request()->getContent(), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($input) || ! is_string($input['token'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $input['token'])) {
                return response()->json(['error' => 'Invalid upload session. Upload the document again.'], 422);
            }
            $token = $input['token'];
            $stage = session()->get('mrf_imports'.'.'.$token) ?? null;
            if (! $stage) {
                return response()->json(['error' => 'The upload review expired. Upload the document again.'], 422);
            }
            if ($action === 'discard') {
                MrfImportStaging::discard($stage, $token);
                session()->forget('mrf_imports'.'.'.$token);

                return response()->json(['ok' => true]);
            }
            if ($action === 'save') {
                if ($stage['request_id']) {
                    return response()->json(['record' => Mrf::loadMrf($pdo, $stage['request_id']), 'message' => 'This upload was already saved.']);
                }
                $existingRequest = MrfDocument::existingMrfDocumentRequest($pdo, $stage['text']);
                if ($existingRequest && $existingRequest['same_request']) {
                    return response()->json(['error' => 'MRF number '.$existingRequest['source_number'].' already exists and the request details match. Open the existing MRF instead of creating a duplicate.', 'existing_id' => $existingRequest['id']], 409);
                }
                if ($existingRequest && ($input['confirm_new_request'] ?? false) !== true) {
                    return response()->json(['error' => 'MRF number '.$existingRequest['source_number'].' is already taken. Check the existing MRF, then confirm this is a different request before creating a new generated number.', 'existing_id' => $existingRequest['id'], 'number_conflict' => $existingRequest], 422);
                }
                $owner = filter_var($input['owner_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (! $owner || ! is_array($input['form'] ?? null)) {
                    return response()->json(['error' => 'Select the responsible MRF requestor and review the request fields.'], 422);
                }
                $query = $pdo->prepare('SELECT id FROM department_users WHERE id = ?');
                $query->execute([$owner]);
                if (! $query->fetchColumn()) {
                    return response()->json(['error' => 'The selected requestor no longer exists. Choose another account.'], 422);
                }
                $form = Mrf::validateMrf($input['form']);
                $source = MrfImportStaging::restore($stage, (int) session()->get('admin.id'), $token);
                $record = Mrf::submitMrf($pdo, (int) $owner, $form, $token, $source);
                MrfImportStaging::discard($stage, $token);
                // Retain only the saved ID for retries after a lost response, not file bytes.
                session()->put('mrf_imports'.'.'.$token, ['admin_id' => $stage['admin_id'], 'expires' => time() + 1200, 'request_id' => (int) $record['id']]);

                return response()->json(['record' => $record, 'message' => count($record['files']).' separate MRF file(s) saved successfully from the uploaded request.']);
            }

            return response()->json(['error' => 'Unknown action.'], 404);
        } catch (\InvalidArgumentException $error) {
            return response()->json(['error' => $error->getMessage()], 422);
        } catch (\JsonException $error) {
            return response()->json(['error' => 'Invalid request data.'], 400);
        } catch (\Throwable $error) {
            if ($error instanceof HttpResponseException) {
                throw $error;
            }
            error_log('CIF MRF import: '.$error->getMessage());

            return response()->json(['error' => 'Unable to process this document. Try again or contact your administrator.'], 500);
        }
    }
}
