<?php
declare(strict_types=1);

namespace App\Services\Recruitment;

final class CifEditor
{
    public static function renderAdminCifField(string $name, array $field, mixed $value, bool $readonly = false): void
    {
        if ($name === 'yr') {
            echo '<input type="hidden" name="yr" value="'.CandidateView::cifEscape($value).'">';

            return;
        }
        $id = 'edit-'.preg_replace('/[^a-zA-Z0-9_-]/', '-', $name);
        $type = $field['type'];
        if ($type === 'year_range') {
            CifEducation::renderCifEducationYears($id, $value, ! empty($field['required']), true, $readonly);

            return;
        }
        $attrs = ' id="'.CandidateView::cifEscape($id).'" name="'.CandidateView::cifEscape($name).'" maxlength="'.($type === 'year' ? 4 : $field['max']).'"';
        if ($readonly) {
            $attrs .= ' readonly';
        }
        $hrField = preg_match('/^hr\[(\w+)\]$/', $name, $match) === 1;
        $scoreField = $hrField && isset(HrFields::cifHrScoreFields()[$match[1]]);
        $assessmentRequired = $hrField && HrFields::cifHrRequired($match[1]);
        if ($assessmentRequired) {
            $attrs .= ' data-assessment-required="true"';
        }
        if (! $hrField) {
            $attrs .= ' data-candidate-label="'.CandidateView::cifEscape($field['label']).'" data-candidate-required="'.(! empty($field['required']) ? 'true' : 'false').'"';
        }
        echo '<div class="editor-field'.(in_array($type, ['textarea', 'photo'], true) ? ' wide' : '').'">'.($type === 'photo' ? '<div class="photo-field-label">' : '<label for="'.CandidateView::cifEscape($id).'">').CandidateView::cifEscape($field['label']);
        if ($assessmentRequired) {
            echo ' <span class="required-mark" aria-label="required for assessment completion">*</span>';
        } elseif ($hrField) {
            echo ' <span class="optional-label">Optional</span>';
        }
        echo $type === 'photo' ? '</div>' : '</label>';
        if ($name === 'desired_position') {
            HiringPositions::renderCifPositionPicker($id, $attrs, (string) ($value ?? ''));
        } elseif ($type === 'photo') {
            CifPhoto::renderCifPhoto($attrs, $value);
        } elseif ($scoreField) {
            $rating = HrFields::cifHrScoreRating($value);
            echo '<input type="number"'.$attrs.' min="0" max="100" step="0.01" inputmode="decimal" placeholder="0–100" data-hr-score aria-describedby="'.CandidateView::cifEscape($id.'-rating').'" value="'.CandidateView::cifEscape($value).'">';
            echo '<output id="'.CandidateView::cifEscape($id.'-rating').'" class="hr-score-rating" for="'.CandidateView::cifEscape($id).'" aria-live="polite">'.CandidateView::cifEscape($rating ?: 'Not scored').'</output>';
        } elseif ($type === 'hr-choice') {
            $choices = $field['options'];
            $detail = '';
            $selected = (string) $value;
            foreach ($choices as $choice) {
                if (str_starts_with($selected, $choice.': ')) {
                    $detail = substr($selected, strlen($choice) + 2);
                    $selected = $choice;
                    break;
                }
            }
            $legacy = $selected !== '' && ! in_array($selected, $choices, true);
            if ($legacy) {
                $selected = '';
                $aliases = ['Present' => 'Reported', 'Current smoker' => 'Reported', 'Former smoker' => 'Reported', 'Occasional' => 'Reported', 'Regular' => 'Reported', 'Non-smoker' => 'None reported', 'Does not drink' => 'None reported'];
                foreach ($aliases as $old => $choice) {
                    if (preg_match('/^'.preg_quote($old, '/').'(?:$|[\s,:;(])/iu', (string) $value)) {
                        $selected = $choice;
                        if ($choice === 'Reported') {
                            $detail = (string) $value;
                        }
                        break;
                    }
                }
                foreach ($choices as $choice) {
                    if (preg_match('/^'.preg_quote($choice, '/').'(?:$|[\s,:;(])/iu', (string) $value)) {
                        $selected = $choice;
                        break;
                    }
                }
            }
            echo '<select'.$attrs.' data-hr-choice="'.CandidateView::cifEscape($match[1]).'"'.($legacy ? ' data-original-answer="'.CandidateView::cifEscape($value).'"' : '').'><option value="">Not assessed</option>';
            foreach ($choices as $choice) {
                echo '<option'.($choice === $selected ? ' selected' : '').'>'.CandidateView::cifEscape($choice).'</option>';
            }
            echo '</select>';
            if ($legacy) {
                echo '<p class="saved-answer-note">Saved note: '.CandidateView::cifEscape($value).'</p>';
            }
            echo '<div class="hr-answer-details"'.($selected === 'Reported' ? '' : ' hidden').'><label for="'.CandidateView::cifEscape($id.'-details').'">Details <span class="optional-label">Optional</span></label><textarea id="'.CandidateView::cifEscape($id.'-details').'" name="hr_details['.CandidateView::cifEscape($match[1]).']" maxlength="950" rows="3">'.CandidateView::cifEscape($detail).'</textarea></div>';
        } elseif ($type === 'select') {
            echo '<select'.$attrs.'><option value="">Not provided</option>';
            if ($value !== null && $value !== '' && ! in_array($value, $field['options'], true)) {
                echo '<option selected value="'.CandidateView::cifEscape($value).'">'.CandidateView::cifEscape($value).' (previously saved)</option>';
            }
            foreach ($field['options'] as $option) {
                echo '<option'.($option === $value ? ' selected' : '').'>'.CandidateView::cifEscape($option).'</option>';
            }
            echo '</select>';
        } elseif ($type === 'textarea') {
            echo '<textarea'.$attrs.' rows="3">'.CandidateView::cifEscape($value).'</textarea>';
        } elseif ($type === 'year') {
            echo '<input type="text"'.$attrs.' inputmode="numeric" pattern="[0-9]{4}" placeholder="YYYY" data-year data-year-max="'.date('Y').'" value="'.CandidateView::cifEscape($value).'">';
        } else {
            $htmlType = in_array($type, ['money', 'rating', 'measurement', 'year'], true) ? 'number' : $type;
            $range = match ($type) {
                'money' => ' min="0" max="9999999999.99" step="0.01"', 'rating' => ' min="0" max="100" step="0.01"', 'measurement' => ' min="0.01" max="1000" step="0.01"', 'year' => ' min="1900" max="'.date('Y').'" step="1"', 'date' => ' max="'.date('Y-m-d').'"', 'month' => ' max="'.date('Y-m').'"', default => ''
            };
            echo '<input type="'.$htmlType.'"'.$attrs.$range.' value="'.CandidateView::cifEscape($value).'">';
        }
        echo '</div>';
    }

