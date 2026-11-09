<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class CifPrint
{
    // Keep the supplied REC-REV-24-03 SBR paper layout independent of the web editor.
    public static function renderCifPrint(array $candidate): void
    {
        $f = $candidate['form'];
        $hr = $candidate['hr'];
        $cell = static function (string $label, mixed $text, int $span = 1, string $key = ''): void {
            echo '<td colspan="'.$span.'"'.($key !== '' ? ' data-print-field="'.CandidateView::cifEscape($key).'"' : '').'><b>'.CandidateView::cifEscape($label).'</b><div>'.nl2br(CandidateView::cifEscape($text ?? '')).'</div></td>';
        };
        $field = static function (string $name, string $label, int $span = 1) use ($f, $cell): void {
            $cell($label, $f[$name] ?? '', $span, $name);
        };
        $heading = static fn (string $label) => print '<h2 class="paper-band">'.CandidateView::cifEscape($label).'</h2>';
        $rows = static function (string $group, int $count) use ($f, $cell): void {
            $definition = CifFields::cifDefinition()['groups'][$group];
            echo '<table class="paper-table">';
            for ($i = 0; $i < $count; $i++) {
                $row = $f[$group][$i] ?? [];
                echo '<tr>';
                foreach ($definition['fields'] as $name => $field) {
                    $cell($field['label'], $row[$name] ?? '', 1, $group.'.'.$i.'.'.$name);
                }
                echo '</tr>';
            }
            echo '</table>';
        };
        $job = static function (int $index) use ($f, $cell): void {
            $row = $f['employment'][$index] ?? [];
            $entry = static function (string $key, string $label, int $span) use ($row, $cell, $index): void {
                $cell($label, $row[$key] ?? '', $span, 'employment.'.$index.'.'.$key);
            };
            echo '<table class="paper-table job-table"><colgroup>'.str_repeat('<col>', 10).'</colgroup><tr>';
            $entry('company_name', 'Company name', 4);
            $entry('company_address', 'Company address', 3);
            $entry('position_title', 'Position title', 3);
            echo '</tr><tr>';
            $entry('employment_period', 'Employment period', 3);
            $entry('previous_salary', 'Previous salary (PHP)', 2);
            $entry('reason_for_separation', 'Reason for separation', 2);
            $cell('Immediate superior / contact', implode(' / ', array_filter([$row['immediate_superior'] ?? '', $row['superior_contact'] ?? ''], static fn ($value) => $value !== '')), 3, 'employment.'.$index.'.superior');
            echo '</tr></table>';
        };
        $certification = 'I further certify that the information I have indicated in this Candidate Information Form are true, accurate and complete and contains no false or misleading statements or no omission of any material facts that should have been stated therein. I am fully aware that any misrepresentation thereof shall be sufficient cause for the company to immediately terminate my service and/or application.';
        $background = 'In compliance with RA 10173, otherwise known as the Data Privacy Act of 2012, I hereby authorize APEC Homes and its designated representatives to collect and process information relating to my current and previous employment. I also expressly give my consent to current and previous employers to release and disclose information that APEC Homes may find significant to my application. I acknowledge that the processing of my information is necessary and is related to the fulfillment of or prior to entering into a contract with APEC Homes.';
        $declaration = static function (string $text, ?string $name, string $evidence = ''): void {
            echo '<div class="paper-declaration"><p>'.CandidateView::cifEscape($text).'</p><strong>'.CandidateView::cifEscape($name ?: '________________________________').'</strong><div>Signature Over Candidate Name</div><small>'.CandidateView::cifEscape($evidence).'</small></div>';
        };
        $questions = static function (string $section) use ($hr): void {
            $fields = HrFields::cifHrFields()[$section];
            $keys = array_keys($fields);
            echo '<table class="paper-table hr-paper-table'.($section === 'Health & lifestyle information' ? ' health-paper-table' : '').'">';
            for ($i = 0; $i < count($keys); $i += 2) {
                echo '<tr>';
                foreach (array_slice($keys, $i, 2) as $key) {
                    $label = match ($key) {
                        'disciplinary_others', 'health_others' => 'Others',
                        'drivers_license_code' => "Driver's license res. code",
                        default => $fields[$key],
                    };
                    echo '<th>'.CandidateView::cifEscape($label).'</th><td data-print-field="hr.'.CandidateView::cifEscape($key).'"><div>'.nl2br(CandidateView::cifEscape($hr[$key] ?? '')).'</div></td>';
                }
                echo '</tr>';
            }
            echo '</table>';
        };
        ?>
    <div class="print-document cif-document">
    <section class="paper-sheet">
     <div class="paper-header"><?php
        Branding::renderCifBrandLogo('paper-logo');
        ?><h1>CANDIDATE INFORMATION FORM</h1><p class="paper-id">APPLICANT ID: <?php
        echo CandidateView::cifEscape($candidate['candidate_number']);
        ?></p><div class="photo-box"><?php
        if ($photo = CifPhoto::cifPhotoSource($f['id_photo'] ?? '')) {
            ?><img src="<?php
            echo CandidateView::cifEscape($photo);
            ?>" alt="Candidate upper-body ID picture"><?php
        } else {
            ?>ID PICTURE<?php
        }
        ?></div></div>
     <?php
        if (! empty($f['demo_record'])) {
            ?><p class="paper-demo">DEMO — Fictional candidate information; simulated declarations.</p><?php
        }
        ?>
     <div class="paper-application"><p><b>DATE APPLIED:</b> <span><?php
        echo CandidateView::cifEscape(CandidateView::cifLocalDate($candidate['submitted_at']));
        ?></span></p><p><b>POSITION APPLIED:</b> <span><?php
        echo CandidateView::cifEscape($f['desired_position'] ?? $candidate['position'] ?? '');
        ?></span></p><p><b>EXPECTED SALARY:</b> <span><?php
        echo CandidateView::cifEscape($f['expected_salary'] ?? '');
        ?></span></p><p><b>APPLIED VIA:</b> <span><?php
        echo CandidateView::cifEscape($f['applied_via'] ?? '');
        ?></span></p></div>
     <?php
        $heading('I.     PERSONAL INFORMATION');
        ?>
     <table class="paper-table personal-paper"><colgroup><?php
        echo str_repeat('<col>', 10);
        ?></colgroup>
      <tr><?php
        $field('ln', 'Last name', 3);
        $cell('First name', trim(($f['fn'] ?? '').' '.($f['sx'] ?? '')), 3, 'fn');
        $field('mn', 'Middle name', 2);
        $field('nickname', 'Nickname', 2);
        ?></tr>
      <tr><?php
        $field('adr', 'Present address', 8);
        $field('mob', 'Contact number', 2);
        ?></tr>
      <tr><?php
        $field('permanent_address', 'Permanent address', 8);
        $field('cs', 'Civil status', 2);
        ?></tr>
      <tr><?php
        $field('place_of_birth', 'Place of birth', 3);
        $field('dob', 'Date of birth', 2);
        $field('height_cm', 'Height (cm)', 1);
        $field('weight_kg', 'Weight (kg)', 2);
        $field('religion', 'Religion', 2);
        ?></tr>
      <tr><?php
        $field('ecn', 'Person to contact in case of emergency', 3);
        $field('emergency_address', 'Address', 3);
        $field('ecp', 'Contact number', 2);
        $field('ecr', 'Relationship', 2);
        ?></tr>
      <tr><?php
        $field('sex', 'Sex', 3);
        $field('nat', 'Nationality', 4);
        $field('blood_type', 'Blood type', 3);
        ?></tr>
      <tr><?php
        $field('contact_email', 'Email', 5);
        $field('facebook_account', 'Facebook account link / name', 5);
        ?></tr>
      <tr><?php
        $field('sss_number', 'SSS number', 2);
        $field('pagibig_number', 'Pag-IBIG number', 3);
        $field('philhealth_number', 'PhilHealth number', 3);
        $field('tin_number', 'TIN', 2);
        ?></tr>
     </table>
     <?php
        $heading('II.     EDUCATIONAL PROFILE');
        ?>
     <table class="paper-table education-paper"><tr><?php
        $field('edu', 'Highest educational attainment');
        $cell('Course taken', $f['crs'] ?? '', 1, 'crs');
        $field('education_years', 'Inclusive years (from–to)');
        ?></tr><tr><?php
        $field('licensure_exam', 'Licensure');
        $field('licensure_month', 'Month & year taken');
        $field('board_rating', 'Board rating');
        ?></tr><tr><?php
        $field('sch', 'School / university');
        $field('yr', 'Year graduated');
        $field('distinctions', 'Distinctions, if any');
        ?></tr></table>
     <?php
        $heading('III.     AWARDS & ACHIEVEMENTS');
        $rows('awards', 2);
        $heading('IV.     EXTRA-CURRICULAR ACTIVITIES');
        $rows('activities', 2);
        ?>
     <?php
        $heading('V.     EMPLOYMENT RECORDS');
        ?>
     <p class="paper-employment-status"><b>CURRENT EMPLOYMENT:</b> <?php
        echo CandidateView::cifEscape($f['emp'] ?? '');
        ?></p>
     <?php
        $job(0);
        ?>
     <footer>REC-REV-24-03 SBR · 1</footer>
    </section>
    <section class="paper-sheet">
     <?php
        $job(1);
        $job(2);
        $heading('VI.     CHARACTER REFERENCE');
        $rows('references', 2);
        $heading('VII.     APEC CONNECTIONS');
        ?>
     <table class="paper-table connections-paper"><?php
        for ($index = 0; $index < 2; $index++) {
            $connection = $f['connections'][$index] ?? [];
            ?><tr><td><b>Name</b> <span data-print-field="connections.<?php
            echo $index;
            ?>.name"><?php
            echo CandidateView::cifEscape($connection['name'] ?? '');
            ?></span></td><td><b>Relationship</b> <span data-print-field="connections.<?php
            echo $index;
            ?>.relationship"><?php
            echo CandidateView::cifEscape($connection['relationship'] ?? '');
            ?></span></td></tr><?php
        }
        ?></table>
     <?php
        $heading('VIII.     CERTIFICATION OF INFORMATION');
        $declaration($certification, $f['certification_name'] ?? null, ! empty($f['consent']) ? 'Typed acknowledgement recorded · '.CandidateView::cifLocalDate($f['accepted_at'] ?? null) : 'Acceptance not recorded');
        $heading('IX.     BACKGROUND CHECK CONSENT');
        $declaration($background, $f['background_check_name'] ?? null, ! empty($f['background_consent']) ? 'Typed acknowledgement recorded' : 'Acceptance not recorded');
        ?>
     <h3 class="hr-print-divider">--------------------------------------------------FOR HR USE--------------------------------------------------</h3>
     <?php
        $heading('I.     DISCIPLINARY RECORDS');
        $questions('Disciplinary records');
        ?>
     <footer>REC-REV-24-03 SBR · 2</footer>
    </section>
    <section class="paper-sheet paper-sheet-natural">
     <?php
        $heading('II.     HEALTH & LIFESTYLE INFORMATION');
        $questions('Health & lifestyle information');
        ?>
     <footer>REC-REV-24-03 SBR · 3</footer>
    </section>
    </div>
    <?php
    }
}
