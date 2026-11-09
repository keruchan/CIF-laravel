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
function educationCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
$field = \App\Services\Recruitment\CifFields::cifScalarFields()['education_years'];
foreach (['2016-2020', '2016–2020', '2016 to 2020', '2020–2020'] as $valid) {
    educationCheck(\App\Services\Recruitment\CandidateStorage::cifValue($valid, $field, null, 'education_years') !== '', 'Valid year range');
}
foreach (['201–2020', '2016–20', '2016–', '–2020', '2020–2016', '1899–2020', '2016–' . ((int) date('Y') + 1), '2016–Present', 'abc', ''] as $invalid) {
    try {
        \App\Services\Recruitment\CandidateStorage::cifValue($invalid, $field, null, 'education_years');
        throw new RuntimeException('Invalid completed range accepted: ' . $invalid);
    } catch (InvalidArgumentException $expected) {
        /* Final candidate submission requires both valid years. */
    }
}
$partial = $field;
$partial['required'] = false;
foreach (['', '2016–', '–2020'] as $valid) {
    educationCheck(\App\Services\Recruitment\CandidateStorage::cifValue($valid, $partial, null, 'education_years') === $valid, 'Incomplete HR work stays savable');
}
educationCheck(\App\Services\Recruitment\CandidateStorage::cifValue('2016–Present', $partial, '2016–Present', 'education_years') === '2016–Present', 'Unchanged legacy text is preserved');
educationCheck(\App\Services\Recruitment\CifEducation::cifEducationYearParts('2016–2020') === ['from' => '2016', 'to' => '2020'], 'Read saved year ranges');
educationCheck(\App\Services\Recruitment\CifEducation::cifEducationYearParts('2016–Present') === ['from' => '2016', 'to' => ''], 'Do not invent a numeric end year for legacy text');
foreach (['201–2020', '2016–', '–2020', '2016–2020'] as $value) {
    educationCheck(\App\Services\Recruitment\ApplicantAccess::validateCifDraft(['education_years' => $value])['education_years'] === $value, 'Partial typing survives draft validation');
}
$missing = \App\Services\Recruitment\CandidateStatuses::cifCompletionMissing(['form' => ['education_years' => '2016–'], 'hr' => []], false);
educationCheck(in_array('Inclusive years — To year', $missing, true) && !in_array('Inclusive years — From year', $missing, true), 'HR completion checks missing end year');
educationCheck(\App\Services\Recruitment\CandidateStorage::cifSqlType($field) === 'TEXT', 'Existing storage column does not need a migration');
ob_start();
echo view('recruitment.candidate.index')->render();
$candidateHtml = ob_get_clean();
$document = new DOMDocument();
@$document->loadHTML($candidateHtml);
$xpath = new DOMXPath($document);
educationCheck($xpath->query('//input[@data-education-year and @data-year and @required and @maxlength="4" and @pattern="[0-9]{4}"]')->length === 2, 'Candidate has two required four-digit year inputs');
educationCheck($xpath->query('//*[@name="yr"]')->length === 0 && !str_contains($candidateHtml, 'Year graduated'), 'Candidate graduation input and label are removed');
educationCheck($xpath->query('//input[@name="education_years" and @type="hidden"]')->length === 1, 'Preserve existing storage and submission key');
ob_start();
\App\Services\Recruitment\CifEditor::renderAdminCifField('education_years', \App\Services\Recruitment\CifFields::cifScalarFields()['education_years'], '2016–2020');
\App\Services\Recruitment\CifEditor::renderAdminCifField('yr', \App\Services\Recruitment\CifFields::cifScalarFields()['yr'], '2020');
$adminHtml = ob_get_clean();
educationCheck(str_contains($adminHtml, 'value="2016"') && str_contains($adminHtml, 'value="2020"') && substr_count($adminHtml, 'data-education-year=') === 2, 'HR editor displays existing years separately');
educationCheck(!str_contains($adminHtml, 'Year graduated') && str_contains($adminHtml, 'type="hidden" name="yr" value="2020"'), 'Retain old graduation data without a visible input');
if (isset($argv[1])) {
    $base = 'file:///' . str_replace(' ', '%20', str_replace('\\', '/', dirname(__DIR__))) . '/pages/candidate/';
    $candidateHtml = str_replace('<head>', '<head><base href="' . $base . '"><script>window.fetch=async()=>new Response(JSON.stringify({csrf:"preview",admin:null,candidate:null}),{headers:{"Content-Type":"application/json"}});</script>', $candidateHtml);
    file_put_contents($argv[1], $candidateHtml);
}
echo "PASS: separate four-digit inclusive years, ordered years, legacy values and drafts retained, missing-end-year completion checks, retired graduation input and unchanged database storage.\n";
