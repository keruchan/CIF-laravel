<?php

declare(strict_types=1);
use App\Services\Recruitment\CandidateStorage;
use App\Services\Recruitment\CifFields;
use App\Services\Recruitment\HiringPositions;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
date_default_timezone_set('Asia/Manila');

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function governmentIdCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$optional = ['sss_number', 'philhealth_number', 'pagibig_number', 'tin_number', 'facebook_account', 'height_cm', 'weight_kg'];
$fields = CifFields::cifScalarFields();
$input = array_fill_keys(array_keys($fields), '');
$input = array_replace($input, [
    'desired_position' => HiringPositions::cifAppliedPositions()[0], 'applied_via' => 'Walk-In',
    'fn' => 'Maria', 'ln' => 'Santos', 'place_of_birth' => 'Malolos, Bulacan',
    'dob' => '1998-06-15', 'height_cm' => '165', 'weight_kg' => '60',
    'religion' => 'Christian', 'cs' => 'Single', 'sex' => 'Female', 'nat' => 'Filipino',
    'mob' => '+63-900-000-0000', 'contact_email' => 'maria.santos@example.com',
    'facebook_account' => 'Maria Santos', 'ecn' => 'Teresa Santos',
    'ecp' => '+63-900-000-0001', 'ecr' => 'Mother', 'edu' => "Bachelor's degree",
    'sch' => 'Bulacan State University', 'crs' => 'Business Administration',
    'education_years' => '2016-2020', 'emp' => 'Unemployed',
    'certification_name' => 'Maria Santos', 'background_check_name' => 'Maria Santos',
    'consent' => true, 'background_consent' => true,
]);
foreach (['present', 'permanent', 'emergency'] as $group) {
    foreach (['barangay' => 'San Pablo', 'municipality' => 'Malolos', 'province' => 'Bulacan'] as $key => $value) {
        $input[$group.'_'.$key] = $value;
    }
}

foreach ($optional as $key) {
    $input[$key] = '';
}
$saved = CandidateStorage::validateCandidateCif($input);
foreach ($optional as $key) {
    governmentIdCheck($fields[$key]['required'] === false && $saved[$key] === '', $key.' accepts a blank on submission');
    unset($input[$key]);
}
$omitted = CandidateStorage::validateCandidateCif($input);
foreach ($optional as $key) {
    governmentIdCheck($omitted[$key] === '', $key.' can be omitted');
}
$supplied = ['sss_number' => '12-3456789-0', 'philhealth_number' => '12-345678901-2', 'pagibig_number' => '1234-5678-9012', 'tin_number' => '123-456-789-000', 'facebook_account' => 'https://facebook.com/maria.test', 'height_cm' => '165', 'weight_kg' => '60'];
$saved = CandidateStorage::validateCandidateCif(array_replace($input, $supplied));
foreach ($supplied as $key => $value) {
    governmentIdCheck($saved[$key] === $value, 'Preserve supplied '.$key);
}
foreach (['fn', 'ln', 'sex', 'contact_email', 'education_years'] as $key) {
    governmentIdCheck($fields[$key]['required'] === true, 'Other required fields remain required');
}
foreach ($optional as $key) {
    try {
        CandidateStorage::validateCandidateCif(array_replace($input, [$key => str_repeat('1', $fields[$key]['max'] + 1)]));
        throw new RuntimeException('An overlong ID should still fail.');
    } catch (InvalidArgumentException $expected) { /* Existing length limits remain. */
    }
}

ob_start();
echo view('recruitment.candidate.index')->render();
$html = ob_get_clean();
$dom = new DOMDocument;
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);
foreach ($optional as $key) {
    governmentIdCheck($xpath->query('//input[@name="'.$key.'" and not(@required)]')->length === 1, 'Rendered '.$key.' is optional');
    governmentIdCheck($xpath->query('//label[@for="field-'.$key.'"]/span[@data-optional-label and contains(text(),"optional")]')->length === 1, 'Show the standard optional label beside '.$key);
}
foreach (['height_cm', 'weight_kg'] as $key) {
    foreach (['0', '-1', '1001', 'invalid'] as $value) {
        try {
            CandidateStorage::validateCandidateCif(array_replace($input, [$key => $value]));
            throw new RuntimeException('Invalid supplied measurements must still fail.');
        } catch (InvalidArgumentException $expected) {
            // Optional measurements retain their existing validation when provided.
        }
    }
}
echo "PASS: optional IDs, Facebook, height and weight render correctly, accept blanks/omissions, preserve supplied values and retain required-field, length and measurement validation. No database changes or email.\n";
