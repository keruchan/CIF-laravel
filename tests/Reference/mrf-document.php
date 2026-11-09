<?php

declare(strict_types=1);
use App\Services\Recruitment\Mrf;
use App\Services\Recruitment\MrfDocument;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
date_default_timezone_set('Asia/Manila');
set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, $e::class.': '.$e->getMessage()."\n".$e->getTraceAsString());
    exit(1);
});
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$expected = ['requestor_name' => 'Demo Paper Requestor', 'requestor_position' => 'IT Supervisor', 'position_title' => 'Engineering Support Staff', 'construction_phase' => 'Phase 2', 'site_location' => 'Demo IT Office', 'headcount' => '3', 'reason' => 'Additional manpower', 'replacement_of' => '', 'reason_details' => 'Support the new office and employee workstations.', 'employment_status' => 'Regular', 'department_name' => 'IT', 'group_name' => 'Systems', 'education_level' => 'Bachelor’s degree', 'preferred_course' => 'Information Technology', 'years_experience' => '2', 'other_qualifications' => "Troubleshooting and networking experience.\nDocument equipment setup and follow security procedures.", 'job_roles' => "Resolve helpdesk tickets.\nMaintain computers and the office network.", 'remarks' => 'Fictional fixture only.', 'computer' => 'Laptop', 'table_chair' => 'Required', 'company_sim' => 'Not required', 'staff_house' => 'Not required', 'vehicle' => 'Not required', 'vehicle_unit' => '', 'buddy_1_name' => 'Demo IT Buddy', 'buddy_1_contact' => '+63-900-000-0000', 'buddy_2_name' => '', 'buddy_2_contact' => '', 'reviewed_reporting' => 'Demo Reporting Officer', 'reviewed_psd' => 'Demo PSD Reviewer', 'reviewed_housing' => 'Demo Housing Reviewer', 'reviewed_group' => 'Demo Group Reviewer', 'approved_department' => 'Demo IT Head'];
$text = "MANPOWER REQUEST FORM\nMRF NO.: 2025-999\nDATE REQUEST: October 1, 2025\n";
foreach (Mrf::mrfFields() as $fields) {
    foreach ($fields as $key => $field) {
        $text .= $field['label'].': '.$expected[$key]."\n";
    }
}
// Keep the standalone test inside its generated temporary fixture directory.
$directory = realpath($argv[1] ?? '');
$temporary = realpath(sys_get_temp_dir());
if (! $directory || ! $temporary || ! str_starts_with(strtolower($directory.DIRECTORY_SEPARATOR), strtolower($temporary.DIRECTORY_SEPARATOR)) || ! str_starts_with(basename($directory), 'cif-mrf-import-test-')) {
    throw new InvalidArgumentException('Use a generated MRF import temporary fixture directory.');
}
$result = MrfDocument::recognizeMrfText($text);
foreach ($expected as $key => $value) {
    if ($result['form'][$key] !== $value) {
        throw new RuntimeException($key.' recognized incorrectly: '.json_encode($result['form'][$key]).' instead of '.json_encode($value));
    }
}
$paragraphs = '';
foreach (explode("\n", trim($text)) as $line) {
    $paragraphs .= '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($line, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t></w:r></w:p>';
}
$xml = '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$paragraphs.'</w:body></w:document>';
$zip = $directory.'/filled-mrf.zip';
$archive = new PharData($zip);
$archive->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
$archive->addFromString('word/document.xml', $xml);
unset($archive);
rename($zip, $directory.'/filled-mrf.docx');
file_put_contents($directory.'/expected.json', json_encode(['form' => $expected, 'text' => $text], JSON_THROW_ON_ERROR));
$docx = MrfDocument::recognizeMrfText(MrfDocument::mrfDocxText($directory.'/filled-mrf.docx'));
$validated = Mrf::validateMrf($docx['form']);
if ($validated['buddy_1_contact'] !== '+63-900-000-0000' || $validated['headcount'] !== 3) {
    throw new RuntimeException('Recognized imports must validate phone numbers through Laravel services.');
}
if ($docx['form'] !== $expected) {
    throw new RuntimeException('DOCX recognition differs: '.json_encode($docx['form']));
}
$unsafeXml = '<!DOCTYPE document [<!ENTITY leak SYSTEM "file:///C:/Windows/win.ini">]><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>&leak;</w:t></w:r></w:p></w:body></w:document>';
foreach (['unsafe-xml' => $unsafeXml, 'macro' => $xml] as $name => $content) {
    $unsafeZip = $directory.'/'.$name.'.zip';
    $unsafe = new PharData($unsafeZip);
    $unsafe->addFromString('[Content_Types].xml', '<Types/>');
    $unsafe->addFromString('word/document.xml', $content);
    if ($name === 'macro') {
        $unsafe->addFromString('word/vbaProject.bin', 'Not an actual macro; security fixture.');
    }
    unset($unsafe);
    rename($unsafeZip, $directory.'/'.$name.'.docx');
    try {
        MrfDocument::mrfDocxText($directory.'/'.$name.'.docx');
        throw new RuntimeException('Unsafe Word fixture was not rejected: '.$name);
    } catch (InvalidArgumentException $error) {
    }
}
$checkbox = MrfDocument::recognizeMrfText("Employment status: ☐ Probationary ☒ Regular ☐ Project-based\nComputer / laptop: No\nTable & chair: Yes\nVehicle: none\n");
if ($checkbox['form']['employment_status'] !== 'Regular' || $checkbox['form']['computer'] !== 'Not required' || $checkbox['form']['table_chair'] !== 'Required' || $checkbox['form']['vehicle'] !== 'Not required') {
    throw new RuntimeException('Explicit checkbox/Yes/No option recognition failed.');
}
$unchecked = MrfDocument::recognizeMrfText("Employment status: ☐ Probationary ☐ Regular ☐ Project-based\n");
if ($unchecked['form']['employment_status'] !== '') {
    throw new RuntimeException('Unchecked options must stay unanswered.');
}
$blankPath = 'C:/Users/APEC Homes/Downloads/Manpower Request Form V5 2025.pdf';
if (is_file($blankPath) && MrfDocument::recognizeMrfText(MrfDocument::mrfPdfText($blankPath))['recognized']) {
    throw new RuntimeException('Blank source PDF template must not be recognized as completed data.');
}
echo "PASS: all MRF fields recognized from text/DOCX; blank PDF template yields no fabricated answers.\n";
