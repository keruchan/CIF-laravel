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
if (\App\Services\Recruitment\ApplicantAccess::CIF_INVITATION_DAYS !== 10) {
    throw new RuntimeException('Invitation policy must be ten days.');
}
foreach (['2026-10-22 07:30:00+00:00', '2026-12-28 23:59:59+00:00', '2024-02-25 12:00:00+00:00', '2026-10-22 15:30:00+08:00'] as $date) {
    $created = new DateTimeImmutable($date);
    $expiry = new DateTimeImmutable(\App\Services\Recruitment\ApplicantAccess::cifInvitationExpiry($created), new DateTimeZone('UTC'));
    if ($expiry->getTimestamp() - $created->getTimestamp() !== 10 * 86400) {
        throw new RuntimeException('Expiry must be exactly ten days after issuance.');
    }
}
$before = time();
$expiry = strtotime(\App\Services\Recruitment\ApplicantAccess::cifInvitationExpiry() . ' UTC');
$after = time();
if ($expiry < $before + 10 * 86400 || $expiry > $after + 10 * 86400) {
    throw new RuntimeException('Default expiry must be ten days from now.');
}
foreach (['pages/candidate/index.php', 'pages/admin/index.php'] as $page) {
    $text = file_get_contents(resource_path('views/recruitment/'.str_replace('pages/', '', $page)));
    if (preg_match('/\b3[ -]days?\b/i', $text) || !preg_match('/\b10[ -]days?\b/i', $text)) {
        throw new RuntimeException('Invitation instructions must match the ten-day policy.');
    }
}
echo "PASS: ten-day invitation expiry, UTC/Philippine offsets, month/year/leap-day boundaries and matching candidate/admin instructions. No database changes or email.\n";
