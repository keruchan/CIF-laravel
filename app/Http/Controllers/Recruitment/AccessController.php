<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Services\Recruitment\AccessLimiter;
use App\Services\Recruitment\ApplicantAccess;
use App\Services\Recruitment\CandidateAnalytics;
use App\Services\Recruitment\CandidateDeployment;
use App\Services\Recruitment\CandidateInterviews;
use App\Services\Recruitment\CandidateListStages;
use App\Services\Recruitment\CandidateMrf;
use App\Services\Recruitment\CandidatePortal;
use App\Services\Recruitment\CandidatePublication;
use App\Services\Recruitment\CandidateStatuses;
use App\Services\Recruitment\CandidateStorage;
use App\Services\Recruitment\CandidateView;
use App\Services\Recruitment\CifFields;
use App\Services\Recruitment\DashboardCoverage;
use App\Services\Recruitment\HiringPositions;
use App\Services\Recruitment\HrFields;
use App\Services\Recruitment\HrKpi;
use App\Services\Recruitment\HrProfile;
use App\Services\Recruitment\HrStaff;
use App\Services\Recruitment\MrfImportStaging;
use App\Services\Recruitment\RecruitmentDashboard;
use App\Services\Recruitment\RecruitmentDatabase;
use App\Services\Recruitment\Security;
use App\Services\Recruitment\TextFormat;
use Illuminate\Http\Exceptions\HttpResponseException;
use InvalidArgumentException;
use JsonException;
use PDOException;
use Throwable;

