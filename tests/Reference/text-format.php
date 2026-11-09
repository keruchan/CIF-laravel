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
function sameFormat(mixed $actual, mixed $expected): void
{
    if ($actual !== $expected) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
sameFormat(\App\Services\Recruitment\TextFormat::cifProperText("hELLO WORLD!\nplease CONTACT HR for sss, tin and philhealth."), "Hello World!\nPlease Contact HR For SSS, TIN And PhilHealth.");
sameFormat(\App\Services\Recruitment\TextFormat::cifProperText("don't CHANGE it; IT support. pag-ibig / n/a."), "Don't Change It; IT Support. Pag-IBIG / N/A.");
sameFormat(\App\Services\Recruitment\TextFormat::cifProperText('éCOLE NIÑO – maría’s résumé'), 'École Niño – María’s Résumé');
$protected = 'https://Example.com/MyPath?Token=aBcD www.Example.com/Case Contact.Name+Tag@Example.com @Mixed.Handle #MixedHashTag EMP-aB12 12-a v2.6 `caseSensitive()`';
sameFormat(\App\Services\Recruitment\TextFormat::cifProperText($protected), $protected);
sameFormat(\App\Services\Recruitment\TextFormat::cifProperText('C:\Users\MixedCase\OriginalFile.docx'), 'C:\Users\MixedCase\OriginalFile.docx');
foreach (['fn', 'mn', 'ln', 'sx', 'ecn', 'name', 'certification_name', 'background_check_name', 'recertification_name', 'immediate_superior', 'requestor_name', 'buddy_1_name', 'reviewed_psd'] as $key) {
    sameFormat(\App\Services\Recruitment\TextFormat::cifSavedText("María o'brien III", $key), "MARÍA O'BRIEN III");
}
foreach (['desired_position', 'position_title', 'employee_id', 'sss_number', 'philhealth_number', 'pagibig_number', 'tin_number', 'drivers_license_code', 'vehicle_unit', 'house_number'] as $key) {
    sameFormat(\App\Services\Recruitment\TextFormat::cifSavedText('aBc-123', $key), 'aBc-123');
}
foreach (['select', 'email', 'tel', 'photo', 'date', 'month', 'year', 'money', 'measurement', 'rating'] as $type) {
    sameFormat(\App\Services\Recruitment\TextFormat::cifSavedText('Case-Sensitive', 'test', $type), 'Case-Sensitive');
}
sameFormat(\App\Services\Recruitment\TextFormat::cifSavedText('mArIa dEla cRuZ', 'facebook_account'), 'MARIA DELA CRUZ');
sameFormat(\App\Services\Recruitment\TextFormat::cifSavedText('https://facebook.com/Mixed.Case', 'facebook_account'), 'https://facebook.com/Mixed.Case');
sameFormat(\App\Services\Recruitment\TextFormat::cifSavedText('Mixed.Handle', 'facebook_account'), 'Mixed.Handle');
$draft = \App\Services\Recruitment\ApplicantAccess::validateCifDraft(['fn' => ' aNa ', 'ln' => 'dELA cRUZ', 'contact_email' => 'Unfinished@', 'sss_number' => 'aBc-001', 'desired_position' => 'Engineering Support Staff', 'present_street' => 'mAIN stREET', 'present_house_number' => '12-a', 'separate_permanent_address' => ['province' => 'bULACAN'], 'employment' => [['reason_for_separation' => 'mOVED TO aPEC homes', 'position_title' => 'it support']], 'references' => [['name' => 'bOb sANTOS']]]);
sameFormat($draft['fn'], ' ANA ');
sameFormat($draft['ln'], 'DELA CRUZ');
sameFormat($draft['contact_email'], 'Unfinished@');
sameFormat($draft['sss_number'], 'aBc-001');
sameFormat($draft['desired_position'], 'Engineering Support Staff');
sameFormat($draft['present_street'], 'Main Street');
sameFormat($draft['present_house_number'], '12-a');
sameFormat($draft['separate_permanent_address']['province'], 'Bulacan');
sameFormat($draft['employment'][0]['reason_for_separation'], 'Moved To APEC Homes');
sameFormat($draft['references'][0]['name'], 'BOB SANTOS');
sameFormat(\App\Services\Recruitment\ApplicantAccess::validateCifDraft($draft), $draft);
$form = \App\Services\Recruitment\CandidateStorage::validateCifInformation(['fn' => 'aNa', 'distinctions' => 'BEST EMPLOYEE in apec', 'sex' => 'Male', 'facebook_account' => 'https://facebook.com/aNa', 'references' => [['name' => 'cHarLie REYES']]], false);
sameFormat($form['fn'], 'ANA');
sameFormat($form['distinctions'], 'Best Employee In APEC');
sameFormat($form['sex'], 'Male');
sameFormat($form['references'][0]['name'], 'CHARLIE REYES');
$hr = \App\Services\Recruitment\HrFields::validateHrReview(['recertification_name' => 'aNa dela cruz', 'hr_notes' => "review WITH HR\nsee https://Example.com/Case", 'habitual_tardiness' => 'Reported'], [], ['habitual_tardiness' => 'late ON monday']);
sameFormat($hr['recertification_name'], 'ANA DELA CRUZ');
sameFormat($hr['hr_notes'], "Review With HR\nSee https://Example.com/Case");
sameFormat($hr['habitual_tardiness'], 'Reported: Late On Monday');
sameFormat(\App\Services\Recruitment\HrFields::validateHrReview($hr, $hr), $hr);
sameFormat(\App\Services\Recruitment\CandidateInterviews::validateCifInterview(['decision' => 'Pending Decision', 'remarks' => 'contact HR tomorrow.'])['remarks'], 'Contact HR Tomorrow.');
$mrf = \App\Services\Recruitment\Mrf::formatSavedMrf(['requestor_name' => 'ana reyes', 'site_location' => 'mALOLOS bULACAN', 'reason' => 'Additional manpower', 'position_title' => 'Engineering Support Staff', 'buddy_1_name' => 'bOB cRUZ', 'reason_details' => 'please ISSUE a laptop.', 'department_name' => 'it', 'vehicle_unit' => 'aBc-001']);
sameFormat($mrf['requestor_name'], 'ANA REYES');
sameFormat($mrf['site_location'], 'Malolos Bulacan');
sameFormat($mrf['reason'], 'Additional manpower');
sameFormat($mrf['position_title'], 'Engineering Support Staff');
sameFormat($mrf['buddy_1_name'], 'BOB CRUZ');
sameFormat($mrf['reason_details'], 'Please Issue A Laptop.');
sameFormat($mrf['department_name'], 'IT');
sameFormat($mrf['vehicle_unit'], 'aBc-001');
sameFormat(\App\Services\Recruitment\Mrf::formatSavedMrf($mrf), $mrf);
try {
    \App\Services\Recruitment\CandidateStorage::cifValue(str_repeat('ß', 100), \App\Services\Recruitment\CifFields::cifScalarFields()['fn'], null, 'fn');
    throw new RuntimeException('Expansion must respect the field limit.');
} catch (InvalidArgumentException) {
}
echo "PASS: Unicode Proper Case, uppercase personal names, protected identifiers/links/credentials/catalogues, draft/address/group/HR/MRF formatting, idempotence and post-format length checks.\n";
