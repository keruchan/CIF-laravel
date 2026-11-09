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
function trackerTimeCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
$baseline = ['status' => 'Screening', 'submitted_at' => '2026-10-20 01:00:00', 'next_schedule' => null, 'steps' => []];
foreach (\App\Services\Recruitment\HrStaff::cifHrStages() as $key => $label) {
    $baseline['steps'][] = ['key' => $key, 'label' => $label, 'state' => $key === 'cif' ? 'current' : 'pending', 'status' => $key === 'cif' ? 'Awaiting screening' : 'Not started'];
}
$public = \App\Services\Recruitment\CandidatePortal::cifCandidateTrackerProgress($baseline);
$progress = \App\Services\Recruitment\CandidatePortal::cifCandidateTrackerUpdateTimes($public, []);
trackerTimeCheck($progress['updated_at'] === null && array_column($progress['steps'], 'updated_at') === [null, null, null, null], 'Submission is not mislabelled as an HR update');
trackerTimeCheck(\App\Services\Recruitment\CandidatePortal::cifCandidateTrackerUpdateTimes($public, [], '2026-10-20 02:00:00')['updated_at'] === '2026-10-20 02:00:00', 'Retain known legacy publication date without inventing phase dates');
$updates = [];
$snapshot = $baseline;
$set = static function (string $key, string $state, string $status) use (&$snapshot): void {
    foreach ($snapshot['steps'] as &$step) {
        if ($step['key'] === $key) {
            $step['state'] = $state;
            $step['status'] = $status;
        }
    }
    unset($step);
};
$publish = static function (string $stage, string $time) use (&$updates, &$snapshot): array {
    $updates[] = ['stage' => $stage, 'published_at' => $time, 'progress_json' => json_encode($snapshot, JSON_THROW_ON_ERROR)];
    return \App\Services\Recruitment\CandidatePortal::cifCandidateTrackerUpdateTimes(\App\Services\Recruitment\CandidatePortal::cifCandidateTrackerProgress($snapshot), $updates);
};
$set('cif', 'completed', 'Passed');
$set('initial_interview', 'current', 'Awaiting HR review');
$snapshot['status'] = 'Initial Interview';
$progress = $publish('cif', '2026-10-21 01:00:00');
trackerTimeCheck(array_column($progress['steps'], 'updated_at') === ['2026-10-21 01:00:00', '2026-10-21 01:00:00', null, null], 'Screening release dates its result and newly opened Interview, not future phases');
$progress = $publish('initial_interview', '2026-10-21 02:00:00');
trackerTimeCheck(array_column($progress['steps'], 'updated_at') === ['2026-10-21 01:00:00', '2026-10-21 02:00:00', null, null], 'Explicit same-result update dates only the selected stage');
$set('initial_interview', 'completed', 'Passed');
$set('department_interview', 'current', 'Pending Decision');
$set('lie_detection', 'current', 'Pending Decision');
$snapshot['status'] = 'Department Interview & Background Check';
$progress = $publish('initial_interview', '2026-10-21 03:00:00');
$set('department_interview', 'completed', 'Passed');
$snapshot['status'] = 'Background Check';
$progress = $publish('department_interview', '2026-10-21 04:00:00');
trackerTimeCheck(count($progress['steps']) === 6 && array_column($progress['steps'], 'updated_at') === ['2026-10-21 01:00:00', '2026-10-21 03:00:00', '2026-10-21 04:00:00', '2026-10-21 03:00:00', null, null], 'Required assessment releases update only their own phase dates');
trackerTimeCheck($progress['updated_at'] === '2026-10-21 04:00:00', 'Header uses latest actual publication time');
$set('lie_detection', 'completed', 'Passed');
$set('job_offer', 'current', 'Pending Decision');
$snapshot['status'] = 'Job Offer';
$progress = $publish('lie_detection', '2026-10-21 05:00:00');
trackerTimeCheck($progress['steps'][4]['updated_at'] === '2026-10-21 05:00:00', 'Opening Job offer uses the release that made it visible');
$set('job_offer', 'under-review', 'Declined');
$progress = $publish('job_offer', '2026-10-21 06:00:00');
trackerTimeCheck($progress['steps'][4]['updated_at'] === '2026-10-21 06:00:00', 'Status changes get their own released update time');
$set('job_offer', 'completed', 'Accepted');
$set('deployment', 'current', 'In progress');
$snapshot['status'] = 'Deployment';
$progress = $publish('job_offer', '2026-10-21 07:00:00');
trackerTimeCheck($progress['steps'][5]['updated_at'] === '2026-10-21 07:00:00', 'Deployment dates its transition, not CIF submission');
$set('deployment', 'completed', 'Completed');
$snapshot['status'] = 'Deployed';
$progress = $publish('deployment', '2026-10-21 08:00:00');
trackerTimeCheck(array_column($progress['steps'], 'updated_at') === ['2026-10-21 01:00:00', '2026-10-21 03:00:00', '2026-10-21 04:00:00', '2026-10-21 05:00:00', '2026-10-21 07:00:00', '2026-10-21 08:00:00'], 'Earlier phase dates remain intact after deployment');
trackerTimeCheck($progress['submitted_at'] === $baseline['submitted_at'], 'Original submission date remains stored separately');
echo "PASS: header and per-phase release dates, required assessment dates, new-phase transitions, repeat/status updates, unpublished baseline and original submission date preserved.\n";
