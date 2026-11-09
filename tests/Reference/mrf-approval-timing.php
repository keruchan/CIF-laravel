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
function approvalTimingCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
$today = new DateTimeImmutable('2026-10-23', new DateTimeZone('Asia/Manila'));
$expected = ['A' => [30, '2026-11-22'], 'B' => [35, '2026-11-27'], 'C-1' => [40, '2026-12-02'], 'C-2' => [45, '2026-12-07'], 'D' => [60, '2026-12-22']];
foreach ($expected as $category => [$days, $due]) {
    $result = \App\Services\Recruitment\MrfApproval::cifMrfApprovalTiming(['date_approved' => '2026-10-23', 'position_category' => $category, 'lead_time_days' => 999, 'hiring_due_date' => '2099-01-01'], $today);
    approvalTimingCheck($result === ['date_approved' => '2026-10-23', 'position_category' => $category, 'lead_time_days' => $days, 'hiring_due_date' => $due], 'All category periods and due dates are calculated by the server');
}
foreach ([['2026-10-01', 'A', '2026-10-31'], ['2025-12-20', 'C-2', '2026-02-03'], ['2024-02-29', 'A', '2024-03-30'], ['2023-02-20', 'A', '2023-03-22']] as [$date, $category, $due]) {
    approvalTimingCheck(\App\Services\Recruitment\MrfApproval::cifMrfApprovalTiming(['date_approved' => $date, 'position_category' => $category], $today)['hiring_due_date'] === $due, 'Backdated approvals and calendar boundaries');
}
foreach ([[], ['date_approved' => null, 'position_category' => 'A'], ['date_approved' => [], 'position_category' => 'A'], ['date_approved' => '2026-10-23', 'position_category' => []], ['date_approved' => '2026-10-23', 'position_category' => 'C'], ['date_approved' => '2026-10-23', 'position_category' => ''], ['date_approved' => '2026-02-30', 'position_category' => 'A'], ['date_approved' => '2026-10-24', 'position_category' => 'A'], ['date_approved' => '2026-1-1', 'position_category' => 'A'], ['date_approved' => '1899-12-31', 'position_category' => 'A']] as $input) {
    try {
        \App\Services\Recruitment\MrfApproval::cifMrfApprovalTiming($input, $today);
        throw new RuntimeException('Invalid approval accepted');
    } catch (InvalidArgumentException $expectedError) {
    }
}
echo "PASS: all five position categories; approval + calendar-day due dates including weekends, month/year/leap boundaries; backdating; missing/invalid/future input rejected; client-provided due dates ignored.\n";
