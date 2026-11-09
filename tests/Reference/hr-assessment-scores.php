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
function scoreCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
$scores = \App\Services\Recruitment\HrFields::cifHrScoreFields();
scoreCheck(count($scores) === 4, 'Exactly four exam score fields');
foreach ($scores as $key => $label) {
    foreach (['0', '59.99', '60', '70', '80', '90', '100', '99.99', ''] as $value) {
        scoreCheck(\App\Services\Recruitment\HrFields::validateHrReview([$key => $value])[$key] === $value, 'Valid numeric score or unfinished blank can save');
    }
    foreach (['-1', '100.01', '101', 'abc', '1e2', '3.14159'] as $value) {
        try {
            \App\Services\Recruitment\HrFields::validateHrReview([$key => $value]);
            throw new RuntimeException('Invalid score accepted: ' . $value);
        } catch (InvalidArgumentException $expected) {
        }
    }
    $missing = \App\Services\Recruitment\CandidateStatuses::cifCompletionMissing(['form' => [], 'hr' => []]);
    scoreCheck(!\App\Services\Recruitment\HrFields::cifHrRequired($key) && !in_array('HR: ' . $label, $missing, true), 'Blank exam scores do not block finishing the interview');
    scoreCheck(!in_array('HR: ' . $label, \App\Services\Recruitment\CandidateStatuses::cifCompletionMissing(['form' => [], 'hr' => [$key => '0']]), true), 'Zero is a real answer');
}
scoreCheck(!\App\Services\Recruitment\HrFields::cifHrRequired('drivers_license_code'), 'Driver license is optional');
scoreCheck(!in_array('HR: Driver’s license restriction code', \App\Services\Recruitment\CandidateStatuses::cifCompletionMissing(['form' => [], 'hr' => []]), true), 'Blank driver license does not block completion');
scoreCheck(\App\Services\Recruitment\HrFields::cifHrRequired('last_medical_examination') && \App\Services\Recruitment\HrFields::cifHrRequired('habitual_tardiness'), 'Other HR requirements remain required');
$form = ['consent' => true, 'background_consent' => true];
foreach (\App\Services\Recruitment\CifFields::cifScalarFields() as $key => $field) {
    if ($field['required']) {
        $form[$key] = $field['type'] === 'year_range' ? '2016–2020' : 'Provided';
    }
}
$hr = [];
foreach (\App\Services\Recruitment\HrFields::cifHrFields() as $fields) {
    foreach ($fields as $key => $label) {
        if (\App\Services\Recruitment\HrFields::cifHrRequired($key)) {
            $hr[$key] = 'Provided';
        }
    }
}
scoreCheck(\App\Services\Recruitment\CandidateStatuses::cifCompletionMissing(['form' => $form, 'hr' => $hr]) === [], 'Finish completion gate allows all exam scores and driver license to be omitted');
foreach (['0' => 1, '59.99' => 1, '60' => 2, '69.99' => 2, '70' => 3, '79.99' => 3, '80' => 4, '89.99' => 4, '90' => 5, '100' => 5] as $score => $rating) {
    scoreCheck(str_starts_with(\App\Services\Recruitment\HrFields::cifHrScoreRating($score), $rating . '/5'), 'Correct rating at score boundary');
}
scoreCheck(\App\Services\Recruitment\HrFields::cifHrScoreRating('') === '' && \App\Services\Recruitment\HrFields::cifHrScoreRating('101') === '', 'Blank and invalid values have no rating');
$legacy = ['md5_score' => 'Legacy MD5 answer', 'planning_judgement' => 'Legacy judgement note'];
$saved = \App\Services\Recruitment\HrFields::validateHrReview(['abstract_reasoning_score' => '85'], $legacy);
scoreCheck($saved['md5_score'] === $legacy['md5_score'] && $saved['planning_judgement'] === $legacy['planning_judgement'], 'Preserve historical assessment answers');
scoreCheck(json_decode(json_encode($saved, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR)['abstract_reasoning_score'] === '85', 'New scores round-trip through existing HR JSON storage');
ob_start();
?><form id="cif-editor" class="editor-card hr-card"><script type="application/json" id="hr-score-scale"><?php 
echo json_encode(\App\Services\Recruitment\HrFields::cifHrScoreScale(), JSON_THROW_ON_ERROR);
?></script><?php 
foreach (array_slice(\App\Services\Recruitment\HrFields::cifHrFields(), 0, 2, true) as $section => $fields) {
    echo '<section class="hr-score-group"><h2>' . \App\Services\Recruitment\CandidateView::cifEscape($section) . '</h2><div class="editor-grid">';
    foreach ($fields as $key => $label) {
        \App\Services\Recruitment\CifEditor::renderAdminCifField('hr[' . $key . ']', ['label' => $label, 'type' => 'rating', 'max' => 1000, 'options' => []], '85');
    }
    echo '</div></section>';
}
echo '</form>';
$html = ob_get_clean();
$dom = new DOMDocument();
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);
scoreCheck($xpath->query('//input[@type="number" and @min="0" and @max="100" and @data-hr-score]')->length === 4, 'All four fields render bounded numeric inputs');
scoreCheck($xpath->query('//output[contains(text(),"4/5")]')->length === 4, 'Saved ratings render correctly');
scoreCheck($xpath->query('//input[@data-hr-score and @data-assessment-required]')->length === 0, 'Client completion checks do not require optional exam scores');
scoreCheck($xpath->query('//label/span[contains(@class,"optional-label")]')->length === 4, 'Each exam score has an optional label');
if (isset($argv[1])) {
    $base = 'file:///' . str_replace(' ', '%20', str_replace('\\', '/', dirname(__DIR__))) . '/pages/admin/';
    $css = file_get_contents(dirname(__DIR__) . '/css/cif-record.css');
    file_put_contents($argv[1], '<!doctype html><html lang="en"><head><meta charset="utf-8"><base href="' . $base . '"><style>' . $css . '</style></head><body>' . $html . '<script src="../../js/hr-scores.js"></script></body></html>');
}
echo "PASS: four optional bounded scores, optional driver license, five rating levels, finish without scores/license, other completion requirements, legacy preservation and numeric HR storage.\n";
