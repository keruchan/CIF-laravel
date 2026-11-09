<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Services\Recruitment\CifMrfAssignmentConflict;
use App\Services\Recruitment\DashboardCoverage;
use App\Services\Recruitment\Mrf;
use App\Services\Recruitment\MrfApproval;
use App\Services\Recruitment\MrfDepartment;
use App\Services\Recruitment\MrfOwnership;
use App\Services\Recruitment\RecruitmentDashboard;
use App\Services\Recruitment\RecruitmentDatabase;
use App\Services\Recruitment\Security;
use App\Services\Recruitment\TextFormat;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Exceptions\HttpResponseException;
use InvalidArgumentException;
use JsonException;
use PDO;
use Throwable;

final class MrfController extends Controller
{
    public function __invoke()
    {

        try {
            $portal = request()->query()['portal'] ?? '';
            if ($portal === 'department') {
                $portal = 'requestor';
            } // Older clients keep their original permissions.
            if (! in_array($portal, ['admin', 'requestor'], true)) {
                return response()->json(['error' => 'Invalid portal.'], 400);
            }
            $sessionKey = $portal === 'requestor' ? 'department' : 'admin';
            if (empty(session()->get($sessionKey))) {
                return response()->json(['error' => 'Please sign in to this portal to continue.'], 401);
            }
            $owner = $portal === 'requestor' ? (int) session()->get('department'.'.id') : null;
            if (request()->method() !== 'POST') {
                return response()->json(['error' => 'Method not allowed.'], 405);
            }

            $input = json_decode(request()->getContent(), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($input)) {
                return response()->json(['error' => 'Invalid request.'], 400);
            }
            $action = request()->query()['action'] ?? '';
            $pdo = RecruitmentDatabase::pdo();

            if ($action === 'hr_options') {
                if ($owner !== null) {
                    return response()->json(['error' => 'Only HR can manage HR assignments.'], 403);
                }

                return response()->json(['staff' => MrfOwnership::cifMrfHrStaff($pdo)]);
            }
            if ($action === 'assignment_update') {
                if ($owner !== null) {
                    return response()->json(['error' => 'Only HR can assign MRFs.'], 403);
                }
                $id = $input['id'] ?? null;
                $revision = $input['assignment_revision'] ?? null;
                $hr = $input['hr_admin_id'] ?? null;
                $reason = $input['reason'] ?? '';
                if (! is_int($id) || $id < 1 || ! is_int($revision) || $revision < 0 || ! array_key_exists('hr_admin_id', $input) || ($hr !== null && (! is_int($hr) || $hr < 1)) || ! is_string($reason) || mb_strlen($reason) > 2000) {
                    return response()->json(['error' => 'Choose a valid MRF, HR member and assignment reason.'], 422);
                }
                $pdo->beginTransaction();
                MrfOwnership::cifAssignMrfHr($pdo, $id, $hr, (int) session()->get('admin'.'.id'), 'manual', $reason, $revision);
                $pdo->commit();

                return response()->json(['record' => Mrf::loadMrf($pdo, $id), 'message' => 'MRF HR assignment saved.']);
            }
            if ($action === 'candidate_options') {
                if ($portal !== 'admin') {
                    return response()->json(['error' => 'Only HR can select a candidate MRF.'], 403);
                }
                $search = $input['search'] ?? '';
                if (! is_string($search) || mb_strlen($search) > 150) {
                    return response()->json(['error' => 'Use a search of up to 150 characters.'], 422);
                }
                $query = $pdo->prepare("SELECT m.id, m.mrf_number, m.position_title, m.department_name, m.site_location, m.status, ap.decision AS approval_status FROM manpower_requests m JOIN manpower_request_approvals ap ON ap.batch_number = m.batch_number AND ap.decision = 'Approved' WHERE m.status IN ('Pending', 'In Progress') AND LOCATE(?, CONCAT_WS(' ', m.mrf_number, m.position_title, m.department_name, m.site_location, m.requestor_name)) > 0 ORDER BY m.created_at DESC, m.id DESC LIMIT 21");
                $query->execute([trim($search)]);
                $requests = $query->fetchAll();
                $more = count($requests) > 20;

                return response()->json(['requests' => array_slice($requests, 0, 20), 'more' => $more]);
            }
            if ($action === 'submit') {
                if ($owner === null) {
                    return response()->json(['error' => 'Only an MRF requestor can submit an MRF.'], 403);
                }
                if (! is_array($input['form'] ?? null) || ! is_string($input['submission_key'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $input['submission_key'])) {
                    return response()->json(['error' => 'Invalid submission. Refresh the page and try again.'], 422);
                }
                $record = Mrf::submitMrf($pdo, $owner, $input['form'], $input['submission_key']);
                $record = MrfDepartment::departmentMrfRecord($pdo, $record);

                return response()->json(['record' => $record, 'message' => 'Manpower request '.$record['mrf_number'].' completed. Print it for management approval; HR will record the approval decision after verification.']);
            }
            if ($action === 'approval_update') {
                if ($owner !== null) {
                    return response()->json(['error' => 'Only HR can record management approval.'], 403);
                }
                $id = $input['id'] ?? null;
                $revision = $input['approval_revision'] ?? null;
                $decision = $input['decision'] ?? null;
                $notes = $input['notes'] ?? '';
                if (! is_int($id) || $id < 1 || ! is_int($revision) || $revision < 0 || ! is_string($decision) || ! in_array($decision, ['Approved', 'Denied'], true) || ! is_string($notes) || mb_strlen($notes) > 2000 || str_contains($notes, "\0")) {
                    return response()->json(['error' => 'Choose Approved or Denied and use notes of up to 2,000 characters.'], 422);
                }
                $notes = TextFormat::cifProperText(trim($notes));
                if (mb_strlen($notes) > 2000) {
                    return response()->json(['error' => 'Approval notes exceed 2,000 characters after formatting.'], 422);
                }
                if ($decision === 'Approved' && ($input['confirm_document'] ?? false) !== true) {
                    return response()->json(['error' => 'Confirm that you verified management approval outside the system before recording Approved.'], 422);
                }
                if ($decision === 'Denied' && $notes === '') {
                    return response()->json(['error' => 'Provide the reason for denial.'], 422);
                }
                $record = Mrf::loadMrf($pdo, $id);
                if (! $record) {
                    return response()->json(['error' => 'Manpower request not found.'], 404);
                }
                Security::cifAuditTargets($pdo, null, $id);
                $pdo->beginTransaction();
                // Match candidate saves: lock headcount rows before the shared approval.
                $query = $pdo->prepare('SELECT id FROM manpower_requests WHERE batch_number = ? ORDER BY id FOR UPDATE');
                $query->execute([$record['batch_number']]);
                $headcountIds = $query->fetchAll(PDO::FETCH_COLUMN);
                $query = $pdo->prepare('SELECT * FROM manpower_request_approvals WHERE batch_number = ? FOR UPDATE');
                $query->execute([$record['batch_number']]);
                $approval = $query->fetch();
                if ($approval && $approval['decision'] !== 'Pending') {
                    $pdo->rollBack();

                    return response()->json(['error' => 'This request is already '.$approval['decision'].'. The decision, approval details and notes are final and cannot be recorded again or changed. You can still print the request.'], 409);
                }
                if (! $approval || (int) $approval['revision'] !== $revision) {
                    $pdo->rollBack();

                    return response()->json(['error' => 'Another HR user changed this approval. Refresh the request before recording a decision.'], 409);
                }
                $timing = $decision === 'Approved' ? MrfApproval::cifMrfApprovalTiming($input) : ['date_approved' => null, 'position_category' => null, 'lead_time_days' => null, 'hiring_due_date' => null];
                if ($approval['decision'] !== $decision || (string) $approval['notes'] !== $notes) {
                    $query = $pdo->prepare("UPDATE manpower_request_approvals SET decision = ?, notes = ?, date_approved=?,position_category=?,lead_time_days=?,hiring_due_date=?,revision = revision + 1, updated_by = ?, updated_at = UTC_TIMESTAMP() WHERE batch_number = ? AND decision = 'Pending'");
                    $query->execute([$decision, $notes, ...array_values($timing), session()->get('admin'.'.id'), $record['batch_number']]);
                    $query = $pdo->prepare('INSERT INTO manpower_request_approval_history (batch_number, admin_id, previous_decision, decision, notes,date_approved,position_category,lead_time_days,hiring_due_date,revision, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())');
                    $query->execute([$record['batch_number'], session()->get('admin'.'.id'), $approval['decision'], $decision, $notes, ...array_values($timing), $revision + 1]);
                }
                $assignedHr = $input['hr_admin_id'] ?? null;
                if ($assignedHr !== null && (! is_int($assignedHr) || $assignedHr < 1)) {
                    throw new InvalidArgumentException('Choose an existing HR member or leave assignment for later.');
                }
                if ($decision === 'Approved' && $assignedHr !== null) {
                    foreach ($headcountIds as $headcountId) {
                        MrfOwnership::cifAssignMrfHr($pdo, (int) $headcountId, $assignedHr, (int) session()->get('admin'.'.id'), 'approval', 'Assigned during approval.');
                    }
                }
                $pdo->commit();

                return response()->json(['record' => Mrf::loadMrf($pdo, $id), 'message' => 'Management approval recorded as '.$decision.' for request '.$record['batch_number'].' and all its headcount files.']);
            }
            if ($action === 'detail' || $action === 'update') {
                $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (! $id) {
                    return response()->json(['error' => 'Invalid request ID.'], 422);
                }
                $record = Mrf::loadMrf($pdo, $id, $owner);
                if (! $record) {
                    return response()->json(['error' => 'Manpower request not found.'], 404);
                }
                Security::cifAuditTargets($pdo, null, $id);
                if ($action === 'detail') {
                    return response()->json(['record' => $owner !== null ? MrfDepartment::departmentMrfRecord($pdo, $record) : $record]);
                }
                if ($owner !== null) {
                    return response()->json(['error' => 'Only HR can manage submitted requests.'], 403);
                }
                $status = $input['status'] ?? null;
                $notes = $input['hr_notes'] ?? '';
                $revision = $input['revision'] ?? null;
                if (! is_string($status) || ! in_array($status, Mrf::mrfStatuses(), true) || ! is_string($notes) || mb_strlen($notes) > 4000 || str_contains($notes, "\0") || ! is_int($revision) || $revision < 0) {
                    return response()->json(['error' => 'Select a valid status and use notes of up to 4,000 characters.'], 422);
                }
                $notes = TextFormat::cifProperText(trim($notes));
                if (mb_strlen($notes) > 4000) {
                    return response()->json(['error' => 'HR notes exceed 4,000 characters after formatting.'], 422);
                }
                if ($status === 'Rejected' && $notes === '') {
                    return response()->json(['error' => 'Add a reason before rejecting this request.'], 422);
                }
                if (in_array($status, ['On Hold', 'Cancelled'], true) && $notes === '') {
                    return response()->json(['error' => 'Add a reason in HR notes before setting this request to '.$status.'.'], 422);
                }
                $pdo->beginTransaction();
                $query = $pdo->prepare('SELECT status, revision, hr_notes FROM manpower_requests WHERE id = ? FOR UPDATE');
                $query->execute([$id]);
                $current = $query->fetch();
                if (! $current || (int) $current['revision'] !== $revision) {
                    $pdo->rollBack();

                    return response()->json(['error' => 'Another HR user changed this request. Refresh before saving.'], 409);
                }
                if ($current['status'] === 'Completed' && in_array($status, ['On Hold', 'Cancelled'], true)) {
                    $pdo->rollBack();

                    return response()->json(['error' => 'This MRF is completed. Hold and Cancel are no longer available.'], 422);
                }
                if ($current['status'] === 'Cancelled' && ! in_array($status, ['Cancelled', 'In Progress'], true)) {
                    $pdo->rollBack();

                    return response()->json(['error' => 'Reinstate this cancelled MRF to In Progress before making other status changes.'], 422);
                }
                if (in_array($current['status'], ['On Hold', 'Cancelled'], true) && $status === 'In Progress' && $notes === '') {
                    $pdo->rollBack();

                    return response()->json(['error' => 'Add a reason before resuming or reinstating this MRF.'], 422);
                }
                $approvalQuery = $pdo->prepare('SELECT decision FROM manpower_request_approvals WHERE batch_number = ? FOR UPDATE');
                $approvalQuery->execute([$record['batch_number']]);
                if (in_array($status, ['In Progress', 'Completed', 'On Hold', 'Cancelled'], true) && $approvalQuery->fetchColumn() !== 'Approved') {
                    $pdo->rollBack();

                    return response()->json(['error' => 'Record verified management approval before starting, completing, holding or cancelling this request.'], 422);
                }
                if ($current['status'] !== $status || (string) $current['hr_notes'] !== $notes) {
                    $query = $pdo->prepare('UPDATE manpower_requests SET status = ?, hr_notes = ?, revision = revision + 1, updated_at = UTC_TIMESTAMP(), updated_by = ? WHERE id = ?');
                    $query->execute([$status, $notes, session()->get('admin'.'.id'), $id]);
                    $query = $pdo->prepare('INSERT INTO manpower_request_history (request_id, admin_id, previous_status, status, hr_notes, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())');
                    $query->execute([$id, session()->get('admin'.'.id'), $current['status'], $status, $notes]);
                }
                if ($status === 'In Progress') {
                    MrfOwnership::cifAssignMrfHr($pdo, (int) $id, (int) session()->get('admin'.'.id'), (int) session()->get('admin'.'.id'), 'started', 'First HR recruitment start.', null, true);
                }
                $pdo->commit();

                return response()->json(['record' => Mrf::loadMrf($pdo, $id), 'message' => 'Manpower request updated. Status: '.$status.'.']);
            }
            if ($action === 'dashboard') {
                if ($owner === null) {
                    return response()->json(['error' => 'Sign in as a requestor to view this dashboard.'], 403);
                }

                $source = MrfDepartment::departmentMrfListSource($owner).' LEFT JOIN manpower_request_approvals ap ON ap.batch_number = m.batch_number';
                [$coverageWhere,$coverageParams] = MrfDepartment::departmentMrfCoverage($input);
                $coverageSql = $coverageWhere ? ' WHERE '.implode(' AND ', $coverageWhere) : '';
                $query = $pdo->prepare("SELECT m.id,m.mrf_number,m.position_title,m.site_location,m.headcount,m.status,m.created_at,m.updated_at,COALESCE(ap.decision,'Pending') AS approval_status,ap.hiring_due_date FROM $source$coverageSql ORDER BY m.created_at DESC,m.id DESC");
                $query->execute($coverageParams);
                $rows = $query->fetchAll();
                $approval = array_fill_keys(Mrf::mrfApprovalStatuses(), 0);
                $progress = array_fill_keys(Mrf::mrfStatuses(), 0);
                $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
                $soon = $today->modify('+7 days')->format('Y-m-d');
                $today = $today->format('Y-m-d');
                $headcount = 0;
                $overdue = 0;
                $upcoming = 0;
                $deadlines = [];
                foreach ($rows as $row) {
                    $approval[$row['approval_status']]++;
                    $progress[$row['status']]++;
                    $headcount += (int) $row['headcount'];
                    if ($row['approval_status'] === 'Approved' && ! in_array($row['status'], ['Completed', 'Rejected', 'Cancelled'], true) && $row['hiring_due_date']) {
                        $deadlines[] = $row;
                        if ($row['hiring_due_date'] < $today) {
                            $overdue++;
                        } elseif ($row['hiring_due_date'] <= $soon) {
                            $upcoming++;
                        }
                    }
                }
                usort($deadlines, static fn (array $a, array $b): int => strcmp($a['hiring_due_date'], $b['hiring_due_date']) ?: ((int) $a['id'] <=> (int) $b['id']));

                return response()->json(['total' => count($rows), 'headcount' => $headcount, 'approval' => $approval, 'progress' => $progress, 'outstanding' => count($deadlines), 'overdue' => $overdue, 'upcoming' => $upcoming, 'recent' => array_slice($rows, 0, 5), 'deadlines' => array_slice($deadlines, 0, 5)]);
            }
            if ($action === 'list') {

                $search = $input['search'] ?? '';
                $status = $input['status'] ?? '';
                $sort = $input['sort'] ?? 'desc';
                $page = $input['page'] ?? 1;
                $approvalFilter = $input['approval'] ?? '';
                if (! is_string($approvalFilter) || ($approvalFilter !== '' && ! in_array($approvalFilter, Mrf::mrfApprovalStatuses(), true))) {
                    return response()->json(['error' => 'Choose a valid approval filter.'], 422);
                }
                if (! is_string($search) || mb_strlen($search) > 254 || ! is_string($status) || ($status !== '' && ! in_array($status, Mrf::mrfStatuses(), true)) || ! in_array($sort, ['asc', 'desc'], true) || ! is_int($page) || $page < 1) {
                    return response()->json(['error' => 'Invalid request filters.'], 422);
                }
                $where = [];
                $params = [];
                if ($owner !== null) {
                    $where[] = 'm.department_user_id = ?';
                    $params[] = $owner;
                }
                if ($owner !== null) {
                    [$extraWhere,$extraParams] = MrfDepartment::departmentMrfCoverage($input);
                    $where = array_merge($where, $extraWhere);
                    $params = array_merge($params, $extraParams);
                }
                // Cards follow the coverage controls above them; lower filters affect the table.
                $baseWhere = $where;
                $baseParams = $params;
                if (trim($search) !== '') {
                    $where[] = "LOCATE(?, CONCAT_WS(' ', m.mrf_number, m.legacy_mrf_number, m.requestor_name, m.position_title, m.department_name, m.site_location)) > 0";
                    $params[] = trim($search);
                }
                if ($status !== '') {
                    $where[] = 'm.status = ?';
                    $params[] = $status;
                }
                if ($approvalFilter !== '') {
                    $where[] = "COALESCE(ap.decision, 'Pending') = ?";
                    $params[] = $approvalFilter;
                }
                if ($owner === null && isset($input['dashboard_metric'])) {
                    $metric = $input['dashboard_metric'];
                    $range = $input['dashboard_range'] ?? 'all';
                    $position = $input['dashboard_position'] ?? '';
                    if (! is_string($metric) || ! in_array($metric, ['mrf_total', 'mrf_outstanding', 'mrf_pending', 'assigned_total', 'assigned_due', 'positions_total', 'positions_progress'], true) || ! is_string($range) || ! is_string($position)) {
                        throw new InvalidArgumentException('Invalid dashboard selection.');
                    }
                    $ids = RecruitmentDashboard::cifDashboardCohort($pdo, $metric, $range, $position, (int) session()->get('admin'.'.id'), null, DashboardCoverage::cifDashboardCoverage($input, 'dashboard_'))['ids'];
                    $where[] = $ids ? 'm.id IN ('.implode(',', $ids).')' : '1=0';
                }
                $filter = $where ? ' WHERE '.implode(' AND ', $where) : '';
                $source = $owner !== null ? MrfDepartment::departmentMrfListSource($owner) : 'manpower_requests m';
                $source .= ' LEFT JOIN manpower_request_approvals ap ON ap.batch_number = m.batch_number';
                $query = $pdo->prepare('SELECT COUNT(*) FROM '.$source.$filter);
                $query->execute($params);
                $total = (int) $query->fetchColumn();
                $pages = max(1, (int) ceil($total / 8));
                $page = min($page, $pages);
                $offset = ($page - 1) * 8;
                $direction = $sort === 'asc' ? 'ASC' : 'DESC';
                $headcountColumn = $owner === null ? ', m.headcount_index' : '';
                $headcountSort = $owner === null ? ', m.headcount_index ASC' : '';
                $query = $pdo->prepare("SELECT m.id, m.mrf_number, m.requestor_name, m.position_title, m.department_name, m.site_location, m.headcount$headcountColumn, m.status, COALESCE(ap.decision, 'Pending') AS approval_status,ap.date_approved,ap.position_category,ap.lead_time_days,ap.hiring_due_date,m.created_at, m.updated_at FROM $source$filter ORDER BY m.created_at $direction, m.batch_number $direction$headcountSort LIMIT 8 OFFSET $offset");
                $query->execute($params);
                $rows = $query->fetchAll();
                $summaryFilter = $baseWhere ? ' WHERE '.implode(' AND ', $baseWhere) : '';
                $summaryParams = $baseParams;
                $summary = array_fill_keys(array_merge(['Total'], Mrf::mrfStatuses()), 0);
                $query = $pdo->prepare('SELECT m.status, COUNT(*) AS total FROM '.$source.$summaryFilter.' GROUP BY m.status');
                $query->execute($summaryParams);
                foreach ($query as $row) {
                    $summary[$row['status']] = (int) $row['total'];
                    $summary['Total'] += (int) $row['total'];
                }
                $approvalSummary = array_fill_keys(array_merge(['Total'], Mrf::mrfApprovalStatuses()), 0);
                $query = $pdo->prepare("SELECT COALESCE(ap.decision, 'Pending') AS decision, COUNT(*) AS total FROM ".$source.$summaryFilter." GROUP BY COALESCE(ap.decision, 'Pending')");
                $query->execute($summaryParams);
                foreach ($query as $row) {
                    $approvalSummary[$row['decision']] = (int) $row['total'];
                    $approvalSummary['Total'] += (int) $row['total'];
                }

                return response()->json(['requests' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'summary' => $summary, 'approval_summary' => $approvalSummary]);
            }

            return response()->json(['error' => 'Unknown action.'], 404);
        } catch (CifMrfAssignmentConflict $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return response()->json(['error' => $e->getMessage()], 409);
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
            error_log('CIF MRF: '.$e->getMessage());

            return response()->json(['error' => 'Unable to complete the request. Please try again or contact HR.'], 500);
        }

    }
}
