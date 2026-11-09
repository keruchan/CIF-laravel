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
function coverageAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
$today = new DateTimeImmutable('2026-10-26', new DateTimeZone('Asia/Shanghai'));
$week = \App\Services\Recruitment\HrKpi::cifKpiCoverage(['period' => 'weekly'], $today);
coverageAssert($week['from']->format('Y-m-d') === '2026-10-26' && $week['end']->format('Y-m-d') === '2026-11-01', 'Default week runs Monday–Sunday.');
$leap = \App\Services\Recruitment\HrKpi::cifKpiCoverage(['period' => 'monthly', 'date_from' => '2024-02-01', 'date_to' => '2024-02-29'], $today);
coverageAssert($leap['to']->format('Y-m-d') === '2024-03-01' && (int) $leap['from']->diff($leap['to'])->format('%a') === 29, 'Leap-day coverage has a valid exclusive upper bound.');
$one = \App\Services\Recruitment\HrKpi::cifKpiCoverage(['period' => 'weekly', 'date_from' => '2026-10-26', 'date_to' => '2026-10-26'], $today);
coverageAssert((int) $one['from']->diff($one['to'])->format('%a') === 1, 'Single-day coverage includes the entire date.');
coverageAssert($one['from']->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') === '2026-10-25 16:00:00', 'Local start correctly converts to UTC for the funnel.');
coverageAssert(\App\Services\Recruitment\HrKpi::cifKpiBucketKey(new DateTimeImmutable('2027-01-03'), 'weekly') === '2026-W53', 'ISO week year is preserved at a year boundary.');
coverageAssert(\App\Services\Recruitment\HrKpi::cifKpiBucketStart(new DateTimeImmutable('2027-01-03'), 'weekly')->format('Y-m-d') === '2026-12-28', 'Sunday belongs to the preceding Monday.');
coverageAssert(\App\Services\Recruitment\HrKpi::cifKpiBucketKey(new DateTimeImmutable('2027-01-04'), 'weekly') === '2027-W01', 'New ISO year starts on its proper Monday.');
\App\Services\Recruitment\HrKpi::cifKpiCoverage(['period' => 'yearly', 'date_from' => '2022-01-01', 'date_to' => '2026-12-31'], $today);
foreach ([['date_from' => '2026-02-30', 'date_to' => '2026-03-01'], ['date_from' => '2026-10-27', 'date_to' => '2026-10-26'], ['date_from' => '2020-01-01', 'date_to' => '2025-01-01'], ['date_from' => '2026-01-01'], ['date_from' => [], 'date_to' => '2026-01-01'], ['date_from' => '2023-02-29', 'date_to' => '2023-03-01'], ['period' => 'daily']] as $invalid) {
    try {
        \App\Services\Recruitment\HrKpi::cifKpiCoverage($invalid, $today);
        throw new RuntimeException('Invalid coverage was accepted.');
    } catch (InvalidArgumentException $expected) {
    }
}
echo "PASS: explicit inclusive coverage, Monday–Sunday weeks, ISO year boundaries, single dates, leap days, timezone conversion, five-year limit and malformed inputs.\n";
