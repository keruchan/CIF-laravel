<?php

declare(strict_types=1);
use App\Services\Recruitment\CandidateInterviews;
use App\Services\Recruitment\CandidatePortal;
use App\Services\Recruitment\CandidatePublication;
use App\Services\Recruitment\HrStaff;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
date_default_timezone_set('Asia/Manila');

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function trackerRequirementCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
$passed = ['decision' => 'Passed', 'completed_at' => '2026-10-22 02:00:00', 'revision' => 1];
$candidate = ['candidate_number' => 'AQUA2601', 'position' => 'Accounting Assistant', 'submitted_at' => '2026-10-21 01:00:00', 'archived_at' => null,
    'interviews' => ['cif' => $passed, 'initial_interview' => $passed], 'hr' => ['hr_notes' => 'PRIVATE-HR-NOTE']];
$cases = [];
foreach (['screening', 'interview_saved'] as $phase) {
    foreach ([false, true] as $department) {
        foreach ([false, true] as $background) {
            $unfinished = $candidate;
            $unfinished['assessment_requirements'] = ['department_required' => $department, 'ldt_required' => $background, 'revision' => 1];
            if ($phase === 'screening') {
                $unfinished['interviews'] = [];
            } else {
                $unfinished['interviews']['initial_interview'] = array_replace($passed, ['completed_at' => null]);
            }
            $tracker = CandidatePortal::cifCandidateTrackerProgress(CandidatePublication::cifProgressFromPublicFacts(CandidatePublication::cifPublicFacts($unfinished)));
            trackerRequirementCheck($tracker['interview_completed'] === false && $tracker['total'] === 4, 'Both assessments stay hidden before Interview is finished, regardless of provisional checkbox choices');
            foreach (['department_interview', 'lie_detection'] as $key) {
                $step = array_values(array_filter($tracker['steps'], static fn (array $step): bool => $step['key'] === $key))[0] ?? null;
                trackerRequirementCheck($step === null, 'Provisional assessments are not included in the candidate roadmap');
                $scheduled = CandidatePublication::cifProgressFromPublicFacts(CandidatePublication::cifPublicFacts($unfinished));
                $scheduled['next_schedule'] = ['stage' => $key, 'label' => HrStaff::cifHrStages()[$key], 'scheduled_at' => '2026-10-23 02:00:00'];
                trackerRequirementCheck(CandidatePortal::cifCandidateTrackerProgress($scheduled)['next_schedule'] === null, 'Unconfirmed assessment schedules stay hidden');
            }
            $cases[] = ['phase' => $phase, 'department' => $department, 'background' => $background, 'progress' => $tracker];
        }
    }
}
foreach ([false, true] as $department) {
    foreach ([false, true] as $background) {
        $candidate['assessment_requirements'] = ['department_required' => $department, 'ldt_required' => $background, 'revision' => 1];
        $facts = CandidatePublication::cifPublicFacts($candidate);
        $raw = CandidatePublication::cifProgressFromPublicFacts($facts);
        $tracker = CandidatePortal::cifCandidateTrackerProgress($raw);
        $expected = ['cif', 'initial_interview'];
        if ($department) {
            $expected[] = 'department_interview';
        }
        if ($background) {
            $expected[] = 'lie_detection';
        }
        array_push($expected, 'job_offer', 'deployment');
        trackerRequirementCheck(array_column($tracker['steps'], 'key') === $expected, 'Only selected assessments appear in candidate roadmap');
        trackerRequirementCheck($tracker['total'] === 4 + (int) $department + (int) $background && $tracker['completed'] === 2, 'Selected assessments control phase count without inventing passes');
        $status = $department && $background ? 'Department Interview & Background Check' : ($department ? 'Department Interview' : ($background ? 'Background Check' : 'Job Offer'));
        trackerRequirementCheck($tracker['status'] === $status, 'Current phase names the required assessment(s)');
        trackerRequirementCheck(! str_contains(json_encode($tracker), 'PRIVATE-'), 'No HR notes exposed');
        foreach (['department_interview' => $department, 'lie_detection' => $background] as $stage => $required) {
            $scheduled = $raw;
            $scheduled['next_schedule'] = ['stage' => $stage, 'label' => HrStaff::cifHrStages()[$stage], 'scheduled_at' => '2026-10-23 02:00:00'];
            trackerRequirementCheck((CandidatePortal::cifCandidateTrackerProgress($scheduled)['next_schedule'] !== null) === $required, 'Only required assessment schedules appear');
        }
        $legacy = $raw;
        unset($legacy['required_assessments']);
        trackerRequirementCheck(array_column(CandidatePortal::cifCandidateTrackerProgress($legacy)['steps'], 'key') === $expected, 'Historical snapshots use Not required flags correctly');
        $history = [['stage' => 'initial_interview', 'published_at' => '2026-10-22 04:00:00', 'progress_json' => json_encode($raw, JSON_THROW_ON_ERROR)]];
        $cases[] = ['phase' => 'finished', 'department' => $department, 'background' => $background, 'progress' => CandidatePortal::cifCandidateTrackerUpdateTimes($tracker, $history)];
    }
}
foreach (CandidateInterviews::cifInterviewDecisions() as $decision) {
    $finished = $candidate;
    $finished['assessment_requirements'] = ['department_required' => false, 'ldt_required' => true, 'revision' => 1];
    $finished['interviews']['initial_interview'] = array_replace($passed, ['decision' => $decision]);
    $tracker = CandidatePortal::cifCandidateTrackerProgress(CandidatePublication::cifProgressFromPublicFacts(CandidatePublication::cifPublicFacts($finished)));
    trackerRequirementCheck($tracker['interview_completed'] === true && $tracker['total'] === 5 && ! in_array('department_interview', array_column($tracker['steps'], 'key'), true), 'Finished Interview confirms checkbox choices regardless of decision');
}
$unfinished = $candidate;
$unfinished['assessment_requirements'] = ['department_required' => false, 'ldt_required' => false, 'revision' => 1];
$unfinished['interviews']['initial_interview'] = array_replace($passed, ['completed_at' => null]);
$unfinishedPublic = ['state' => CandidatePublication::cifPublicFacts($unfinished)];
$finished = $unfinished;
$finished['interviews']['initial_interview'] = $passed;
$stillProvisional = CandidatePortal::cifCandidateTrackerProgress(CandidatePublication::cifProgressFromPublicFacts(CandidatePublication::cifProposedStageRelease($finished, $unfinishedPublic, 'job_offer')));
trackerRequirementCheck($stillProvisional['total'] === 4 && $stillProvisional['interview_completed'] && $stillProvisional['assessment_requirements_confirmed'], 'Later-stage update confirms the route and passes its earlier Interview process');
$confirmed = CandidatePortal::cifCandidateTrackerProgress(CandidatePublication::cifProgressFromPublicFacts(CandidatePublication::cifProposedStageRelease($finished, $unfinishedPublic, 'initial_interview')));
trackerRequirementCheck($confirmed['total'] === 4 && $confirmed['interview_completed'], 'Finishing and releasing Interview confirms the selected route');
$candidate['assessment_requirements'] = ['department_required' => false, 'ldt_required' => false, 'revision' => 1];
$public = ['state' => CandidatePublication::cifPublicFacts($candidate)];
$candidate['assessment_requirements'] = ['department_required' => true, 'ldt_required' => true, 'revision' => 2];
$unpublished = CandidatePortal::cifCandidateTrackerProgress(CandidatePublication::cifProgressFromPublicFacts(CandidatePublication::cifProposedStageRelease($candidate, $public, 'job_offer')));
trackerRequirementCheck($unpublished['total'] === 6, 'A later-stage candidate update also publishes route choices after Interview is finished');
$published = CandidatePortal::cifCandidateTrackerProgress(CandidatePublication::cifProgressFromPublicFacts(CandidatePublication::cifProposedStageRelease($candidate, $public, 'initial_interview')));
trackerRequirementCheck($published['total'] === 6, 'Releasing Interview publishes selected assessment requirements');
$candidate['assessment_requirements'] = ['department_required' => false, 'ldt_required' => false, 'revision' => 3];
$notRequired = CandidatePortal::cifCandidateTrackerProgress(CandidatePublication::cifProgressFromPublicFacts(CandidatePublication::cifProposedStageRelease($candidate, $public, 'department_interview')));
trackerRequirementCheck(! in_array('department_interview', array_column($notRequired['steps'], 'key'), true), 'Direct release cannot expose an unchecked assessment');
// Reproduce Finish Interview -> auto-open Background check -> Update candidate.
foreach ([false, true] as $department) {
    foreach ([false, true] as $background) {
        $internal = $unfinished;
        $internal['assessment_requirements'] = ['department_required' => $department, 'ldt_required' => $background, 'revision' => 2];
        foreach (['department_interview', 'lie_detection', 'job_offer', 'deployment'] as $stage) {
            $before = CandidatePortal::cifCandidateTrackerProgress(CandidatePublication::cifProgressFromPublicFacts(CandidatePublication::cifProposedStageRelease($internal, $unfinishedPublic, $stage)));
            trackerRequirementCheck($before['total'] === 4 && ! $before['assessment_requirements_confirmed'], 'Checkboxes alone cannot expose optional stages before Interview finish');
            $finished = $internal;
            $finished['interviews']['initial_interview'] = $passed;
            $facts = CandidatePublication::cifProposedStageRelease($finished, $unfinishedPublic, $stage);
            trackerRequirementCheck($facts['interviews']['initial_interview'] === $unfinishedPublic['state']['interviews']['initial_interview'], 'Other-stage release never publishes Interview decision or completion');
            $after = CandidatePortal::cifCandidateTrackerProgress(CandidatePublication::cifProgressFromPublicFacts($facts));
            trackerRequirementCheck($after['total'] === 4 + (int) $department + (int) $background && $after['assessment_requirements_confirmed'], 'Update candidate after finish reveals exactly the checked stages from every later tab');
            foreach (['department_interview' => $department, 'lie_detection' => $background] as $key => $required) {
                trackerRequirementCheck(in_array($key, array_column($after['steps'], 'key'), true) === $required, 'Route membership matches checked requirements');
            }
        }
    }
}
if (($argv[1] ?? '') === '--json') {
    echo json_encode($cases, JSON_THROW_ON_ERROR);
} else {
    echo "PASS: both assessments hidden before Interview completion; all four confirmed routes after finish/release; conditional schedules/counts, all interview decisions, legacy snapshots and publication privacy.\n";
}
