<?php

declare (strict_types=1);
require dirname(__DIR__, 2) . "/vendor/autoload.php";
$app = require dirname(__DIR__, 2) . "/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
date_default_timezone_set("Asia/Manila");
set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, $e::class . ": " . $e->getMessage() . "\n" . $e->getTraceAsString());
    exit(1);
});
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
function roadmapCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
$record = static fn(string $decision = 'Passed', bool $finished = true): array => ['decision' => $decision, 'completed_at' => $finished ? '2026-10-14 02:00:00' : null, 'revision' => 1];
$candidate = ['submitted_at' => '2026-10-14 01:00:00', 'status' => 'Screening', 'interviews' => []];
roadmapCheck(\App\Services\Recruitment\CandidateInterviews::cifInterviewStages()['lie_detection'] === 'Background check', 'Existing assessment key uses the new interview label');
roadmapCheck(\App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($candidate)['steps'][3]['label'] === 'Background check', 'HR and candidate roadmaps share the new label');
roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifAutomaticCandidateStatus($candidate) === 'Screening', 'New submissions await screening');
roadmapCheck(\App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($candidate)['completed'] === 0, 'Submission is not a screening pass');
roadmapCheck(!in_array(true, array_column(\App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($candidate)['steps'], 'connector_active'), true), 'Unscreened candidates have no active connectors');
roadmapCheck(\App\Services\Recruitment\CandidateInterviews::cifInterviewPrerequisite($candidate, 'initial_interview') !== null, 'Screening gates Initial interview');
$candidate['interviews']['cif'] = $record('Failed');
roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifNextStageAfterCompletion($candidate, 'cif') === null, 'Failed screening does not redirect');
roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifAutomaticCandidateStatus($candidate) === 'Screening Failed' && \App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($candidate)['steps'][0]['state'] === 'held', 'Failed screening blocks the roadmap');
$candidate['interviews']['cif'] = $record('Backout');
roadmapCheck(\App\Services\Recruitment\CandidateInterviews::validateCifInterview(['decision' => 'Backout'], 'cif')['decision'] === 'Backout', 'Screening accepts Backout');
roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifAutomaticCandidateStatus($candidate) === 'Screening Backout', 'Screening Backout is not labeled Failed');
roadmapCheck(\App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($candidate)['steps'][0]['state'] === 'held' && \App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($candidate)['steps'][0]['status'] === 'Backout', 'Roadmap displays Backout at screening');
roadmapCheck(\App\Services\Recruitment\CandidateInterviews::cifInterviewPrerequisite($candidate, 'initial_interview') !== null && \App\Services\Recruitment\CandidateStatuses::cifNextStageAfterCompletion($candidate, 'cif') === null, 'Backout blocks Initial interview and automatic advancement');
$candidate['interviews']['cif'] = $record();
roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifNextStageAfterCompletion($candidate, 'cif') === 'initial_interview', 'Passed screening redirects to Initial interview');
roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifAutomaticCandidateStatus($candidate) === 'Initial Interview', 'Passed screening opens Initial interview');
roadmapCheck(array_column(\App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($candidate)['steps'], 'connector_active') === [false, true, false, false, false, false], 'Screening activates only the connector to Initial interview');
roadmapCheck(\App\Services\Recruitment\CandidateInterviews::cifInterviewPrerequisite($candidate, 'initial_interview') === null, 'Initial interview allowed');
$candidate['interviews']['initial_interview'] = $record();
roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifAutomaticCandidateStatus($candidate) === 'Department Interview & Background Check', 'Passed Initial opens required assessments');
roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifNextStageAfterCompletion($candidate, 'initial_interview') === 'department_interview', 'Initial completion redirects to Department interview');
roadmapCheck(\App\Services\Recruitment\CandidateInterviews::cifInterviewPrerequisite($candidate, 'department_interview') === null, 'Initial interview directly opens assessments');
$candidate['interviews']['exam'] = $record('Failed');
// Historical exam no longer gates the route.
roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifAutomaticCandidateStatus($candidate) === 'Department Interview & Background Check', 'Parallel assessments do not imply ordering');
roadmapCheck(array_column(\App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($candidate)['steps'], 'connector_active') === [false, true, true, true, false, false], 'Finished Passed Initial activates both independent assessment connectors, not Job offer');
$unfinishedInitial = $candidate;
$unfinishedInitial['interviews']['initial_interview'] = $record('Passed', false);
roadmapCheck(!\App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($unfinishedInitial)['steps'][2]['connector_active'], 'Save-only Passed Initial does not activate assessment connectors');
foreach ([true, false] as $department) {
    foreach ([true, false] as $ldt) {
        $route = $candidate;
        $route['assessment_requirements'] = ['department_required' => $department, 'ldt_required' => $ldt];
        $waitingStatus = $department && $ldt ? 'Department Interview & Background Check' : ($department ? 'Department Interview' : ($ldt ? 'Background Check' : 'Job Offer'));
        roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifAutomaticCandidateStatus($route) === $waitingStatus, 'Four optional assessment routes');
        roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifNextStageAfterCompletion($route, 'initial_interview') === ($department ? 'department_interview' : ($ldt ? 'lie_detection' : 'job_offer')), 'Completion skips excluded assessments');
        foreach (['department_interview', 'lie_detection'] as $stage) {
            if (\App\Services\Recruitment\CandidateInterviews::cifAssessmentRequired($route, $stage)) {
                roadmapCheck(\App\Services\Recruitment\CandidateInterviews::cifInterviewPrerequisite($route, $stage) === null, 'Either assessment may run first');
            }
            $route['interviews'][$stage] = $record();
        }
        roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifAutomaticCandidateStatus($route) === 'Job Offer', 'Required passes advance to offer');
        roadmapCheck(\App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($route)['steps'][4]['connector_active'], 'All required assessments passed connects to Job offer, including skipped assessments');
        roadmapCheck(\App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($route)['total'] === 4 + (int) $department + (int) $ldt, 'Excluded stages do not count');
        foreach (['Pending Decision', 'Failed', 'Backout', 'Pooling', 'No Response'] as $decision) {
            $hold = $route;
            $hold['interviews']['initial_interview'] = $record($decision);
            roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifAutomaticCandidateStatus($hold) === 'Initial Interview', 'Unsuccessful initial result stops advancement');
        }
        foreach (\App\Services\Recruitment\CandidateInterviews::cifInterviewDecisions('job_offer') as $decision) {
            $route['interviews']['job_offer'] = $record($decision);
            roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifAutomaticCandidateStatus($route) === ($decision === 'Accepted' ? 'Deployment' : 'Job Offer'), 'Only Accepted advances');
            roadmapCheck((\App\Services\Recruitment\CandidateInterviews::cifInterviewPrerequisite($route, 'deployment') === null) === ($decision === 'Accepted'), 'Deployment gate matches status');
            roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifNextStageAfterCompletion($route, 'job_offer') === ($decision === 'Accepted' ? 'deployment' : null), 'Only Accepted offer auto-navigates');
        }
        $route['interviews']['job_offer'] = $record('Accepted', false);
        roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifAutomaticCandidateStatus($route) === 'Job Offer', 'Saving an unfinished accepted offer does not advance');
        roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifNextStageAfterCompletion($route, 'job_offer') === null, 'Unfinished offer does not redirect');
        roadmapCheck(!\App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($route)['steps'][5]['connector_active'], 'An unfinished Accepted offer does not activate Deployment connector');
        $route['interviews']['job_offer'] = $record('Accepted');
        $route['deployment'] = ['completed_at' => '2026-10-14 03:00:00', 'revision' => 1];
        roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifAutomaticCandidateStatus($route) === 'Deployed', 'Completed deployment is final status');
        roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifNextStageAfterCompletion($route, 'deployment') === null, 'Deployment stays on the final step');
        roadmapCheck(\App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($route)['completed'] === \App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($route)['total'], 'Roadmap is complete');
        roadmapCheck(array_column(\App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($route)['steps'], 'connector_active') === [false, true, true, true, true, true], 'Successful route activates all connectors');
        $route['interviews']['cif'] = $record('Failed');
        roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifAutomaticCandidateStatus($route) === 'Screening Failed' && \App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($route)['completed'] === 0, 'Revised failed screening blocks every later result without deleting it');
        roadmapCheck($route['deployment']['completed_at'] !== null, 'Prior deployment remains stored');
        roadmapCheck(!in_array(true, array_column(\App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($route)['steps'], 'connector_active'), true), 'Failed screening clears connector activation without deleting later results');
    }
}
$candidate['interviews']['lie_detection'] = $record();
roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifNextStageAfterCompletion($candidate, 'lie_detection') === 'department_interview', 'LDT-first completion redirects to the remaining assessment');
roadmapCheck(\App\Services\Recruitment\CandidateStatuses::cifAutomaticCandidateStatus($candidate) === 'Department Interview', 'LDT can finish first');
roadmapCheck(\App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($candidate)['steps'][3]['state'] === 'completed', 'Roadmap records independent completion');
roadmapCheck(!\App\Services\Recruitment\CandidateRoadmap::cifHiringRoadmap($candidate)['steps'][4]['connector_active'], 'One of two required assessment passes does not connect to Job offer');
roadmapCheck(\App\Services\Recruitment\CandidateInterviews::cifInterviewDecisions('cif') === ['Passed', 'Failed', 'Backout'], 'Screening supports Passed, Failed and Backout');
roadmapCheck(\App\Services\Recruitment\CandidateInterviews::cifInterviewDecisions('job_offer') === ['Accepted', 'Declined', 'Backout', 'Pending Decision', 'Pooling'], 'Offer choices unchanged');
foreach (['cif', 'initial_interview', 'job_offer'] as $stage) {
    try {
        \App\Services\Recruitment\CandidateInterviews::validateCifInterview(['decision' => 'For Schedule!'], $stage);
        throw new RuntimeException('Removed decision accepted');
    } catch (InvalidArgumentException $expected) {
    }
}
echo "PASS: screening gates, automatic statuses without a standalone Exam, all optional assessment routes, independent completion, offer acceptance and final Deployed status.\n";
