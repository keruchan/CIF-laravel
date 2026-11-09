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
$candidate = ['candidate_number' => 'AB2601', 'position' => 'Accountant', 'submitted_at' => '2026-10-16 01:00:00', 'status' => 'Initial Interview', 'archived_at' => null, 'form' => array_fill_keys(array_keys(\App\Services\Recruitment\CifFields::cifScalarFields()), ''), 'hr' => ['recertification_name' => 'EXCLUDED RECERTIFICATION NAME', 'recertification_date' => '2026-10-16', 'hr_notes' => 'Saved HR notes remain available.'], 'mrf' => ['mrf_number' => 'EXCLUDED-MRF-NUMBER', 'position_title' => 'EXCLUDED-MRF-POSITION', 'status' => 'Approved']];
$candidate['form'] = array_replace($candidate['form'], ['fn' => 'ANA', 'ln' => 'SANTOS', 'desired_position' => 'Accountant', 'adr' => 'Malolos, Bulacan', 'permanent_address' => 'Malolos, Bulacan']);
foreach (\App\Services\Recruitment\HrFields::cifHrScoreFields() as $key => $label) {
    $candidate['hr'][$key] = '85';
}
foreach (\App\Services\Recruitment\HrFields::cifHrFields()['Health & lifestyle information'] as $key => $label) {
    $candidate['hr'][$key] = 'None reported';
}
ob_start();
\App\Services\Recruitment\CifPrint::renderCifPrint($candidate);
$html = ob_get_clean();
if (preg_match('/RE-CERTIFICATION|Re-certification|EXCLUDED-?MRF|EXCLUDED RECERTIFICATION/i', $html)) {
    throw new RuntimeException('Excluded print content remains');
}
$doc = new DOMDocument();
@$doc->loadHTML($html);
$xpath = new DOMXPath($doc);
if ($xpath->query('//section[contains(@class,"paper-sheet")]')->length !== 3) {
    throw new RuntimeException('Expected three CIF sheets');
}
if ($xpath->query('//table[contains(@class,"paper-assessment-scores")]')->length !== 0 || preg_match('/COGNITIVE SKILLS|FLANAGAN INDUSTRIAL|Abstract Reasoning|Verbal &amp; Numerical Reasoning|Judgement &amp; Comprehension|MD5 SCORE/i', $html)) {
    throw new RuntimeException('Assessment headings and scores must not be printed');
}
foreach (\App\Services\Recruitment\HrFields::cifHrScoreFields() as $key => $label) {
    if ($xpath->query('//*[@data-print-field="hr.' . $key . '"]')->length !== 0) {
        throw new RuntimeException('Assessment score remains in print: ' . $key);
    }
}
if ($xpath->query('//section[contains(@class,"paper-sheet-natural")]//table[contains(@class,"health-paper-table")]')->length !== 1) {
    throw new RuntimeException('Health table must use natural-height sheet');
}
if ($xpath->query('//*[@data-print-field="hr.hr_notes"]')->length !== 0 || preg_match('/Application status|Archived|HR notes/i', $html)) {
    throw new RuntimeException('Internal status and notes must not be printed');
}
if (!str_contains($html, 'CERTIFICATION OF INFORMATION') || !str_contains($html, 'BACKGROUND CHECK CONSENT')) {
    throw new RuntimeException('Original candidate declarations must remain');
}
if (str_contains($html, $candidate['hr']['hr_notes'])) {
    throw new RuntimeException('Internal HR notes must not be printed');
}
if (isset($argv[1])) {
    $base = 'file:///' . str_replace(' ', '%20', str_replace('\\', '/', dirname(__DIR__))) . '/pages/admin/';
    $styles = '';
    foreach (['branding', 'cif-record', 'cif-photo', 'cif-print'] as $file) {
        $styles .= file_get_contents(dirname(__DIR__) . '/css/' . $file . '.css');
    }
    file_put_contents($argv[1], '<!doctype html><html lang="en"><head><meta charset="utf-8"><base href="' . $base . '"><style>' . $styles . '</style></head><body class="cif-portal-record"><div id="app"><main><div class="pg">' . $html . '</div></main></div></body></html>');
}
echo "PASS: assessment scores, internal notes, status, archive flags, re-certification and MRF excluded; three sheets; natural-height Health & Lifestyle; candidate declarations retained.\n";
