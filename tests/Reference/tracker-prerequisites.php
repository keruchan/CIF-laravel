<?php

declare(strict_types=1);
use App\Services\Recruitment\CandidateInterviews;
use App\Services\Recruitment\CandidatePortal;
use App\Services\Recruitment\CandidatePublication;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
date_default_timezone_set('Asia/Manila');

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function trackerPrerequisiteCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
$candidate = ['candidate_number' => 'TRACK2601', 'position' => 'Accountant', 'submitted_at' => '2026-11-01 01:00:00', 'archived_at' => null, 'interviews' => []];
$baseline = CandidatePublication::cifPublicFacts($candidate);
$passed = ['decision' => 'Passed', 'completed_at' => '2026-11-02 02:00:00', 'revision' => 1];
foreach (CandidateInterviews::cifInterviewDecisions() as $decision) {
    $facts = $baseline;
    $facts['released_stage'] = 'initial_interview';
    $facts['interviews']['initial_interview'] = array_replace($passed, ['decision' => $decision]);
    $before = json_encode($facts);
    $progress = CandidatePortal::cifCandidateTrackerProgress(CandidatePublication::cifProgressFromPublicFacts($facts));
    $steps = array_column($progress['steps'], null, 'key');
    trackerPrerequisiteCheck($steps['cif']['state'] === 'completed' && $steps['cif']['status'] === 'Passed' && $steps['cif']['label'] === 'SCREENING', 'Every published Interview decision passes Screening');
    trackerPrerequisiteCheck($steps['initial_interview']['status'] === $decision && $steps['initial_interview']['connected'], 'Keep the published Interview result and connect it to Screening');
    trackerPrerequisiteCheck($progress['completed'] === ($decision === 'Passed' ? 2 : 1), 'Correct completed count for '.$decision);
    trackerPrerequisiteCheck(json_encode($facts) === $before, 'Reading an earlier publication does not rewrite its stored facts');
}
foreach ([false, true] as $department) {
    foreach ([false, true] as $background) {
        foreach (['department_interview', 'lie_detection', 'job_offer', 'deployment'] as $stage) {
            $facts = $baseline;
            $facts['released_stage'] = $stage;
            $facts['assessment_requirements_confirmed'] = true;
            $facts['assessment_requirements'] = ['department_required' => $department, 'ldt_required' => $background, 'revision' => 1];
            if (in_array($stage, ['department_interview', 'lie_detection'], true)) {
                if (! ($stage === 'department_interview' ? $department : $background)) {
                    continue;
                }
                $facts['interviews'][$stage] = ['decision' => 'Pooling', 'completed_at' => null, 'revision' => 1];
            }
            $tracker = CandidatePortal::cifCandidateTrackerProgress(CandidatePublication::cifProgressFromPublicFacts($facts));
            $steps = array_column($tracker['steps'], null, 'key');
            trackerPrerequisiteCheck($steps['cif']['status'] === 'Passed' && $steps['initial_interview']['status'] === 'Passed', 'Later stages complete Screening and Interview');
            trackerPrerequisiteCheck($tracker['total'] === 4 + (int) $department + (int) $background, 'Skipped assessments do not count');
            if (in_array($stage, ['department_interview', 'lie_detection'], true)) {
                $other = $stage === 'department_interview' ? 'lie_detection' : 'department_interview';
                trackerPrerequisiteCheck($steps[$stage]['status'] === 'Pooling', 'Preserve selected assessment result');
                trackerPrerequisiteCheck(! isset($steps[$other]) || $steps[$other]['state'] === 'pending', 'Parallel assessment is not automatically passed');
                trackerPrerequisiteCheck($tracker['completed'] === 2, 'Only the common prerequisite stages pass');
            } else {
                foreach (['department_interview' => $department, 'lie_detection' => $background] as $key => $required) {
                    trackerPrerequisiteCheck(isset($steps[$key]) === $required, 'Only required assessment stages appear');
                    if ($required) {
                        trackerPrerequisiteCheck($steps[$key]['status'] === 'Passed', 'Offer and Deployment pass required assessments');
                    }
                }
                trackerPrerequisiteCheck($tracker['completed'] === 2 + (int) $department + (int) $background + (int) ($stage === 'deployment'), 'Consistent progress count at later stage');
                if ($stage === 'deployment') {
                    trackerPrerequisiteCheck($steps['job_offer']['status'] === 'Accepted', 'Deployment implies an accepted offer');
                }
            }
        }
    }
}
// Reproduce the screenshot from a stored public Interview pass with no Screening release.
$facts = $baseline;
$facts['released_stage'] = 'initial_interview';
$facts['interviews']['initial_interview'] = $passed;
$facts['assessment_requirements'] = ['department_required' => false, 'ldt_required' => true, 'revision' => 1];
$facts['assessment_requirements_confirmed'] = true;
$tracker = CandidatePortal::cifCandidateTrackerProgress(CandidatePublication::cifProgressFromPublicFacts($facts));
trackerPrerequisiteCheck($tracker['status'] === 'Background Check' && $tracker['completed'] === 2 && $tracker['total'] === 5, 'Screenshot route is corrected to two of five completed');
$legacy = $tracker;
foreach ($legacy['steps'] as &$step) {
    if ($step['key'] === 'cif') {
        $step['state'] = 'pending';
        $step['status'] = 'Not started';
        $step['label'] = 'CIF screening';
    }
} unset($step);
$legacy['completed'] = 1;
$history = [['stage' => 'initial_interview', 'published_at' => '2026-11-03 03:00:00', 'progress_json' => json_encode($legacy)]];
$withTimes = CandidatePortal::cifCandidateTrackerUpdateTimes($tracker, $history);
$steps = array_column($withTimes['steps'], null, 'key');
trackerPrerequisiteCheck($steps['cif']['updated_at'] === '2026-11-03 03:00:00', 'Inferred Screening uses the existing release time');
// A later release of Screening failure must still display the failure.
$facts['released_stage'] = 'cif';
$facts['interviews']['cif'] = array_replace($passed, ['decision' => 'Failed']);
$tracker = CandidatePortal::cifCandidateTrackerProgress(CandidatePublication::cifProgressFromPublicFacts($facts));
$steps = array_column($tracker['steps'], null, 'key');
trackerPrerequisiteCheck($tracker['status'] === 'Screening Failed' && $steps['cif']['status'] === 'Failed', 'A newly shared Screening failure is not replaced by an old Interview pass');
echo "PASS: prior-stage completion for all Interview decisions, screenshot recovery, parallel assessments, skipped stages, counts/connectors, release timestamps and later Screening failures.\n";
