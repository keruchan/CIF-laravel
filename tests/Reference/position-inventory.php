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
if (count(\App\Services\Recruitment\HiringPositions::cifAppliedPositions()) !== 48 || count(\App\Services\Recruitment\HiringPositions::cifHiringPositions()) !== 347) {
    throw new RuntimeException('Workbook inventory counts differ.');
}
foreach (\App\Services\Recruitment\HiringPositions::cifAppliedPositions() as $position) {
    if (\App\Services\Recruitment\HiringPositions::validateCifAppliedPosition($position) !== $position) {
        throw new RuntimeException('An applied position was altered.');
    }
}
foreach (['Invented position', 'Software Developer', 'Architect 1', 'site engineer', 'Site Engineer extra', null, []] as $invalid) {
    try {
        \App\Services\Recruitment\HiringPositions::validateCifAppliedPosition($invalid);
        throw new RuntimeException('Unlisted applied position accepted.');
    } catch (InvalidArgumentException $expected) {
    }
}
if (!in_array('Architect 1', \App\Services\Recruitment\HiringPositions::cifHiringPositions(), true) || in_array('Architect', \App\Services\Recruitment\HiringPositions::cifHiringPositions(), true)) {
    throw new RuntimeException('Applied and deployment lists must stay separate.');
}
if (\App\Services\Recruitment\HiringPositions::validateCifAppliedPosition('', true) !== '') {
    throw new RuntimeException('Incomplete HR drafts can keep a missing answer.');
}
echo "PASS: 48 applied positions and 347 official deployment titles, separate lists, exact title validation.\n";
