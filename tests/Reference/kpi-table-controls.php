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
function tableAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
$sample = ['id' => 1, 'mrf_number' => '2026-2-01', 'position_title' => 'Zoology Assistant', 'department_name' => 'Accounting', 'site_location' => 'Malolos', 'requestor_name' => 'MARIA NAVARRO', 'hr_name' => 'ANA SANTOS', 'status' => 'Completed', 'position_category' => 'A', 'filled' => true, 'average_days' => 12.1, 'days_open' => 40, 'hiring_due_date' => '2026-10-02'];
$rows = [$sample, array_replace($sample, ['id' => 2, 'mrf_number' => '2026-10-01', 'position_title' => 'Accounting Assistant', 'status' => 'In Progress', 'filled' => false, 'days_open' => 30, 'hiring_due_date' => '2026-10-01']), array_replace($sample, ['id' => 3, 'mrf_number' => '2026-3-01', 'position_title' => 'Support 100%', 'status' => 'On Hold', 'position_category' => 'D', 'filled' => false, 'days_open' => null])];
$sort = \App\Services\Recruitment\HrKpi::cifKpiTableRows($rows, \App\Services\Recruitment\HrKpi::cifKpiTableOptions(['table_sort' => 'mrf']));
tableAssert(array_column($sort, 'id') === [1, 3, 2], 'MRF number sorts naturally, never by the displayed position.');
$sort = \App\Services\Recruitment\HrKpi::cifKpiTableRows($rows, \App\Services\Recruitment\HrKpi::cifKpiTableOptions(['table_sort' => 'mrf', 'table_direction' => 'desc']));
tableAssert(array_column($sort, 'id') === [2, 3, 1], 'Descending MRF order handles numeric segments.');
foreach (['asc', 'desc'] as $direction) {
    $sort = \App\Services\Recruitment\HrKpi::cifKpiTableRows($rows, \App\Services\Recruitment\HrKpi::cifKpiTableOptions(['table_sort' => 'days', 'table_direction' => $direction]));
    tableAssert(end($sort)['id'] === 3, 'Unavailable days sort last in either direction.');
}
tableAssert(array_column(\App\Services\Recruitment\HrKpi::cifKpiTableRows($rows, \App\Services\Recruitment\HrKpi::cifKpiTableOptions(['table_sort' => 'due'])), 'id') === [2, 1, 3], 'Hiring-due sort follows actual calendar dates.');
tableAssert(array_column(\App\Services\Recruitment\HrKpi::cifKpiTableRows($rows, \App\Services\Recruitment\HrKpi::cifKpiTableOptions(['table_sort' => 'days', 'table_direction' => 'desc'])), 'id') === [2, 1, 3], 'Days sort numerically, using fill duration or open days as displayed.');
tableAssert(count(\App\Services\Recruitment\HrKpi::cifKpiTableRows($rows, \App\Services\Recruitment\HrKpi::cifKpiTableOptions(['table_search' => 'malolos', 'table_status' => 'In Progress', 'table_filled' => 'unfilled', 'table_category' => 'A']))) === 1, 'Search and filters combine across row fields.');
tableAssert(array_column(\App\Services\Recruitment\HrKpi::cifKpiTableRows($rows, \App\Services\Recruitment\HrKpi::cifKpiTableOptions(['table_search' => '100%'])), 'id') === [3], 'Percent signs are literal search characters, not SQL wildcards.');
tableAssert(\App\Services\Recruitment\HrKpi::cifKpiTableOptions([])['per_page'] === 15 && \App\Services\Recruitment\HrKpi::cifKpiTableOptions(['per_page' => 50])['per_page'] === 15, 'Page size is fixed at 15 regardless of client input.');
foreach ([['table_sort' => 'position'], ['table_sort' => []], ['table_direction' => 'DROP TABLE'], ['table_status' => 'unknown'], ['table_category' => 'Z'], ['table_filled' => true], ['table_search' => str_repeat('a', 255)]] as $invalid) {
    try {
        \App\Services\Recruitment\HrKpi::cifKpiTableOptions($invalid);
        throw new RuntimeException('Invalid table controls were accepted.');
    } catch (InvalidArgumentException $expected) {
    }
}
echo "PASS: MRF-number-only natural sorting, dates/day sorting, null timing placement, combined search/filters and strict table-control validation.\n";
