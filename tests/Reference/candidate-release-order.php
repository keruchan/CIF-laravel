<?php

declare(strict_types=1);
use App\Services\Recruitment\CandidateDeployment;
use App\Services\Recruitment\CandidateInterviews;
use App\Services\Recruitment\CandidatePortal;
use App\Services\Recruitment\CandidatePublication;
use App\Services\Recruitment\CandidateSchedule;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
date_default_timezone_set('Asia/Manila');

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function releaseOrderCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
$candidate = ['candidate_number' => 'AB2601', 'position' => 'Accountant', 'submitted_at' => '2026-10-16 01:00:00', 'archived_at' => null, 'interviews' => []];
$public = ['state' => CandidatePublication::cifPublicFacts($candidate)];
foreach (['department_interview', 'lie_detection', 'job_offer', 'deployment'] as $stage) {
    ob_start();
    if ($stage === 'deployment') {
        CandidateDeployment::renderCifDeploymentPanel($candidate);
    } else {
        CandidateInterviews::renderCifInterviewPanel($candidate, $stage);
    }
    $markup = ob_get_clean();
    $dom = new DOMDocument;
    @$dom->loadHTML($markup);
    $xpath = new DOMXPath($dom);
    releaseOrderCheck($xpath->query('//button[@data-stage-publish]/ancestor::fieldset[@disabled]')->length === 0, 'Update action stays available inside a blocked module');
    releaseOrderCheck($xpath->query('//button[@data-stage-publish]')->length === 1, 'Update action is present');
    releaseOrderCheck($xpath->query('//button[@data-stage-save and @disabled and @data-prerequisite-blocked]')->length === 1, 'Blocked module cannot save');
    releaseOrderCheck($xpath->query('//button[@data-stage-finish and @disabled and @data-prerequisite-blocked]')->length === 1, 'Blocked module cannot finish');
}
foreach (['job_offer', 'deployment', 'initial_interview'] as $stage) {
    $facts = CandidatePublication::cifProposedStageRelease($candidate, $public, $stage);
    $progress = CandidatePublication::cifProgressFromPublicFacts($facts);
    $expected = ['job_offer' => 'Job Offer', 'deployment' => 'Deployment', 'initial_interview' => 'Initial Interview'][$stage];
    releaseOrderCheck($progress['status'] === $expected, 'An unfinished later stage can be released: '.$stage);
    $priorCount = ['job_offer' => 4, 'deployment' => 5, 'initial_interview' => 1][$stage];
    releaseOrderCheck($progress['completed'] === $priorCount, 'Released phases automatically pass their required earlier processes');
    releaseOrderCheck($facts['interviews']['cif'] === $public['state']['interviews']['cif'], 'Screening result stays unpublished');
    releaseOrderCheck(CandidateInterviews::cifInterviewPrerequisite($candidate, $stage) !== null, 'Internal completion gate stays enforced');
}
$finished = ['decision' => 'Passed', 'completed_at' => '2026-10-16 02:00:00', 'revision' => 1];
foreach (['cif', 'initial_interview', 'department_interview', 'lie_detection'] as $key) {
    $candidate['interviews'][$key] = $finished;
}
$candidate['interviews']['job_offer'] = array_replace($finished, ['decision' => 'Accepted']);
$facts = CandidatePublication::cifProposedStageRelease($candidate, $public, 'job_offer');
$progress = CandidatePublication::cifProgressFromPublicFacts($facts);
releaseOrderCheck($progress['status'] === 'Deployment' && $progress['completed'] === 5, 'Direct Job offer release passes earlier processes and opens Deployment');
releaseOrderCheck($facts['interviews']['initial_interview'] === $public['state']['interviews']['initial_interview'], 'Unpublished saved Initial interview is not released');
releaseOrderCheck(array_column(CandidateSchedule::cifNextPhaseOptions($facts, 'job_offer'), 'key') === ['deployment'], 'Next-phase scheduling works after a direct release');
$facts = CandidateSchedule::cifApplyNextSchedule($facts, 'job_offer', ['next_phase' => 'deployment', 'next_datetime' => '2026-10-20T09:30']);
releaseOrderCheck($facts['next_schedule']['scheduled_at'] === '2026-10-20 01:30:00', 'Direct release accepts a schedule');
$candidate['deployment'] = ['completed_at' => '2026-10-16 03:00:00', 'revision' => 1];
$facts = CandidatePublication::cifProposedStageRelease($candidate, $public, 'deployment');
$progress = CandidatePortal::cifCandidateTrackerProgress(CandidatePublication::cifProgressFromPublicFacts($facts));
releaseOrderCheck($progress['total'] === 6 && $progress['assessment_requirements_confirmed'] && ! in_array('exam', array_column($progress['steps'], 'key'), true), 'Direct update after finished Interview publishes the chosen route without the retired Exam stage');
releaseOrderCheck($progress['status'] === 'Deployed' && $progress['completed'] === 6, 'Direct Deployment publication completes the required roadmap');
$candidate['archived_at'] = '2026-10-16 04:00:00';
try {
    CandidatePublication::cifProposedStageRelease($candidate, $public, 'deployment');
    throw new RuntimeException('Archived release accepted');
} catch (InvalidArgumentException $expected) {
}
echo "PASS: released stages complete their required predecessors on the tracker, internal completion gates and private details remain intact, scheduling and direct Deployment work.\n";
