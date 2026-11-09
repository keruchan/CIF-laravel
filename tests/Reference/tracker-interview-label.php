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
function trackerLabelCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
$progress = ['status' => 'Initial Interview', 'steps' => [['key' => 'cif', 'label' => 'CIF screening', 'state' => 'completed', 'status' => 'Passed'], ['key' => 'initial_interview', 'label' => 'Initial interview', 'state' => 'current', 'status' => 'Pending Decision'], ['key' => 'department_interview', 'label' => 'Department interview', 'state' => 'pending', 'status' => 'Pending Decision'], ['key' => 'lie_detection', 'label' => 'Background check', 'state' => 'pending', 'status' => 'Pending Decision'], ['key' => 'job_offer', 'label' => 'Job offer', 'state' => 'pending', 'status' => 'Pending Decision'], ['key' => 'deployment', 'label' => 'Deployment', 'state' => 'pending', 'status' => 'Pending']], 'next_schedule' => ['stage' => 'initial_interview', 'label' => 'Initial interview', 'scheduled_at' => '2026-10-22 02:00:00']];
$tracker = \App\Services\Recruitment\CandidatePortal::cifCandidateTrackerProgress($progress);
trackerLabelCheck($tracker['status'] === 'Interview', 'Candidate current phase is Interview');
trackerLabelCheck($tracker['steps'][1]['key'] === 'initial_interview' && $tracker['steps'][1]['label'] === 'Interview', 'Roadmap uses Interview without changing stored stage key');
trackerLabelCheck($tracker['next_schedule']['label'] === 'Interview' && $tracker['next_schedule']['scheduled_at'] === $progress['next_schedule']['scheduled_at'], 'Next phase uses Interview without changing schedule');
trackerLabelCheck(count($tracker['steps']) === 4 && $tracker['completed'] === 1, 'Optional assessments stay hidden until Interview completion and completed count is unchanged');
trackerLabelCheck($progress['status'] === 'Initial Interview' && $progress['steps'][1]['label'] === 'Initial interview', 'Original HR progress is unchanged');
trackerLabelCheck(\App\Services\Recruitment\CandidateInterviews::cifInterviewStages()['initial_interview'] === 'HR interview', 'HR roadmap uses the current label');
$progress['status'] = 'Job Offer';
$progress['next_schedule'] = ['stage' => 'job_offer', 'label' => 'Job offer', 'scheduled_at' => null];
$tracker = \App\Services\Recruitment\CandidatePortal::cifCandidateTrackerProgress($progress);
trackerLabelCheck($tracker['status'] === 'Job Offer' && $tracker['next_schedule']['label'] === 'Job offer', 'Other phase names remain unchanged');
echo "PASS: candidate Interview labels in roadmap, current phase and schedule; stable stage keys, required assessments and HR labels preserved.\n";