    public static function renderCifEditor(array $candidate): void
    {
        $f = $candidate['form'];
        $hr = $candidate['hr'];
        $definition = CifFields::cifDefinition();
        $readOnly = CandidateDeployment::cifCandidateIsDeployed($candidate);
        ?>
    <div class="record-screen"<?= $readOnly ? ' data-record-readonly="true"' : '' ?>>
     <?php if ($readOnly) { ?><p class="deployed-record-notice" role="status">Deployment completed. This CIF and all hiring assessments are view-only. You can still view each stage and print the record.</p><?php } ?>
     <section class="candidate-hero has-roadmap"><div class="avatar"><?= CandidateView::cifEscape(mb_substr($f['fn'] ?? '', 0, 1).mb_substr($f['ln'] ?? '', 0, 1)) ?></div><div class="candidate-identity"><div class="candidate-profile-meta"><span class="eyebrow">CANDIDATE PROFILE · <?= CandidateView::cifEscape($candidate['candidate_number']) ?></span><span class="status-pill"><?= CandidateView::cifEscape(CandidateStatuses::cifCandidateStatusLabel($candidate['status'])) ?><?= $candidate['archived_at'] ? ' · Archived' : '' ?></span></div><h1><?= CandidateView::cifEscape(trim(($f['fn'] ?? '').' '.($f['mn'] ?? '').' '.($f['ln'] ?? ''))) ?></h1><p><?= CandidateView::cifEscape($candidate['position'] ?: 'Position not specified') ?> <span>·</span> <?= CandidateView::cifEscape($candidate['email']) ?></p></div><?php CandidateRoadmap::renderCifHiringRoadmap($candidate); ?></section>
     <?php HrStaff::renderCifHrCredits((int) $candidate['id']); ?>
     <?php if (! empty($f['demo_record'])) { ?><div class="demo-note">Demo profile — fictional information and simulated declarations.</div><?php } ?>
     <div class="record-layout"><nav class="section-nav" aria-label="CIF sections"><p>ON THIS PAGE</p><a href="#hr-review">HR interview</a><?php foreach ($definition['sections'] as $section => $fields) { ?><a href="#section-<?= array_search($section, array_keys($definition['sections']), true) ?>"><?= CandidateView::cifEscape($section) ?></a><?php } ?><div class="revision-note"><b>Original submission</b><span><?= CandidateView::cifEscape(CandidateView::cifLocalDate($candidate['submitted_at'])) ?></span><?php if ($candidate['revision']) { ?><b>Last HR update</b><span><?= CandidateView::cifEscape($candidate['editor']) ?><br><?= CandidateView::cifEscape(CandidateView::cifLocalDate($candidate['edited_at'])) ?></span><?php } ?></div></nav>
     <div class="editor-content"><form id="cif-editor" novalidate>
      <script type="application/json" id="hr-score-scale"><?= json_encode(HrFields::cifHrScoreScale(), JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?></script>
      <fieldset class="cif-record-fields"<?= $readOnly ? ' disabled' : '' ?>><legend class="roadmap-sr-only">Candidate information and HR assessment</legend>
      <div id="stage-panel-initial_interview" role="tabpanel" aria-labelledby="roadmap-tab-initial_interview" data-record-panel="initial_interview">
      <?php $screeningBlocked = CandidateInterviews::cifInterviewPrerequisite($candidate, 'initial_interview');
        if ($screeningBlocked) { ?><p class="stage-prerequisite" role="status"><?= CandidateView::cifEscape($screeningBlocked) ?></p><?php } ?>
      <fieldset class="initial-interview-fields"<?= $screeningBlocked ? ' disabled' : '' ?>><legend class="roadmap-sr-only">HR interview assessment</legend>
      <section id="hr-review" class="editor-card hr-card"><div class="card-title"><span class="section-number">HR</span><div><h2>HR interview</h2><p><span class="required-mark">*</span> Required to complete the assessment. Exam scores and driver's license are optional. Partial assessments can be saved.</p></div><span id="hr-completion" class="completion-badge">Checking completion</span></div>
      <?php $initial = CandidateInterviews::cifInterviewRecord($candidate, 'initial_interview'); ?><div class="hr-interview-controls"><div class="interview-decision-fields inline-interview-decision"><label for="initial-decision-inline">Interview decision</label><select id="initial-decision-inline" name="interview_decision"><?php foreach (CandidateInterviews::cifInterviewDecisions() as $decision) { ?><option<?= $decision === $initial['decision'] ? ' selected' : '' ?>><?= CandidateView::cifEscape($decision) ?></option><?php } ?></select><small>Finish Interview saves and finishes together.</small></div>
      <?php $requirements = CandidateInterviews::cifAssessmentRequirements($candidate);
        $requirementLocks = CandidateInterviews::cifAssessmentRequirementLocks($candidate); ?>
      <fieldset class="assessment-requirements"><legend>Required assessments</legend><div class="assessment-options">
       <?php foreach (['department_required' => ['department', 'Department interview'], 'ldt_required' => ['ldt', 'Background check']] as $flag => [$suffix, $label]) { ?>
       <label<?= $requirementLocks[$flag] !== null ? ' title="'.CandidateView::cifEscape($requirementLocks[$flag]).'"' : '' ?>><input type="checkbox" id="assessment-<?= $suffix ?>" name="assessment_<?= $suffix ?>"<?= $requirements[$flag] ? ' checked' : '' ?><?= $requirementLocks[$flag] !== null ? ' disabled data-assessment-locked' : '' ?>> <?= $label ?></label>
       <?php } ?></div>
       <small><?php if ($requirementLocks['department_required'] !== null && $requirementLocks['ldt_required'] !== null) { ?>These assessments are locked.<?php } elseif (array_filter($requirementLocks)) { ?>Saved assessments are locked. You can add an unchecked assessment while the job offer is pending.<?php } else { ?>Select both, either, or neither. Any order after the HR interview passes; existing results are retained.<?php } ?></small>
       <?php if ($requirements['revision']) { ?><p class="assessment-audit">Changed by <?= CandidateView::cifEscape($requirements['editor']) ?> · <?= CandidateView::cifEscape(CandidateView::cifLocalDate($requirements['updated_at'])) ?></p><?php } ?>
      </fieldset></div>
      <div class="interview-decision-fields initial-decision-remarks"><label for="initial-decision-remarks-inline">Decision remarks <span class="optional-label">Optional</span></label><textarea id="initial-decision-remarks-inline" name="interview_remarks" rows="2" maxlength="2000"><?= CandidateView::cifEscape($initial['remarks']) ?></textarea></div>
      <div id="hr-missing-notice" class="assessment-notice" role="status" aria-live="polite"></div>
      <?php CandidateMrf::renderCifMrfPicker($candidate); ?>
      <?php foreach (HrFields::cifHrFields() as $section => $fields) {
          $isHealth = $section === 'Health & lifestyle information';
          $isScore = isset(HrFields::cifHrScoreFields()[array_key_first($fields)]);
          $requiredSection = (bool) array_filter(array_keys($fields), [HrFields::class, 'cifHrRequired']);
          $displayFields = $isHealth ? array_replace(array_fill_keys(['last_medical_examination', 'medical_condition', 'maintenance_medication', 'allergies', 'tattoo', 'drug_use_history', 'alcohol_intake', 'gambling', 'smoking', 'transportation', 'drivers_license_code', 'health_others'], ''), $fields) : $fields; ?><details class="hr-group<?= $isHealth ? ' hr-health-group' : ($isScore ? ' hr-score-group' : '') ?>" open data-section-label="<?= CandidateView::cifEscape($section) ?>"><summary><span><?= CandidateView::cifEscape($section) ?> <?php if ($requiredSection) { ?><span class="required-mark" aria-label="required section">*</span><?php } else { ?><span class="optional-label">Optional</span><?php } ?></span><span class="completion-badge section-completion"></span></summary><?php if ($isScore) { ?><p class="hr-score-guide">Optional. Leave blank if no exam score is available. Score out of 100. Ratings: 90–100 Outstanding · 80–89 Very satisfactory · 70–79 Satisfactory · 60–69 Needs improvement · Below 60 Unsatisfactory.</p><?php } ?><div class="editor-grid">
       <?php foreach ($displayFields as $name => $label) {
           $choices = HrFields::cifHrChoices($name);
           $field = ['label' => $label, 'type' => isset(HrFields::cifHrScoreFields()[$name]) ? 'rating' : ($choices ? 'hr-choice' : ($name === 'recertification_date' ? 'date' : (in_array($name, ['hr_notes', 'medical_condition', 'maintenance_medication', 'health_others', 'disciplinary_others'], true) ? 'textarea' : 'text'))), 'max' => 1000, 'options' => $choices];
           CifEditor::renderAdminCifField('hr['.$name.']', $field, $hr[$name] ?? '');
       } ?>
      </div></details><?php } ?>
      <div class="interview-outcome"><span>Saved HR interview decision</span><strong><?= CandidateView::cifEscape($initial['decision']) ?></strong><p><?= CandidateView::cifEscape($initial['remarks']) ?></p><small><?= $initial['completed_at'] ? 'Interview finished · '.CandidateView::cifEscape(CandidateView::cifLocalDate($initial['completed_at'])) : 'Interview not finished yet.' ?></small></div>
      </section></fieldset></div>
      <div id="stage-panel-cif" class="hide" role="tabpanel" aria-labelledby="roadmap-tab-cif" data-record-panel="cif">
      <?php $screening = CandidateInterviews::cifInterviewRecord($candidate, 'cif'); ?><section class="editor-card screening-overview"><h2>CIF screening</h2><p>Review the candidate information, then complete screening as Passed, Failed or Backout. Missing answers are a warning only at this stage; verify them with the candidate during HR interview. Only Passed opens HR interview.</p><?php if ($screening['completed_at']) { ?><p class="interview-completion-note">Screening: <strong><?= CandidateView::cifEscape($screening['decision']) ?></strong> · <?= CandidateView::cifEscape(CandidateView::cifLocalDate($screening['completed_at'])) ?><?php if ($screening['editor']) { ?> · <?= CandidateView::cifEscape($screening['editor']) ?><?php } ?></p><?php } ?></section>
      <section class="editor-card screening-mrf-card" aria-label="Screening MRF identification"><?php CandidateMrf::renderCifMrfPicker($candidate, 'screening-mrf', false, 'cif'); ?></section>
      <details id="missing-information" class="missing-information"><summary>Missing candidate information <span id="candidate-missing-count" class="completion-badge" aria-live="polite"></span></summary><p>Click a field to jump to it. Optional blanks are for review, not completion errors; unused optional records are excluded.</p><div id="candidate-missing-links"></div></details>
      <?php foreach ($definition['sections'] as $index => $fields) {
          $number = array_search($index, array_keys($definition['sections']), true); ?>
      <section id="section-<?= $number ?>" class="editor-card candidate-card<?= $index === 'Certification & consent' ? ' consent-card' : '' ?>"><div class="card-title"><span class="section-number"><?= $number + 1 ?></span><div><h2><?= CandidateView::cifEscape($index) ?></h2><p><?= $index === 'Certification & consent' ? 'Original candidate acknowledgements are read-only.' : 'Review and correct candidate details where necessary.' ?></p></div></div><div class="editor-grid">
       <?php foreach ($fields as $name => $field) {
           CifEditor::renderAdminCifField($name, $field, $f[$name] ?? '', in_array($name, ['certification_name', 'background_check_name'], true));
       } ?>
      </div>
      <?php foreach ($definition['groups'] as $group => $record) {
          if ($record['section'] !== $index) {
              continue;
          } ?><div class="editor-record-group"><h3><?= CandidateView::cifEscape($record['label']) ?></h3>
       <?php for ($row = 0; $row < $record['max']; $row++) { ?><details class="editor-record" data-record-anchor="<?= CandidateView::cifEscape($group.'['.$row.']['.$record['anchor'].']') ?>" <?= ! empty($f[$group][$row]) ? 'open' : '' ?>><summary>Record <?= $row + 1 ?> <span><?= CandidateView::cifEscape($f[$group][$row][$record['anchor']] ?? 'No details yet') ?></span></summary><div class="editor-grid"><?php foreach ($record['fields'] as $name => $field) {
           CifEditor::renderAdminCifField($group.'['.$row.']['.$name.']', $field, $f[$group][$row][$name] ?? '');
       } ?></div></details><?php } ?></div><?php } ?>
      <?php if ($index === 'Certification & consent') { ?><div class="ack-grid"><div><span>Certification</span><b><?= ! empty($f['consent']) ? 'Accepted' : 'Not recorded' ?></b></div><div><span>Background check consent</span><b><?= ! empty($f['background_consent']) ? 'Accepted' : 'Not recorded' ?></b></div><div><span>Acceptance time</span><b><?= CandidateView::cifEscape(CandidateView::cifLocalDate($f['accepted_at'] ?? null)) ?></b></div></div><p class="editor-help">HR edits are saved as revisions. The original submission, typed acknowledgements and consent timestamps are preserved.</p><?php } ?>
      </section><?php } ?>
      </div></fieldset><div class="save-bar"><span id="edit-state"><?= $readOnly ? 'View-only after deployment' : 'All changes saved' ?></span><div class="save-actions"><button id="publish-current-stage" type="button" class="draft-button">Update candidate</button><button id="save-cif"<?= $readOnly ? ' disabled data-deployment-locked hidden' : '' ?> type="submit" class="draft-button">Save changes</button><button id="complete-screening"<?= $readOnly ? ' disabled data-deployment-locked hidden' : '' ?> type="button" class="primary-button">Complete Screening</button><button id="complete-interview"<?= $readOnly ? ' disabled data-deployment-locked hidden' : '' ?> type="button" class="primary-button">Finish Interview</button></div></div>
     </form><?php foreach (['department_interview', 'lie_detection', 'job_offer', 'deployment'] as $stage) {
         CandidateInterviews::renderCifInterviewPanel($candidate, $stage);
     } ?></div></div>
     <dialog id="completion-dialog" class="completion-dialog" aria-labelledby="completion-title"><h2 id="completion-title">Finish Interview</h2><p id="completion-description"></p><ul id="completion-missing"></ul><div class="interview-decision-fields"><label for="initial-decision">Decision</label><select id="initial-decision"><?php foreach (CandidateInterviews::cifInterviewDecisions() as $decision) { ?><option<?= $decision === $initial['decision'] ? ' selected' : '' ?>><?= CandidateView::cifEscape($decision) ?></option><?php } ?></select><label for="initial-decision-remarks">Remarks <span class="optional-label">Optional</span></label><textarea id="initial-decision-remarks" rows="3" maxlength="2000"><?= CandidateView::cifEscape($initial['remarks']) ?></textarea><small>Pending Decision is allowed. The next stage opens for recording only after a Passed decision.</small></div><div class="completion-dialog-actions"><button id="completion-back" type="button" class="draft-button">Back to editing</button><button id="completion-confirm" type="button" class="primary-button">Finish Interview</button></div></dialog>
     <dialog id="stage-completion-dialog" class="completion-dialog" aria-labelledby="stage-completion-title"><h2 id="stage-completion-title">Finish Interview</h2><p id="stage-completion-description"></p><p><span id="stage-completion-result-label">Decision:</span> <strong id="stage-completion-decision"></strong></p><p id="stage-completion-help">Pending Decision is allowed. Only a finished interview with a Passed decision advances the roadmap.</p><div class="completion-dialog-actions"><button type="button" id="stage-completion-back" class="draft-button">Back to editing</button><button type="button" id="stage-completion-confirm" class="primary-button">Finish Interview</button></div></dialog>
     <dialog id="deployment-completion-dialog" class="completion-dialog" aria-labelledby="deployment-completion-title"><h2 id="deployment-completion-title">Complete deployment</h2><p id="deployment-completion-description"></p><div class="completion-dialog-actions"><button type="button" id="deployment-completion-back" class="draft-button">Back to editing</button><button type="button" id="deployment-completion-confirm" class="primary-button">Complete deployment</button></div></dialog>
     <dialog id="screening-completion-dialog" class="completion-dialog" aria-labelledby="screening-completion-title"><h2 id="screening-completion-title">Complete Screening</h2><p>Your current CIF edits will be saved with this decision. Failed or Backout does not allow the candidate to proceed to HR interview.</p><div class="interview-decision-fields"><label for="screening-decision">Screening decision</label><select id="screening-decision" required><option value="">Choose a decision</option><option>Passed</option><option>Failed</option><option>Backout</option></select></div><p id="screening-completion-warning"></p><div class="completion-dialog-actions"><button type="button" id="screening-completion-back" class="draft-button">Back to editing</button><button type="button" id="screening-completion-confirm" class="primary-button">Complete Screening</button></div></dialog>
    </div>
    <?php
    }
}