final class AccessController extends Controller
{
    public function __invoke()
    {

        try {
            $action = request()->query()['action'] ?? '';
            if (request()->method() === 'GET' && $action === 'state') {
                return response()->json([
                    'csrf' => session()->token(),
                    'admin' => session()->get('admin') ?? null,
                    'candidate' => CandidatePortal::candidateState(),
                ]);
            }
            if (request()->method() !== 'POST') {

                return response()->json(['error' => 'Method not allowed.'], 405);
            }

            $input = json_decode(request()->getContent(), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($input)) {
                return response()->json(['error' => 'Invalid request.'], 400);
            }
            $email = strtolower(trim(is_string($input['email'] ?? null) ? $input['email'] : ''));

            if ($action === 'admin_login') {
                AccessLimiter::check('admin');
                $query = RecruitmentDatabase::pdo()->prepare("SELECT id, email, name, password_hash,role,account_status,access_version FROM admins WHERE email = ? AND role='hr' AND account_status='Active'");
                $query->execute([$email]);
                $admin = $query->fetch();
                $password = is_string($input['password'] ?? null) ? $input['password'] : '';
                if (! $admin || ! password_verify($password, $admin['password_hash'])) {
                    Security::cifAuditWrite(RecruitmentDatabase::pdo(), 'Access', 'HR sign-in failed', 'Account', '', $email, [], 'Failed');

                    return response()->json(['error' => 'Incorrect username or password.'], 401);
                }
                session()->migrate(true);
                // Each portal keeps its own authenticated role, including other open browser tabs.
                session()->put('admin', Security::cifAdminProfile($admin));
                session()->put('admin_access_version', (int) $admin['access_version']);
                session()->put('admin_password_signature', hash('sha256', $admin['password_hash']));
                Security::cifAuditContext(RecruitmentDatabase::pdo(), 'HR', (int) $admin['id'], $admin['name']);
                Security::cifAuditWrite(RecruitmentDatabase::pdo(), 'Access', 'HR signed in', 'Account', (string) $admin['id'], $admin['email']);

                return response()->json(['admin' => session()->get('admin')]);
            }
            if ($action === 'admin_logout') {
                foreach (session()->get('mrf_imports', []) as $token => $stage) {
                    MrfImportStaging::discard($stage, $token);
                }
                if (! empty(session()->get('admin'))) {
                    Security::cifAuditWrite(RecruitmentDatabase::pdo(), 'Access', 'HR signed out', 'Account', (string) session()->get('admin'.'.id'), session()->get('admin'.'.email'));
                }
                session()->forget(['admin', 'admin_password_signature', 'admin_access_version', 'mrf_imports']);
                session()->migrate(true);

                return response()->json(['ok' => true]);
            }
            if (in_array($action, ['hr_kpi_charts', 'hr_staff_monitor', 'hr_profile_photo', 'hr_profile_photo_save', 'admin_settings_save', 'admin_badges', 'candidates', 'candidate_dashboard', 'candidate_archive', 'candidate_save', 'candidate_stage_save', 'candidate_deployment_save'], true)) {
                if (empty(session()->get('admin'))) {
                    return response()->json(['error' => 'Sign in as HR to continue.'], 401);
                }
                $pdo = RecruitmentDatabase::pdo();
                CandidateStatuses::refreshCifRetiredExamStatuses($pdo);
                $statuses = CandidateStatuses::cifCandidateStatuses();
                if ($action === 'hr_profile_photo') {
                    return response()->json(['photo' => HrProfile::loadCifHrProfilePhoto($pdo, (int) session()->get('admin'.'.id')), 'admin_id' => (int) session()->get('admin'.'.id')]);
                }
                if ($action === 'hr_profile_photo_save') {
                    try {
                        // Never accept a target account from the browser: members edit only their own photo.
                        $photo = HrProfile::saveCifHrProfilePhoto($pdo, (int) session()->get('admin'.'.id'), $input['photo'] ?? null);

                        return response()->json(['ok' => true, 'photo' => $photo, 'message' => $photo === '' ? 'Profile picture removed.' : 'Profile picture saved.']);
                    } catch (InvalidArgumentException $e) {
                        return response()->json(['error' => $e->getMessage()], 422);
                    }
                }
                if ($action === 'hr_staff_monitor') {
                    try {
                        return response()->json(HrKpi::cifHrKpi($pdo, $input) + ['current_admin_id' => (int) session()->get('admin'.'.id')]);
                    } catch (InvalidArgumentException $e) {
                        return response()->json(['error' => $e->getMessage()], 422);
                    }
                }
                if ($action === 'hr_kpi_charts') {
                    try {
                        return response()->json(HrKpi::cifHrKpiCharts($pdo, $input));
                    } catch (InvalidArgumentException $e) {
                        return response()->json(['error' => $e->getMessage()], 422);
                    }
                }
                if ($action === 'admin_settings_save') {
                    $name = is_string($input['name'] ?? null) ? TextFormat::cifSavedText(trim($input['name']), 'name') : '';
                    $current = is_string($input['current_password'] ?? null) ? $input['current_password'] : '';
                    $next = is_string($input['new_password'] ?? null) ? $input['new_password'] : '';
                    $confirmation = is_string($input['confirm_password'] ?? null) ? $input['confirm_password'] : '';
                    if ($name === '' || mb_strlen($name) > 150) {
                        return response()->json(['error' => 'Enter a display name of up to 150 characters.'], 422);
                    }
                    if ($current === '' || strlen($current) > 72) {
                        return response()->json(['error' => 'Enter your current password.'], 422);
                    }
                    if ($next !== '' || $confirmation !== '') {
                        // PASSWORD_DEFAULT currently uses bcrypt; prevent silent truncation.
                        if (mb_strlen($next) < 12 || strlen($next) > 72 || str_contains($next, "\0")) {
                            return response()->json(['error' => 'Use at least 12 characters and no more than 72 bytes for the new password.'], 422);
                        }
                        if (! hash_equals($next, $confirmation)) {
                            return response()->json(['error' => 'The new passwords do not match.'], 422);
                        }
                    }
                    AccessLimiter::check('admin-settings-'.session()->get('admin'.'.id'));
                    $query = $pdo->prepare('SELECT email, name, password_hash FROM admins WHERE id = ?');
                    $query->execute([session()->get('admin'.'.id')]);
                    $account = $query->fetch();
                    if (! $account || ! password_verify($current, $account['password_hash'])) {
                        return response()->json(['error' => 'Your current password is incorrect.'], 422);
                    }
                    if ($next !== '' && password_verify($next, $account['password_hash'])) {
                        return response()->json(['error' => 'Choose a new password different from your current password.'], 422);
                    }
                    $changedPassword = $next !== '';
                    if (! $changedPassword && $name === $account['name']) {
                        return response()->json(['admin' => session()->get('admin'), 'message' => 'Your settings are already up to date.']);
                    }
                    $hash = $changedPassword ? password_hash($next, PASSWORD_DEFAULT) : $account['password_hash'];
                    $query = $pdo->prepare('UPDATE admins SET name = ?, password_hash = ?,revision=revision+1,access_version=access_version+1 WHERE id = ? AND password_hash = ? AND name = ?');
                    $query->execute([$name, $hash, session()->get('admin'.'.id'), $account['password_hash'], $account['name']]);
                    if ($query->rowCount() !== 1) {
                        return response()->json(['error' => 'Your account changed in another session. Sign in again before saving.'], 409);
                    }
                    session()->put('admin'.'.name', $name);
                    session()->put('admin_password_signature', hash('sha256', $hash));
                    session()->put('admin_access_version', (int) (session()->get('admin_access_version') ?? 0) + 1);
                    if ($changedPassword) {
                        session()->migrate(true);
                    }

                    return response()->json(['admin' => session()->get('admin'), 'message' => $changedPassword ? 'Password updated successfully. Other sessions have been signed out; you remain signed in here.' : 'Your display name has been updated.']);
                }
                if ($action === 'admin_badges') {
                    return response()->json([
                        'submitted' => (int) $pdo->query("SELECT COUNT(*) FROM candidates WHERE archived_at IS NULL AND status = 'Screening'")->fetchColumn(),
                        'pending' => (int) $pdo->query('SELECT COUNT(*) FROM invitations WHERE archived_at IS NULL AND submitted_at IS NULL AND redeemed_at IS NULL AND expires_at > UTC_TIMESTAMP()')->fetchColumn(),
                        'mrf_pending' => (int) $pdo->query("SELECT COUNT(*) FROM manpower_requests m LEFT JOIN manpower_request_approvals ap ON ap.batch_number = m.batch_number WHERE COALESCE(ap.decision, 'Pending') = 'Pending'")->fetchColumn(),
                    ]);
                }
                if ($action === 'candidate_stage_save') {
                    if (isset($input['complete']) && ! is_bool($input['complete'])) {
                        return response()->json(['error' => 'Invalid finish interview request.'], 422);
                    }
                    $complete = $input['complete'] ?? false;
                    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    $stage = $input['stage'] ?? null;
                    if (! $id || ! is_string($stage) || ! in_array($stage, ['department_interview', 'lie_detection', 'job_offer'], true) || ! is_int($input['revision'] ?? null) || $input['revision'] < 0) {
                        return response()->json(['error' => 'Invalid interview stage request.'], 422);
                    }
                    try {
                        $values = CandidateInterviews::validateCifInterview($input, $stage);
                    } catch (InvalidArgumentException $e) {
                        return response()->json(['error' => $e->getMessage()], 422);
                    }
                    $pdo->beginTransaction();
                    $lock = $pdo->prepare('SELECT id FROM candidates WHERE id = ? FOR UPDATE');
                    $lock->execute([$id]);
                    if (! $lock->fetchColumn()) {
                        $pdo->rollBack();

                        return response()->json(['error' => 'Candidate not found.'], 404);
                    }
                    $candidate = CandidateView::loadCifCandidate($pdo, $id);
                    Security::cifAuditTargets($pdo, $id, ! empty($candidate['mrf']['id']) ? (int) $candidate['mrf']['id'] : null);
                    if (CandidateDeployment::cifCandidateIsDeployed($candidate)) {
                        $pdo->rollBack();

                        return response()->json(['error' => 'This candidate has been deployed. The CIF and hiring assessments are view-only and can no longer be edited.'], 409);
                    }
                    if ($candidate) {
                        CandidatePublication::cifEnsurePublicBaseline($pdo, $candidate);
                    } $previous = CandidateInterviews::cifInterviewRecord($candidate, $stage);
                    if (isset($input['requirements_revision']) && $input['requirements_revision'] !== CandidateInterviews::cifAssessmentRequirements($candidate)['revision']) {
                        $pdo->rollBack();

                        return response()->json(['error' => 'Required assessments changed. Refresh before saving this interview.'], 409);
                    }
                    if ($input['revision'] !== $previous['revision']) {
                        $pdo->rollBack();

                        return response()->json(['error' => 'Another HR user updated this interview. Refresh before saving.'], 409);
                    }
                    if ($reason = CandidateInterviews::cifInterviewPrerequisite($candidate, $stage)) {
                        $pdo->rollBack();

                        return response()->json(['error' => $reason], 422);
                    }
                    CandidateInterviews::saveCifInterview($pdo, $id, $stage, $values, $previous['revision'], (int) session()->get('admin'.'.id'), $complete || ! empty($previous['completed_at']));
                    HrStaff::trackCifHrPhase($pdo, $id, $stage, (int) session()->get('admin'.'.id'), $complete, $values['decision'], $previous['completed_at']);
                    $notice = CandidateInterviews::cifInterviewStages()[$stage].($complete ? ($stage === 'job_offer' ? ' response saved and offer finished.' : ' changes saved and interview finished.') : ' changes saved.');
                    if ($complete) {
                        $notice .= ' Decision: '.$values['decision'].'.';
                    }
                    $status = CandidateStatuses::syncCifCandidateStatus($pdo, $id);
                    $nextStage = $complete ? CandidateStatuses::cifNextStageAfterCompletion(CandidateView::loadCifCandidate($pdo, $id), $stage) : null;
                    $pdo->commit();

                    return response()->json(['ok' => true, 'revision' => $previous['revision'] + 1, 'notice' => $notice, 'status' => $status, 'next_stage' => $nextStage]);
                }
                if ($action === 'candidate_deployment_save') {
                    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if (! $id || ! is_bool($input['complete'] ?? null) || ! is_int($input['revision'] ?? null) || $input['revision'] < 0 || ! is_int($input['requirements_revision'] ?? null)) {
                        return response()->json(['error' => 'Invalid deployment request.'], 422);
                    }
                    $pdo->beginTransaction();
                    $lock = $pdo->prepare('SELECT id FROM candidates WHERE id = ? FOR UPDATE');
                    $lock->execute([$id]);
                    if (! $lock->fetchColumn()) {
                        $pdo->rollBack();

                        return response()->json(['error' => 'Candidate not found.'], 404);
                    }
                    $candidate = CandidateView::loadCifCandidate($pdo, $id);
                    Security::cifAuditTargets($pdo, $id, ! empty($candidate['mrf']['id']) ? (int) $candidate['mrf']['id'] : null);
                    if (CandidateDeployment::cifCandidateIsDeployed($candidate)) {
                        $pdo->rollBack();

                        return response()->json(['error' => 'This candidate has been deployed. The CIF and hiring assessments are view-only and can no longer be edited.'], 409);
                    }
                    if ($candidate) {
                        CandidatePublication::cifEnsurePublicBaseline($pdo, $candidate);
                    } $previous = CandidateDeployment::cifDeploymentRecord($candidate);
                    if ($input['revision'] !== $previous['revision'] || $input['requirements_revision'] !== CandidateInterviews::cifAssessmentRequirements($candidate)['revision']) {
                        $pdo->rollBack();

                        return response()->json(['error' => 'Deployment or assessment requirements changed. Refresh before saving.'], 409);
                    }
                    if ($reason = CandidateInterviews::cifInterviewPrerequisite($candidate, 'deployment')) {
                        $pdo->rollBack();

                        return response()->json(['error' => $reason], 422);
                    }
                    try {
                        $values = CandidateDeployment::validateCifDeployment($pdo, $input, $candidate, $input['complete']);
                    } catch (InvalidArgumentException $e) {
                        $pdo->rollBack();

                        return response()->json(['error' => $e->getMessage()], 422);
                    }
                    Security::cifAuditTargets($pdo, $id, $values['request_id'], isset($previous['request_id']) ? (int) $previous['request_id'] : null);
                    try {
                        CandidateDeployment::saveCifDeployment($pdo, $id, $values, $previous, (int) session()->get('admin'.'.id'), $input['complete']);
                    } catch (PDOException $e) {
                        if (($e->errorInfo[1] ?? null) !== 1062) {
                            throw $e;
                        } $pdo->rollBack();

                        return response()->json(['error' => 'Employee ID is already assigned. Use a unique Employee ID.'], 409);
                    }
                    CandidateMrf::startCandidateMrf($pdo, $values['request_id'], (int) session()->get('admin'.'.id'));
                    HrStaff::trackCifHrPhase($pdo, $id, 'deployment', (int) session()->get('admin'.'.id'), $input['complete'], $input['complete'] || ! empty($previous['completed_at']) ? 'Deployed' : null, $previous['completed_at']);
                    $status = CandidateStatuses::syncCifCandidateStatus($pdo, $id);
                    $pdo->commit();

                    return response()->json(['ok' => true, 'notice' => $input['complete'] ? 'Deployment details saved and deployment completed.' : 'Deployment changes saved.', 'revision' => $previous['revision'] + 1, 'status' => $status]);
                }
                if ($action === 'candidate_save') {

                    if (isset($input['complete']) && ! is_bool($input['complete'])) {
                        return response()->json(['error' => 'Invalid finish interview request.'], 422);
                    }
                    $complete = $input['complete'] ?? false;
                    $scope = $input['scope'] ?? 'initial_interview';
                    if (! in_array($scope, ['cif', 'initial_interview'], true)) {
                        return response()->json(['error' => 'Invalid CIF save scope.'], 422);
                    }
                    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if (! $id || ! is_array($input['form'] ?? null)) {
                        return response()->json(['error' => 'Invalid candidate information.'], 422);
                    }
                    $pdo->beginTransaction();
                    $lock = $pdo->prepare('SELECT id FROM candidates WHERE id = ? FOR UPDATE');
                    $lock->execute([$id]);
                    if (! $lock->fetchColumn()) {
                        $pdo->rollBack();

                        return response()->json(['error' => 'Candidate not found.'], 404);
                    }
                    $candidate = CandidateView::loadCifCandidate($pdo, $id);
                    Security::cifAuditTargets($pdo, $id, ! empty($candidate['mrf']['id']) ? (int) $candidate['mrf']['id'] : null);
                    if (CandidateDeployment::cifCandidateIsDeployed($candidate)) {
                        $pdo->rollBack();

                        return response()->json(['error' => 'This candidate has been deployed. The CIF and hiring assessments are view-only and can no longer be edited.'], 409);
                    }
                    if ($candidate) {
                        CandidatePublication::cifEnsurePublicBaseline($pdo, $candidate);
                    }
                    if (($input['revision'] ?? null) !== $candidate['revision']) {
                        $pdo->rollBack();

                        return response()->json(['error' => 'Another HR user saved changes. Refresh before editing this record.'], 409);
                    }
                    if ($scope === 'initial_interview' && ($reason = CandidateInterviews::cifInterviewPrerequisite($candidate, 'initial_interview'))) {
                        $pdo->rollBack();

                        return response()->json(['error' => $reason], 422);
                    }
                    try {
                        $posted = $input['form'];
                        foreach (['certification_name', 'background_check_name'] as $key) {
                            $posted[$key] = $candidate['form'][$key] ?? '';
                        }
                        $form = array_replace($candidate['form'], CandidateStorage::validateCifInformation($posted, false, $candidate['form']));
                        $form['desired_position'] = HiringPositions::validateCifAppliedPosition($form['desired_position'], true);
                        $hr = $scope === 'cif' ? $candidate['hr'] : HrFields::validateHrReview($input['hr'] ?? [], $candidate['hr'], $input['hr_details'] ?? []);
                        $currentMrf = $candidate['mrf'] ? (int) $candidate['mrf']['id'] : null;
                        $selectedMrf = CandidateMrf::validateCandidateMrf($pdo, array_key_exists('mrf_id', $input) ? $input['mrf_id'] : $currentMrf, $currentMrf, $input['mrf_text'] ?? null, $scope !== 'cif');
                        $initial = CandidateInterviews::cifInterviewRecord($candidate, 'initial_interview');
                        $interviewValues = $scope === 'cif' ? $initial : CandidateInterviews::validateCifInterview(['decision' => $input['decision'] ?? $initial['decision'], 'remarks' => $input['decision_remarks'] ?? $initial['remarks']]);
                        $requirements = $scope === 'cif' ? CandidateInterviews::cifAssessmentRequirements($candidate) : CandidateInterviews::validateCifAssessmentRequirements($input['assessment_requirements'] ?? null, $candidate);
                        if ($scope === 'cif' && $complete) {
                            $screeningValues = CandidateInterviews::validateCifInterview(['decision' => $input['screening_decision'] ?? ''], 'cif');
                        }
                    } catch (InvalidArgumentException $e) {
                        $pdo->rollBack();

                        return response()->json(['error' => $e->getMessage()], 422);
                    }
                    $form['position'] = $form['desired_position'];
                    $statusNotice = $scope === 'cif' ? 'CIF changes saved.' : 'CIF and HR assessment changes saved.';
                    $screeningMissing = [];
                    if ($complete) {
                        $missing = CandidateStatuses::cifCompletionMissing(['form' => $form, 'hr' => $hr], $scope !== 'cif');
                        if ($scope === 'cif') {
                            $screeningMissing = $missing;
                        } elseif ($missing) {
                            $pdo->rollBack();

                            return response()->json(['error' => 'Cannot complete interview. Missing: '.implode(', ', $missing).'. Use Save to keep incomplete work.', 'missing' => $missing], 422);
                        }
                        $statusNotice = $scope === 'cif' ? 'CIF changes saved and screening completed. Decision: '.$screeningValues['decision'].'.' : 'Changes saved and interview finished. Decision: '.$interviewValues['decision'].'.';
                    }
                    Security::cifAuditTargets($pdo, $id, $selectedMrf, $currentMrf);
                    $query = $pdo->prepare('INSERT INTO candidate_admin_revisions (candidate_id, admin_id, form_data, hr_data, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())');
                    $query->execute([$id, session()->get('admin'.'.id'), json_encode($form, JSON_THROW_ON_ERROR), json_encode($hr, JSON_THROW_ON_ERROR)]);
                    $revision = (int) $pdo->lastInsertId();
                    $query = $pdo->prepare('INSERT INTO candidate_mrf_assignments (revision_id, candidate_id, request_id) VALUES (?, ?, ?)');
                    $query->execute([$revision, $id, $selectedMrf]);
                    $query = $pdo->prepare('UPDATE candidates SET first_name = ?, middle_name = ?, last_name = ?, position = ? WHERE id = ?');
                    $query->execute([$form['fn'], $form['mn'] ?: null, $form['ln'], $form['desired_position'] ?: null, $id]);
                    $priorScreening = CandidateInterviews::cifInterviewRecord($candidate, 'cif');
                    $passedScreening = $scope === 'cif' && $complete ? $screeningValues['decision'] === 'Passed' : ! empty($priorScreening['completed_at']) && $priorScreening['decision'] === 'Passed';
                    $mrfStarted = $selectedMrf !== null && CandidateMrf::startCandidateMrf($pdo, $selectedMrf, (int) session()->get('admin'.'.id'), $passedScreening, $scope === 'cif' ? 'screening' : 'started');
                    if ($scope === 'initial_interview') {
                        CandidateInterviews::saveCifInterview($pdo, $id, 'initial_interview', $interviewValues, $initial['revision'], (int) session()->get('admin'.'.id'), $complete || ! empty($initial['completed_at']));
                        CandidateInterviews::saveCifAssessmentRequirements($pdo, $id, $candidate, $requirements, (int) session()->get('admin'.'.id'));
                    } elseif ($complete) {
                        $screening = CandidateInterviews::cifInterviewRecord($candidate, 'cif');
                        CandidateInterviews::saveCifInterview($pdo, $id, 'cif', $screeningValues, $screening['revision'], (int) session()->get('admin'.'.id'), true);
                    }
                    $trackedDecision = $scope === 'initial_interview' ? $interviewValues['decision'] : ($complete ? $screeningValues['decision'] : CandidateInterviews::cifInterviewRecord($candidate, 'cif')['decision']);
                    HrStaff::trackCifHrPhase($pdo, $id, $scope, (int) session()->get('admin'.'.id'), $complete, $trackedDecision, CandidateInterviews::cifInterviewRecord($candidate, $scope)['completed_at']);
                    $newStatus = CandidateStatuses::syncCifCandidateStatus($pdo, $id);
                    $nextStage = $complete ? CandidateStatuses::cifNextStageAfterCompletion(CandidateView::loadCifCandidate($pdo, $id), $scope) : null;
                    $statusNotice .= ' Stage: '.CandidateStatuses::cifCandidateStatusLabel($newStatus).'.';
                    if ($screeningMissing) {
                        $statusNotice .= ' Warning: '.count($screeningMissing).' required CIF answers are still missing. Verify and complete them with the candidate during HR interview.';
                    }
                    if ($mrfStarted) {
                        $statusNotice .= ' Selected MRF moved to In Progress.';
                    }
                    $pdo->commit();

                    return response()->json(['ok' => true, 'revision' => $revision, 'status' => $newStatus, 'notice' => $statusNotice, 'mrf_started' => $mrfStarted, 'missing' => $screeningMissing, 'next_stage' => $nextStage]);
                }
                if ($action === 'candidate_status') {
                    return response()->json(['error' => 'Candidate status is automatic. Complete the appropriate roadmap stage to update it.'], 409);
                }
                if ($action === 'candidate_archive') {
                    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if (! $id || ! is_bool($input['archived'] ?? null)) {
                        return response()->json(['error' => 'Invalid archive action.'], 422);
                    }
                    $query = $pdo->prepare('SELECT id FROM candidates WHERE id = ?');
                    $query->execute([$id]);
                    if (! $query->fetchColumn()) {
                        return response()->json(['error' => 'Candidate not found.'], 404);
                    }
                    $query = $pdo->prepare($input['archived'] ? 'UPDATE candidates SET archived_at = UTC_TIMESTAMP(), archived_by = ? WHERE id = ?' : 'UPDATE candidates SET archived_at = NULL, archived_by = NULL WHERE id = ?');
                    $query->execute($input['archived'] ? [session()->get('admin'.'.id'), $id] : [$id]);

                    return response()->json(['ok' => true]);
                }
                if ($action === 'candidate_dashboard') {
                    $range = $input['range'] ?? 'all';
                    $position = $input['position'] ?? '';
                    if (! is_string($range) || ! in_array($range, ['all', '30', '90', '365'], true) || ! is_string($position) || ($position !== '' && ! in_array($position, HiringPositions::cifAppliedPositions(), true))) {
                        return response()->json(['error' => 'Choose a position from the available applied-position list.'], 422);
                    }
                    $trendStatus = $input['trend_status'] ?? '';
                    if (! is_string($trendStatus) || ($trendStatus !== '' && ! in_array($trendStatus, $statuses, true))) {
                        return response()->json(['error' => 'Invalid chart status.'], 422);
                    }
                    $trendInterval = $input['trend_interval'] ?? 'monthly';
                    if (! is_string($trendInterval) || ! in_array($trendInterval, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
                        return response()->json(['error' => 'Invalid timeline interval.'], 422);
                    }
                    try {
                        $coverage = DashboardCoverage::cifDashboardCoverage($input);
                    } catch (InvalidArgumentException $e) {
                        return response()->json(['error' => $e->getMessage()], 422);
                    }
                    $analytics = CandidateAnalytics::cifAnalyticsSummary(CandidateAnalytics::cifAnalyticsRows($pdo), $range, $position, $trendStatus, $trendInterval, $coverage);
                    $summary = ['total' => $analytics['total'], 'statuses' => $analytics['charts']['status'], 'sex' => $analytics['charts']['sex'], 'ages' => $analytics['charts']['age'], 'archived' => (int) $pdo->query('SELECT COUNT(*) FROM candidates WHERE archived_at IS NOT NULL')->fetchColumn()];
                    $pending = (int) $pdo->query('SELECT COUNT(*) FROM invitations WHERE archived_at IS NULL AND submitted_at IS NULL AND redeemed_at IS NULL AND expires_at > UTC_TIMESTAMP()')->fetchColumn();
                    $cards = RecruitmentDashboard::cifRecruitmentDashboardCards($pdo, $range, $position, (int) session()->get('admin'.'.id'), null, $coverage);

                    return response()->json(['summary' => $summary, 'analytics' => $analytics, 'pending_invitations' => $pending, 'dashboard_cards' => $cards]);
                }
                $search = trim(is_string($input['search'] ?? null) ? $input['search'] : '');
                $status = is_string($input['status'] ?? null) ? $input['status'] : '';
                $position = is_string($input['position'] ?? null) ? $input['position'] : '';
                if (mb_strlen($search) > 254 || ($position !== '' && ! in_array($position, HiringPositions::cifAppliedPositions(), true)) || ($status !== '' && ! in_array($status, $statuses, true))) {
                    return response()->json(['error' => 'Choose an available applied position and status.'], 422);
                }
                $where = [! empty($input['archived']) ? 'archived_at IS NOT NULL' : 'archived_at IS NULL'];
                $params = [];
                if (! empty($input['insight'])) {
                    $insight = $input['insight'];
                    $allowed = ['all', 'outstanding', 'status', 'position', 'source', 'sex', 'age', 'civil_status', 'nationality', 'education', 'course', 'employment', 'province', 'city', 'salary', 'licensure', 'cif_readiness', 'hr_readiness', 'month', 'day', 'week', 'year'];
                    if (! is_array($insight) || ! is_string($insight['field'] ?? null) || ! in_array($insight['field'], $allowed, true) || ! is_string($insight['value'] ?? null) || mb_strlen($insight['value']) > 500 || ! in_array($insight['range'] ?? null, ['all', '30', '90', '365'], true)) {
                        return response()->json(['error' => 'Invalid dashboard drill-down.'], 422);
                    }
                    try {
                        $coverage = DashboardCoverage::cifDashboardCoverage($insight);
                    } catch (InvalidArgumentException $e) {
                        return response()->json(['error' => $e->getMessage()], 422);
                    }
                    if ($insight['field'] === 'outstanding') {
                        $ids = RecruitmentDashboard::cifDashboardCohort($pdo, 'candidates_outstanding', $insight['range'], $position, (int) session()->get('admin'.'.id'), null, $coverage)['ids'];
                    } else {
                        $scope = CandidateAnalytics::cifAnalyticsScope(CandidateAnalytics::cifAnalyticsRows($pdo), $insight['range'], $position, $coverage);
                        $ids = array_column(array_filter($scope, static fn ($row) => $insight['field'] === 'all' || $row['dimensions'][$insight['field']] === $insight['value']), 'id');
                    }
                    $where[] = $ids ? 'id IN ('.implode(',', array_map('intval', $ids)).')' : '1 = 0';
                }
                if ($search !== '') {
                    $where[] = "(LOCATE(?, CONCAT_WS(' ', first_name, middle_name, last_name)) > 0 OR LOCATE(?, CONCAT_WS(', ', last_name, first_name)) > 0 OR LOCATE(?, email) > 0 OR LOCATE(?, candidate_number) > 0 OR LOCATE(?, COALESCE(position, '')) > 0 OR LOCATE(?, COALESCE(legacy_candidate_number, '')) > 0)";
                    $params = array_fill(0, 6, $search);
                }
                if ($status !== '') {
                    $where[] = 'status = ?';
                    $params[] = $status;
                }
                if ($position !== '') {
                    $where[] = 'position = ?';
                    $params[] = $position;
                }
                try {
                    [$stageWhere,$stageParams] = CandidateListStages::cifCandidateListStageFilter($input['stage'] ?? '', $input['stage_status'] ?? '');
                } catch (InvalidArgumentException $e) {
                    return response()->json(['error' => $e->getMessage()], 422);
                }
                $where = array_merge($where, $stageWhere);
                $params = array_merge($params, $stageParams);
                $filter = ' WHERE '.implode(' AND ', $where);
                $query = $pdo->prepare('SELECT COUNT(*) FROM candidates'.$filter);
                $query->execute($params);
                $total = (int) $query->fetchColumn();
                $pages = max(1, (int) ceil($total / 8));
                $page = min($pages, max(1, (int) ($input['page'] ?? 1)));
                $offset = ($page - 1) * 8;
                $sort = ['n+' => 'last_name ASC, first_name ASC', 'n-' => 'last_name DESC, first_name DESC', 'd+' => 'submitted_at ASC', 'd-' => 'submitted_at DESC', 'id+' => 'candidate_number ASC', 'id-' => 'candidate_number DESC'][$input['sort'] ?? 'd-'] ?? 'submitted_at DESC';
                $stageColumns = '';
                foreach (array_keys(CandidateListStages::cifCandidateListStageCatalogue()) as $stage) {
                    $stageColumns .= ',('.CandidateListStages::cifCandidateListDecisionSql($stage).') AS decision_'.$stage;
                }
                foreach (['department_interview' => 'department_required', 'lie_detection' => 'ldt_required'] as $stage => $flag) {
                    $stageColumns .= ",COALESCE((SELECT required.$flag FROM candidate_assessment_requirements required WHERE required.candidate_id=candidates.id),1) AS required_$stage";
                }
                $query = $pdo->prepare("SELECT id, candidate_number, first_name, middle_name, last_name, email, position, status, submitted_at, archived_at$stageColumns FROM candidates$filter ORDER BY $sort, id DESC LIMIT 8 OFFSET $offset");
                $query->execute($params);
                $summary = array_fill_keys(array_merge(['Total'], $statuses, ['Archived']), 0);
                foreach ($pdo->query('SELECT status, archived_at IS NOT NULL AS archived, COUNT(*) AS count FROM candidates GROUP BY status, archived_at IS NOT NULL') as $row) {
                    $count = (int) $row['count'];
                    if ($row['archived']) {
                        $summary['Archived'] += $count;
                    } else {
                        $summary['Total'] += $count;
                        $summary[$row['status']] = ($summary[$row['status']] ?? 0) + $count;
                    }
                }
                $candidates = $query->fetchAll();
                foreach ($candidates as &$candidate) {
                    $candidate['stages'] = CandidateListStages::cifCandidateListStageRows($candidate);
                    foreach (array_keys($candidate) as $key) {
                        if (str_starts_with($key, 'decision_') || str_starts_with($key, 'required_')) {
                            unset($candidate[$key]);
                        }
                    }
                }
                unset($candidate);

                return response()->json(['candidates' => $candidates, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => 8,
                    'active_count' => $summary['Total'], 'summary' => $summary,
                    'positions' => HiringPositions::cifAppliedPositions()]);
            }
            if (in_array($action, ['generate', 'regenerate_code', 'invitations', 'invitation_archive'], true)) {
                if (empty(session()->get('admin'))) {
                    return response()->json(['error' => 'Sign in as HR to continue.'], 401);
                }
                if ($action === 'invitation_archive') {
                    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if (! $id || ! is_bool($input['archived'] ?? null)) {
                        return response()->json(['error' => 'Invalid invitation archive request.'], 422);
                    }
                    $pdo = RecruitmentDatabase::pdo();
                    $pdo->beginTransaction();
                    $query = $pdo->prepare('SELECT email FROM invitations WHERE id = ?');
                    $query->execute([$id]);
                    $invitationEmail = $query->fetchColumn();
                    if ($invitationEmail === false) {
                        $pdo->rollBack();

                        return response()->json(['error' => 'Invitation not found.'], 404);
                    }
                    // Match generation's lock order so archiving/restoring and new codes
                    // for the same email cannot create multiple active invitations.
                    $query = $pdo->prepare('SELECT id, archived_at FROM invitations WHERE email = ? ORDER BY id FOR UPDATE');
                    $query->execute([$invitationEmail]);
                    $emailInvitations = $query->fetchAll();
                    if (! in_array((string) $id, array_map(static fn (array $row): string => (string) $row['id'], $emailInvitations), true)) {
                        $pdo->rollBack();

                        return response()->json(['error' => 'Invitation not found.'], 404);
                    }
                    if (! $input['archived']) {
                        foreach ($emailInvitations as $invitation) {
                            if ((int) $invitation['id'] !== $id && $invitation['archived_at'] === null) {
                                $pdo->rollBack();

                                return response()->json(['error' => 'This email already has an active invitation. Archive it before restoring this invitation.'], 409);
                            }
                        }
                    }
                    if ($input['archived']) {
                        $query = $pdo->prepare('UPDATE invitations SET archived_at = COALESCE(archived_at, UTC_TIMESTAMP()), archived_by = COALESCE(archived_by, ?) WHERE id = ?');
                        $query->execute([session()->get('admin'.'.id'), $id]);
                    } else {
                        $query = $pdo->prepare('UPDATE invitations SET archived_at = NULL, archived_by = NULL WHERE id = ?');
                        $query->execute([$id]);
                    }
                    $pdo->commit();

                    return response()->json(['ok' => true]);
                }
                if ($action === 'invitations') {
                    $pdo = RecruitmentDatabase::pdo();
                    $statusSql = ApplicantAccess::cifInvitationStatusSql();
                    $search = trim(is_string($input['search'] ?? null) ? $input['search'] : '');
                    $status = is_string($input['status'] ?? null) ? $input['status'] : '';
                    $position = is_string($input['position'] ?? null) ? trim($input['position']) : '';
                    $sorts = ['created_desc' => 'created_at DESC', 'created_asc' => 'created_at ASC', 'expires_desc' => 'expires_at DESC', 'expires_asc' => 'expires_at ASC', 'login_desc' => 'last_login_at DESC', 'login_asc' => 'last_login_at ASC'];
                    $sortKey = $input['sort'] ?? 'created_desc';
                    $archived = $input['archived'] ?? false;
                    if (! is_bool($archived) || mb_strlen($search) > 254 || ($position !== '' && ! in_array($position, HiringPositions::cifAppliedPositions(), true)) || ! is_string($sortKey) || ! isset($sorts[$sortKey]) || ! in_array($status, ['', 'Pending', 'In Progress', 'Submitted', 'Expired'], true)) {
                        return response()->json(['error' => 'Invalid invitation filters.'], 422);
                    }
                    $where = [$archived ? 'archived_at IS NOT NULL' : 'archived_at IS NULL'];
                    $params = [];
                    if ($search !== '') {
                        $where[] = "(LOCATE(?, email) > 0 OR LOCATE(?, COALESCE(position, '')) > 0 OR LOCATE(?, COALESCE(applicant_id, '')) > 0 OR LOCATE(?, COALESCE(access_code, '')) > 0)";
                        $params = [$search, $search, $search, ApplicantAccess::normalizeCifInvitationCode($search)];
                    }
                    if ($status !== '') {
                        $where[] = "($statusSql) = ?";
                        $params[] = $status;
                    }
                    if ($position !== '') {
                        $where[] = 'position = ?';
                        $params[] = $position;
                    }
                    if (isset($input['dashboard_metric'])) {
                        $metric = $input['dashboard_metric'];
                        $range = $input['dashboard_range'] ?? 'all';
                        if (! is_string($metric) || ! in_array($metric, ['invitations_total', 'invitations_outstanding'], true) || ! is_string($range)) {
                            return response()->json(['error' => 'Invalid dashboard selection.'], 422);
                        }
                        try {
                            $ids = RecruitmentDashboard::cifDashboardCohort($pdo, $metric, $range, $position, (int) session()->get('admin'.'.id'), null, DashboardCoverage::cifDashboardCoverage($input, 'dashboard_'))['ids'];
                        } catch (InvalidArgumentException $e) {
                            return response()->json(['error' => $e->getMessage()], 422);
                        }
                        $where[] = $ids ? 'id IN ('.implode(',', $ids).')' : '1=0';
                    }
                    $filter = $where ? ' WHERE '.implode(' AND ', $where) : '';
                    $query = $pdo->prepare('SELECT COUNT(*) FROM invitations'.$filter);
                    $query->execute($params);
                    $total = (int) $query->fetchColumn();
                    $perPage = 10;
                    $pages = max(1, (int) ceil($total / $perPage));
                    $page = min($pages, max(1, (int) ($input['page'] ?? 1)));
                    $offset = ($page - 1) * $perPage;
                    $sort = $sorts[$sortKey];
                    $query = $pdo->prepare('SELECT id, email, position, access_code, applicant_id, expires_at, NOT '.ApplicantAccess::cifInvitationCodeValidSql()." AS code_expired, created_at, last_login_at, redeemed_at, submitted_at, archived_at, $statusSql AS status FROM invitations$filter ORDER BY $sort, id DESC LIMIT $perPage OFFSET $offset");
                    $query->execute($params);
                    $rows = $query->fetchAll();
                    $summary = ['Total' => 0, 'Pending' => 0, 'In Progress' => 0, 'Submitted' => 0, 'Expired' => 0];
                    foreach ($pdo->query("SELECT $statusSql AS status, COUNT(*) AS count FROM invitations WHERE archived_at IS NULL GROUP BY status")->fetchAll() as $row) {
                        $summary[$row['status']] = (int) $row['count'];
                        $summary['Total'] += (int) $row['count'];
                    }
                    $summary['Archived'] = (int) $pdo->query('SELECT COUNT(*) FROM invitations WHERE archived_at IS NOT NULL')->fetchColumn();

                    return response()->json(['invitations' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage, 'summary' => $summary, 'sort' => $sortKey,
                        'positions' => HiringPositions::cifAppliedPositions()]);
                }
                if ($action === 'regenerate_code') {
                    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if (! $id) {
                        return response()->json(['error' => 'Select a valid invitation.'], 422);
                    }
                    $pdo = RecruitmentDatabase::pdo();
                    $pdo->beginTransaction();
                    $query = $pdo->prepare('SELECT email, redeemed_at, submitted_at FROM invitations WHERE id=? FOR UPDATE');
                    $query->execute([$id]);
                    $invitation = $query->fetch();
                    if (! $invitation) {
                        $pdo->rollBack();

                        return response()->json(['error' => 'Invitation not found.'], 404);
                    }
                    if ($invitation['redeemed_at'] || $invitation['submitted_at']) {
                        $pdo->rollBack();

                        return response()->json(['error' => 'This invitation has already been opened or submitted and cannot receive a replacement code. Its existing code resumes saved progress before the form deadline or provides read-only tracking after submission.'], 409);
                    }
                    $code = ApplicantAccess::generateCifInvitationCode($pdo);
                    $expires = ApplicantAccess::cifInvitationExpiry();
                    $query = $pdo->prepare('UPDATE invitations SET code_hash=?,access_code=?,expires_at=? WHERE id=?');
                    $query->execute([hash('sha256', $code), $code, $expires, $id]);
                    $pdo->commit();

                    return response()->json(['email' => $invitation['email'], 'code' => $code, 'expires_at' => $expires, 'expires_in_days' => ApplicantAccess::CIF_INVITATION_DAYS]);
                }
                if (strlen($email) > 254 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return response()->json(['error' => 'Enter a valid candidate email.'], 422);
                }
                $position = trim(is_string($input['position'] ?? null) ? $input['position'] : '');
                try {
                    $position = HiringPositions::validateCifAppliedPosition($position);
                } catch (InvalidArgumentException $e) {
                    return response()->json(['error' => $e->getMessage()], 422);
                }
                $pdo = RecruitmentDatabase::pdo();
                $pdo->beginTransaction();
                $query = $pdo->prepare('SELECT id, archived_at FROM invitations WHERE email = ? ORDER BY id FOR UPDATE');
                $query->execute([$email]);
                foreach ($query->fetchAll() as $invitation) {
                    if ($invitation['archived_at'] === null) {
                        $pdo->rollBack();

                        return response()->json(['error' => 'This email already has an active invitation. Use it, or archive it before creating a new invitation.'], 409);
                    }
                }
                $code = ApplicantAccess::generateCifInvitationCode($pdo);
                $expires = ApplicantAccess::cifInvitationExpiry();
                $query = $pdo->prepare('INSERT INTO invitations (email, code_hash, access_code, expires_at, position, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())');
                $query->execute([$email, hash('sha256', $code), $code, $expires, $position, session()->get('admin'.'.id')]);
                $invitationId = (int) $pdo->lastInsertId();
                $pdo->commit();

                return response()->json(['id' => $invitationId, 'email' => $email, 'code' => $code, 'expires_at' => $expires, 'expires_in_days' => ApplicantAccess::CIF_INVITATION_DAYS, 'link' => '../candidate/']);
            }
            if ($action === 'candidate_logout') {
                if (! empty(session()->get('invitation_id'))) {
                    $id = (int) session()->get('invitation_id');
                    $query = RecruitmentDatabase::pdo()->prepare('SELECT email FROM invitations WHERE id = ?');
                    $query->execute([$id]);
                    $candidateEmail = (string) ($query->fetchColumn() ?: '');
                    Security::cifAuditContext(RecruitmentDatabase::pdo(), 'Candidate', $id, $candidateEmail);
                    Security::cifAuditWrite(RecruitmentDatabase::pdo(), 'Access', 'Candidate exited', 'Invitation', (string) $id, $candidateEmail);
                }
                session()->forget(['invitation_id', 'candidate_until', 'candidate_code_hash', 'candidate_access_version']);
                session()->migrate(true);

                return response()->json(['ok' => true]);
            }
            if ($action === 'candidate_progress') {
                $candidate = CandidatePortal::candidateState();
                if (! $candidate) {
                    return response()->json(['error' => 'Re-enter your email and invitation code to view your application.'], 401);
                }
                if ($candidate['mode'] !== 'tracking') {
                    return response()->json(['error' => 'Submit your CIF before tracking your application.'], 409);
                }

                return response()->json(['candidate' => $candidate]);
            }
            if ($action === 'candidate_draft') {
                $candidate = CandidatePortal::candidateState();
                if (! $candidate) {
                    return response()->json(['error' => 'Your form session ended. Re-enter your email and code to continue. Your last saved progress is retained.'], 401);
                }
                if ($candidate['mode'] !== 'form') {
                    return response()->json(['error' => 'Your CIF is already submitted and is read-only. Refresh to track your application.'], 409);
                }
                $revision = $input['revision'] ?? null;
                $step = $input['step'] ?? null;
                if (! is_array($input['form'] ?? null) || ! is_int($revision) || $revision < 0 || ! is_int($step) || $step < 0 || $step >= count(CifFields::cifDefinition()['sections']) || (isset($input['preserve_photo']) && ! is_bool($input['preserve_photo']))) {
                    return response()->json(['error' => 'Invalid progress save. Refresh before trying again.'], 422);
                }
                $pdo = RecruitmentDatabase::pdo();
                $pdo->beginTransaction();
                $query = $pdo->prepare('SELECT draft_revision, draft_form, submitted_at, redeemed_at, code_hash,access_enabled,access_version, '.ApplicantAccess::cifInvitationCodeValidSql().' AS valid_code FROM invitations WHERE id = ? FOR UPDATE');
                $query->execute([session()->get('invitation_id')]);
                $invitation = $query->fetch();
                if (! $invitation || ! (bool) $invitation['access_enabled'] || (int) $invitation['access_version'] !== (int) (session()->get('candidate_access_version') ?? 0) || ! $invitation['redeemed_at'] || ! $invitation['valid_code'] || ! hash_equals((string) $invitation['code_hash'], (string) session()->get('candidate_code_hash'))) {
                    $pdo->rollBack();

                    return response()->json(['error' => 'This invitation is no longer available for editing. Your last saved draft is retained. Contact HR for help.'], 401);
                }
                if ($invitation['submitted_at']) {
                    $pdo->rollBack();

                    return response()->json(['error' => 'Your CIF is submitted and can no longer be changed. Refresh for application tracking.'], 409);
                }
                if ((int) $invitation['draft_revision'] !== $revision) {
                    $pdo->rollBack();

                    return response()->json(['error' => 'Your progress was updated in another tab or device. Reload the saved draft before continuing to avoid overwriting it.'], 409);
                }
                $posted = $input['form'];
                if (($input['preserve_photo'] ?? false) === true) {
                    $previous = $invitation['draft_form'] ? json_decode($invitation['draft_form'], true, 32, JSON_THROW_ON_ERROR) : [];
                    $posted['id_photo'] = $previous['id_photo'] ?? '';
                }
                try {
                    $form = ApplicantAccess::validateCifDraft($posted);
                } catch (InvalidArgumentException $e) {
                    $pdo->rollBack();

                    return response()->json(['error' => $e->getMessage()], 422);
                }
                $query = $pdo->prepare('UPDATE invitations SET draft_form = ?, draft_step = ?, draft_revision = draft_revision + 1, draft_saved_at = UTC_TIMESTAMP() WHERE id = ?');
                $query->execute([json_encode($form, JSON_THROW_ON_ERROR), $step, session()->get('invitation_id')]);
                $query = $pdo->prepare('SELECT draft_saved_at FROM invitations WHERE id = ?');
                $query->execute([session()->get('invitation_id')]);
                $savedAt = $query->fetchColumn();
                $pdo->commit();

                return response()->json(['ok' => true, 'revision' => $revision + 1, 'saved_at' => $savedAt]);
            }
            if ($action === 'verify') {
                AccessLimiter::check('candidate');
                $credential = ApplicantAccess::normalizeCifInvitationCode($input['code'] ?? null);
                if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! preg_match('/^[A-Z0-9]{6,8}$/D', $credential)) {
                    return response()->json(['error' => 'Email or invitation code is incorrect.'], 401);
                }
                $pdo = RecruitmentDatabase::pdo();
                $pdo->beginTransaction();
                // Applicant IDs identify records; they are deliberately NOT credentials.
                $query = $pdo->prepare('SELECT i.id, i.redeemed_at, i.submitted_at,i.access_version FROM invitations i WHERE i.email = ? AND i.code_hash = ? AND i.access_enabled=1 AND ('.ApplicantAccess::cifInvitationCodeValidSql('i').' OR EXISTS (SELECT 1 FROM candidates c WHERE c.invitation_id = i.id)) ORDER BY i.id DESC LIMIT 1 FOR UPDATE');
                $codeHash = hash('sha256', $credential);
                $query->execute([$email, $codeHash]);
                $invitation = $query->fetch();
                if (! $invitation) {
                    $pdo->rollBack();
                    Security::cifAuditWrite($pdo, 'Access', 'Candidate sign-in failed', 'Invitation', '', $email, [], 'Failed');

                    return response()->json(['error' => 'Email or invitation code is incorrect, or the unused code has expired. Contact HR if you need help.'], 401);
                }
                $id = (int) $invitation['id'];
                Security::cifAuditContext($pdo, 'Candidate', $id, $email);
                // First access is retained. After submission this credential is tracking-only.
                $query = $pdo->prepare('UPDATE invitations SET redeemed_at = COALESCE(redeemed_at, UTC_TIMESTAMP()), last_login_at = UTC_TIMESTAMP() WHERE id = ?');
                $query->execute([$id]);
                $pdo->commit();
                session()->migrate(true);
                session()->put('invitation_id', (int) $id);
                session()->put('candidate_code_hash', $codeHash);
                session()->put('candidate_access_version', (int) $invitation['access_version']);
                session()->put('candidate_until', time() + 86400);
                Security::cifAuditWrite($pdo, 'Access', 'Candidate signed in', 'Invitation', (string) $id, $email);

                return response()->json(['candidate' => CandidatePortal::candidateState()]);
            }
            if ($action === 'submit') {
                $candidate = CandidatePortal::candidateState();
                if (! $candidate) {
                    return response()->json(['error' => 'Your form session has ended. Re-enter your email and code to resume saved progress or track your submitted application.'], 401);
                }
                if ($candidate['mode'] !== 'form') {
                    return response()->json(['error' => 'Your CIF is already submitted. It is read-only; use application tracking to view progress.'], 409);
                }
                if (! is_int($input['draft_revision'] ?? null) || $input['draft_revision'] < 0) {
                    return response()->json(['error' => 'Refresh your saved CIF before submitting.'], 422);
                }
                if (in_array($candidate['position'], HiringPositions::cifAppliedPositions(), true)) {
                    $input['desired_position'] = $candidate['position'];
                }
                if (! array_key_exists('contact_email', $input)) {
                    $input['contact_email'] = $candidate['email'];
                }
                try {
                    $form = CandidateStorage::validateCandidateCif($input);
                    $form['fn'] = ApplicantAccess::cifApplicantName($form['fn'], 'first name');
                    $form['ln'] = ApplicantAccess::cifApplicantName($form['ln'], 'surname');
                } catch (InvalidArgumentException $e) {
                    return response()->json(['error' => $e->getMessage()], 422);
                }
                $pdo = RecruitmentDatabase::pdo();
                $pdo->beginTransaction();
                $query = $pdo->prepare('SELECT email, position, applicant_id, submitted_at, redeemed_at, code_hash,access_enabled,access_version, draft_revision, '.ApplicantAccess::cifInvitationCodeValidSql().' AS valid_code FROM invitations WHERE id = ? FOR UPDATE');
                $query->execute([session()->get('invitation_id')]);
                $invitation = $query->fetch();
                if (! $invitation || $invitation['submitted_at'] !== null) {
                    $pdo->rollBack();

                    return response()->json(['error' => 'This form has already been submitted.'], 409);
                }
                if (! (bool) $invitation['access_enabled'] || (int) $invitation['access_version'] !== (int) (session()->get('candidate_access_version') ?? 0) || ! $invitation['redeemed_at'] || ! $invitation['valid_code'] || ! hash_equals($invitation['code_hash'], (string) (session()->get('candidate_code_hash') ?? ''))) {
                    $pdo->rollBack();

                    return response()->json(['error' => 'Your form session is no longer valid. Please contact HR for assistance.'], 401);
                }
                if ((int) $invitation['draft_revision'] !== $input['draft_revision']) {
                    $pdo->rollBack();

                    return response()->json(['error' => 'Saved progress changed in another tab or device. Reload before submitting to avoid replacing newer answers.'], 409);
                }
                $form['email'] = $invitation['email'];
                $form['position'] = $form['desired_position'];
                $number = $invitation['applicant_id'] ?: ApplicantAccess::assignCifApplicantId($pdo, (int) session()->get('invitation_id'), $form['fn'], $form['ln']);
                $query = $pdo->prepare('INSERT INTO candidates (candidate_number, invitation_id, email, first_name, middle_name, last_name, position, form_data, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())');
                $query->execute([$number, session()->get('invitation_id'), $invitation['email'], $form['fn'], $form['mn'] ?: null, $form['ln'], $form['position'], json_encode($form, JSON_THROW_ON_ERROR)]);
                CandidateStorage::saveCifDetails($pdo, (int) $pdo->lastInsertId(), $form);
                $query = $pdo->prepare('UPDATE invitations SET submitted_at = UTC_TIMESTAMP(), draft_form = NULL, draft_step = 0, draft_revision = draft_revision + 1, draft_saved_at = NULL WHERE id = ?');
                $query->execute([session()->get('invitation_id')]);
                $pdo->commit();

                return response()->json(['ok' => true, 'candidate_number' => $number, 'candidate' => CandidatePortal::candidateState()]);
            }

            return response()->json(['error' => 'Unknown action.'], 404);
        } catch (JsonException $e) {
            return response()->json(['error' => 'Invalid request data.'], 400);
        } catch (Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('CIF access: '.$e->getMessage());

            return response()->json(['error' => 'Unable to complete the request. Please try again or contact HR.'], 500);
        }

    }
}
