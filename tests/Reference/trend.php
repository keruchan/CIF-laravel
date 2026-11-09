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
function trendCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}
$rows = [];
$id = 0;
$first = (new DateTimeImmutable('first day of this month'))->modify('-5 months');
foreach ([3, 6, 9, 12, 15, 18] as $index => $count) {
    $date = $first->modify("+{$index} months");
    for ($n = 0; $n < $count; $n++) {
        $rows[] = ['id' => ++$id, 'name' => 'Fictional test', 'submitted_at' => $date->format('Y-m-d') . ' 00:00:00', 'submitted_date' => $date->format('Y-m-d'), 'dimensions' => ['status' => $n % 2 ? 'In Progress' : 'Submitted', 'position' => 'Test role', 'source' => 'Not provided', 'sex' => 'Not provided', 'age' => 'Not provided', 'education' => 'Not provided', 'employment' => 'Not provided', 'province' => 'Not provided', 'salary' => 'Not provided', 'month' => $date->format('Y-m')]];
    }
}
$all = \App\Services\Recruitment\CandidateAnalytics::cifAnalyticsSummary($rows, 'all', '');
$months = array_keys($all['charts']['month']);
trendCheck(count($months) === 6 && array_sum($all['charts']['month']) === count($rows), 'Every selected candidate is counted once across the full timeline');
trendCheck($all['trend']['moving_average'][$months[0]] === null && $all['trend']['moving_average'][$months[1]] === null, 'No invented averages before three complete months');
trendCheck($all['trend']['moving_average'][$months[2]] === 6.0 && $all['trend']['moving_average'][$months[3]] === 9.0 && $all['trend']['moving_average'][$months[4]] === 12.0, 'Trailing three-month averages are mathematically correct');
trendCheck($all['trend']['moving_average'][$months[5]] === null && in_array($months[5], $all['trend']['partial_months'], true), 'Current partial month is counted but excluded from the average');
trendCheck(count($all['trend']['forecast']) === 3 && array_values($all['trend']['forecast']) === [null, null, null], 'Less than two yearly cycles does not invent seasonal forecasts');
$nextMonth = (new DateTimeImmutable('first day of this month'))->modify('+1 month')->format('Y-m');
trendCheck(array_key_first($all['trend']['forecast']) === $nextMonth, 'Forecast begins next calendar month, not in historical months');
$short = \App\Services\Recruitment\CandidateAnalytics::cifAnalyticsSummary($rows, '30', '');
trendCheck(!$short['trend']['forecast_available'] && array_values($short['trend']['forecast']) === [null, null, null], 'Insufficient complete history produces no invented forecast');
$filtered = \App\Services\Recruitment\CandidateAnalytics::cifAnalyticsSummary($rows, 'all', '', 'In Progress');
trendCheck($filtered['trend']['total'] === 30 && array_sum($filtered['charts']['month']) === 30, 'Status filter only counts matching candidate submissions');
trendCheck($filtered['total'] === $all['total'], 'Chart-only status filter does not change other dashboard summaries');
trendCheck(!$filtered['trend']['forecast_available'], 'Status-specific forecasts also require two complete yearly cycles');
$gapRows = array_values(array_filter($rows, static fn($row) => $row['dimensions']['month'] !== $months[1]));
$gap = \App\Services\Recruitment\CandidateAnalytics::cifAnalyticsSummary($gapRows, 'all', '');
trendCheck($gap['charts']['month'][$months[1]] === 0 && $gap['trend']['moving_average'][$months[2]] === 4.0, 'Months with no submissions are zero, not missing, in the average');
$empty = \App\Services\Recruitment\CandidateAnalytics::cifAnalyticsSummary([], 'all', '');
trendCheck($empty['trend']['total'] === 0 && array_sum($empty['charts']['month']) === 0, 'Empty selection is safe and has no fake candidate counts');
trendCheck(!$empty['trend']['forecast_available'], 'Empty selection has no forecast');
$seasons = [-6, -4, -2, 0, 2, 4, 6, 4, 2, 0, -2, -4];
$series = [];
for ($i = 0; $i < 36; $i++) {
    $series[] = 100 + 2 * $i + $seasons[$i % 12];
}
$original = $series;
$model = \App\Services\Recruitment\CandidateForecast::cifHoltWinters($series, 15);
trendCheck($model !== null && $model['training_count'] === 36, 'Holt-Winters fits complete yearly history');
for ($i = 0; $i < 15; $i++) {
    $expected = 100 + 2 * (36 + $i) + $seasons[(36 + $i) % 12];
    trendCheck(abs($model['values'][$i] - $expected) < 0.01, 'Recursive horizon ' . ($i + 1) . ' preserves known trend and seasonal month');
}
trendCheck($series === $original, 'Forecasting does not append predicted observations to historical data');
trendCheck(\App\Services\Recruitment\CandidateForecast::cifHoltWinters(array_slice($series, 0, 23), 3) === null, '23 months cannot initialize two annual cycles');
trendCheck(\App\Services\Recruitment\CandidateForecast::cifHoltWinters(array_slice($series, 0, 24), 3) !== null, '24 complete months enable seasonal forecasting');
trendCheck(\App\Services\Recruitment\CandidateForecast::cifHoltWinters(array_fill(0, 36, 0), 3)['values'] === [0.0, 0.0, 0.0], 'Zero counts are safe; genuinely flat data can have flat forecasts');
trendCheck(\App\Services\Recruitment\CandidateForecast::cifHoltWinters([INF, ...array_fill(0, 24, 1)], 3) === null, 'Non-finite observations are rejected');
$declining = [];
for ($i = 0; $i < 24; $i++) {
    $declining[] = 46 - 2 * $i;
}
trendCheck(\App\Services\Recruitment\CandidateForecast::cifHoltWinters($declining, 4)['values'] === [0.0, 0.0, 0.0, 0.0], 'Projected candidate counts never go below zero');
$longRows = [];
$first = (new DateTimeImmutable('first day of this month'))->modify('-35 months');
for ($i = 0; $i < 36; $i++) {
    $date = $first->modify("+{$i} months");
    $count = 30 + $i + $seasons[$i % 12];
    for ($n = 0; $n < $count; $n++) {
        $row = $rows[0];
        $row['id'] = count($longRows) + 1;
        $row['submitted_at'] = $date->format('Y-m-d') . ' 00:00:00';
        $row['submitted_date'] = $date->format('Y-m-d');
        $row['dimensions']['month'] = $date->format('Y-m');
        $row['dimensions']['status'] = $n % 2 ? 'In Progress' : 'Submitted';
        $longRows[] = $row;
    }
}
$long = \App\Services\Recruitment\CandidateAnalytics::cifAnalyticsSummary($longRows, 'all', '');
$training = array_slice($long['charts']['month'], 0, -1, true);
$expected = \App\Services\Recruitment\CandidateForecast::cifHoltWinters($training, 4);
trendCheck($long['trend']['forecast_available'] && count($long['trend']['forecast_training_months']) === 35, 'Dashboard trains on complete months only');
trendCheck(array_values($long['trend']['forecast']) === array_slice($expected['values'], 1), 'Current partial month is bridged before the next three calendar months');
trendCheck(count(array_unique(array_values($long['trend']['forecast']))) === 3, 'Trending seasonal example produces three distinct estimates, not repeated averages');
$withoutCurrent = array_filter($longRows, static fn($row) => $row['dimensions']['month'] !== date('Y-m'));
trendCheck(\App\Services\Recruitment\CandidateAnalytics::cifAnalyticsSummary($withoutCurrent, 'all', '')['trend']['forecast'] === $long['trend']['forecast'], 'Partial-month observations cannot bias full-month model training');
$cohort = \App\Services\Recruitment\CandidateAnalytics::cifAnalyticsSummary($longRows, 'all', '', 'In Progress');
$cohortExpected = \App\Services\Recruitment\CandidateForecast::cifHoltWinters(array_slice($cohort['charts']['month'], 0, -1, true), 4);
trendCheck(array_values($cohort['trend']['forecast']) === array_slice($cohortExpected['values'], 1), 'Status-specific forecasts fit only matching monthly counts');
trendCheck($cohort['total'] === count($longRows), 'Predictions do not increase actual dashboard totals');
foreach (['daily', 'weekly', 'monthly', 'yearly'] as $interval) {
    $grouped = \App\Services\Recruitment\CandidateAnalytics::cifAnalyticsSummary($longRows, 'all', '', 'In Progress', $interval);
    trendCheck(array_sum($grouped['trend']['series']) === $cohort['trend']['total'], ucfirst($interval) . ' grouping counts every matching candidate exactly once');
    $keys = array_keys($grouped['trend']['series']);
    $sorted = $keys;
    sort($sorted);
    trendCheck($keys === $sorted, ucfirst($interval) . ' buckets are chronologically ordered');
    trendCheck($grouped['trend']['forecast'] === $cohort['trend']['forecast'], ucfirst($interval) . ' grouping leaves the monthly forecast unchanged');
    $currentKey = \App\Services\Recruitment\CandidateAnalytics::cifAnalyticsPeriodKey(new DateTimeImmutable('today'), $interval);
    trendCheck(in_array($currentKey, $grouped['trend']['partial_periods'], true), ucfirst($interval) . ' current bucket is marked partial');
}
trendCheck(\App\Services\Recruitment\CandidateAnalytics::cifAnalyticsPeriodKey(new DateTimeImmutable('2025-01-01'), 'weekly') === '2024-12-30', 'Cross-year weeks use one Monday-start bucket');
trendCheck(\App\Services\Recruitment\CandidateAnalytics::cifAnalyticsPeriodKey(new DateTimeImmutable('2024-02-29'), 'daily') === '2024-02-29', 'Daily grouping preserves leap days');
trendCheck(\App\Services\Recruitment\CandidateAnalytics::cifAnalyticsPeriodKey(new DateTimeImmutable('2024-12-31'), 'yearly') === '2024', 'Year grouping uses the calendar year');
$days = \App\Services\Recruitment\CandidateAnalytics::cifAnalyticsSummary($rows, 'all', '', '', 'daily');
trendCheck(in_array(0, $days['trend']['series'], true), 'Daily dates with no submissions are zero, not omitted');
foreach (['daily', 'weekly', 'monthly', 'yearly'] as $interval) {
    $short = \App\Services\Recruitment\CandidateAnalytics::cifAnalyticsSummary($longRows, '30', '', '', $interval);
    trendCheck(array_sum($short['trend']['series']) === $short['trend']['total'], ucfirst($interval) . ' buckets respect the date-filtered scope');
}
try {
    \App\Services\Recruitment\CandidateAnalytics::cifAnalyticsSummary($longRows, 'all', '', '', 'hourly');
    trendCheck(false, 'Invalid interval rejected');
} catch (InvalidArgumentException $error) {
    trendCheck(true, 'Invalid interval rejected');
}
