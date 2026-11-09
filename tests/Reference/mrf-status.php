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
function mrfStatusCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
mrfStatusCheck(\App\Services\Recruitment\Mrf::mrfStatuses() === ['Pending', 'In Progress', 'Completed', 'Rejected', 'On Hold', 'Cancelled'], 'Existing statuses retained and new statuses appended');
foreach (\App\Services\Recruitment\Mrf::mrfStatuses() as $status) {
    $counts = array_fill_keys(\App\Services\Recruitment\Mrf::mrfStatuses(), 0);
    $counts[$status] = 2;
    mrfStatusCheck(\App\Services\Recruitment\MrfDepartment::departmentMrfStatus($counts) === $status, 'A uniform request status is visible to the requestor: ' . $status);
}
foreach ([['On Hold', 'Pending'], ['Cancelled', 'In Progress'], ['On Hold', 'Cancelled'], ['Completed', 'On Hold']] as [$first, $second]) {
    $counts = array_fill_keys(\App\Services\Recruitment\Mrf::mrfStatuses(), 0);
    $counts[$first]++;
    $counts[$second]++;
    mrfStatusCheck(\App\Services\Recruitment\MrfDepartment::departmentMrfStatus($counts) === 'In Progress', 'Mixed headcounts retain the existing combined-status rule');
}
echo "PASS: On Hold/Cancelled catalogue and requestor aggregation, all legacy statuses and mixed-headcount behavior preserved.\n";
