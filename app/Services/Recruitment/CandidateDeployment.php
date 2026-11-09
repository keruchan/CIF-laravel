<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use DateTimeImmutable;

final class CandidateDeployment
{
    public static function cifCandidateIsDeployed(array $candidate): bool
    {
        // Completion is permanent even if an older record has inconsistent earlier stages.
        return ! empty($candidate['deployment']['completed_at']) || ($candidate['status'] ?? '') === 'Deployed';
    }

    public static function cifDeploymentRecord(array $candidate): array
    {
        return $candidate['deployment'] ?? ['request_id' => $candidate['mrf']['id'] ?? null, 'mrf_number' => $candidate['mrf']['mrf_number'] ?? '', 'date_hired' => '', 'employee_id' => '', 'position_title' => '', 'completed_at' => null, 'revision' => 0, 'updated_at' => null, 'editor' => null];
    }

    public static function validateCifDeployment(\PDO $pdo, array $input, array $candidate, bool $complete): array
    {
        $previous = CandidateDeployment::cifDeploymentRecord($candidate);
        $currentMrf = $previous['request_id'] ? (int) $previous['request_id'] : null;
        if (! array_key_exists('source_mrf_id', $input) || $input['source_mrf_id'] !== $currentMrf) {
            throw new \InvalidArgumentException('The prefilled MRF changed. Refresh this candidate before saving deployment.');
        }
        $requestId = CandidateMrf::validateCandidateMrf($pdo, $input['mrf_id'] ?? null, $currentMrf, $input['mrf_text'] ?? null);
        if ($requestId !== $currentMrf && ($input['confirm_mrf_change'] ?? false) !== true) {
            throw new \InvalidArgumentException('Confirm the change to the deployment MRF before saving. The screening/interview MRF will remain unchanged.');
        }
        $values = ['request_id' => $requestId];
        foreach (['date_hired' => 10, 'employee_id' => 50, 'position_title' => 150] as $key => $limit) {
            $value = $input[$key] ?? '';
            if (! is_string($value) || mb_strlen($value) > $limit || preg_match('/[\x00-\x1F\x7F]/u', $value)) {
                throw new \InvalidArgumentException('Use valid deployment details within the allowed field lengths.');
            }
            $values[$key] = trim($value);
        }
        if ($values['date_hired'] !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $values['date_hired']);
            if (! $date || $date->format('Y-m-d') !== $values['date_hired'] || (int) $date->format('Y') < 1900) {
                throw new \InvalidArgumentException('Enter a valid date hired.');
            }
        }
        if ($values['position_title'] !== '' && ! in_array($values['position_title'], HiringPositions::cifHiringPositions(), true)) {
            throw new \InvalidArgumentException('Choose an official deployment position title from the search results.');
        }
        if ($complete || ! empty($previous['completed_at'])) {
            $missing = [];
            foreach (['date_hired' => 'Date hired', 'position_title' => 'Position title'] as $key => $label) {
                if ($values[$key] === '') {
                    $missing[] = $label;
                }
            }
            if ($missing) {
                throw new \InvalidArgumentException('Complete deployment requires: '.implode(', ', $missing).'. Use Save changes to keep unfinished work.');
            }
        }
        if ($values['employee_id'] !== '') {
            $query = $pdo->prepare('SELECT candidate_id FROM candidate_deployments WHERE employee_id = ? AND candidate_id <> ?');
            $query->execute([$values['employee_id'], $candidate['id']]);
            if ($query->fetchColumn()) {
                throw new \InvalidArgumentException('This Employee ID is already assigned to another applicant.');
            }
        }

        return $values;
    }

    public static function saveCifDeployment(\PDO $pdo, int $id, array $values, array $previous, int $adminId, bool $complete): void
    {
        $finished = $complete || ! empty($previous['completed_at']);
        $query = $pdo->prepare('INSERT INTO candidate_deployments (candidate_id, request_id, date_hired, employee_id, position_title, completed_at, revision, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, IF(?, UTC_TIMESTAMP(), NULL), ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE request_id = VALUES(request_id), date_hired = VALUES(date_hired), employee_id = VALUES(employee_id), position_title = VALUES(position_title), completed_at = COALESCE(completed_at, VALUES(completed_at)), revision = VALUES(revision), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)');
        $query->execute([$id, $values['request_id'], $values['date_hired'] === '' ? null : $values['date_hired'], $values['employee_id'] === '' ? null : $values['employee_id'], $values['position_title'] === '' ? null : $values['position_title'], (int) $finished, $previous['revision'] + 1, $adminId]);
        $query = $pdo->prepare('INSERT INTO candidate_deployment_history (candidate_id, request_id, date_hired, employee_id, position_title, completed_at, revision, admin_id, created_at) SELECT candidate_id, request_id, date_hired, employee_id, position_title, completed_at, revision, updated_by, updated_at FROM candidate_deployments WHERE candidate_id = ?');
        $query->execute([$id]);
    }

    public static function renderCifDeploymentPanel(array $candidate): void
    {
        $readOnly = CandidateDeployment::cifCandidateIsDeployed($candidate);
        $record = CandidateDeployment::cifDeploymentRecord($candidate);
        $blocked = CandidateInterviews::cifInterviewPrerequisite($candidate, 'deployment');
        ?>
    <section id="stage-panel-deployment" class="hide editor-card interview-stage-card" role="tabpanel" aria-labelledby="roadmap-tab-deployment" data-record-panel="deployment">
     <div class="card-title"><span class="section-number"><?php
        echo PortalIcons::portalIcon('deployment');
        ?></span><div><h2>Deployment</h2><p>Record the final MRF, hiring date, employee ID and position title.</p></div></div>
     <?php
        if ($blocked) {
            ?><p class="stage-prerequisite" role="status"><?php
            echo CandidateView::cifEscape($blocked);
            ?></p><?php
        }
        ?>
     <form data-deployment-form novalidate data-revision="<?php
        echo $record['revision'];
        ?>" data-requirements-revision="<?php
        echo CandidateInterviews::cifAssessmentRequirements($candidate)['revision'];
        ?>" data-source-mrf="<?php
        echo (int) ($record['request_id'] ?? 0);
        ?>"><fieldset<?php
        echo $blocked || $readOnly ? ' disabled' : '';
        ?>><legend class="roadmap-sr-only">Deployment details</legend>
      <div class="candidate-mrf-field"><div class="candidate-mrf-label"><label for="deployment-mrf-search">Deployment MRF <span class="required-mark">*</span></label><button type="button" id="deployment-mrf-change" class="draft-button">Change MRF</button></div>
       <div class="candidate-mrf-picker"><input id="deployment-mrf-search" type="search" maxlength="150" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="deployment-mrf-options" aria-describedby="deployment-mrf-help" placeholder="Search registered MRFs" value="<?php
        echo CandidateView::cifEscape($record['mrf_number']);
        ?>" readonly required><input id="deployment-mrf-id" type="hidden" value="<?php
        echo (int) ($record['request_id'] ?? 0) ?: '';
        ?>"><ul id="deployment-mrf-options" role="listbox" aria-label="Available deployment MRFs" hidden></ul></div>
       <p id="deployment-mrf-help" class="editor-help">Prefilled from screening or Interview. Only HR-verified Approved MRFs can be used. Changing it affects deployment only, not the screening/interview assignment.</p>
      </div>
      <div class="editor-grid deployment-grid">
       <div class="editor-field"><label for="deployment-date">Date hired</label><input id="deployment-date" name="date_hired" type="date" min="1900-01-01" max="9999-12-31" value="<?php
        echo CandidateView::cifEscape($record['date_hired']);
        ?>"></div>
       <div class="editor-field"><label for="deployment-employee-id">Employee ID (EMPID) <small>Optional</small></label><input id="deployment-employee-id" name="employee_id" type="text" maxlength="50" value="<?php
        echo CandidateView::cifEscape($record['employee_id']);
        ?>" aria-describedby="deployment-employee-help"><p id="deployment-employee-help" class="editor-help">Optional. Add the Employee ID before completing deployment; completed records are view-only.</p></div>
       <div class="editor-field deployment-position"><label for="deployment-position-search">Official position title</label><div class="candidate-mrf-picker"><input id="deployment-position-search" name="position_title" type="search" maxlength="150" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="deployment-position-options" aria-describedby="deployment-position-help" placeholder="Search official deployment titles" value="<?php
        echo CandidateView::cifEscape($record['position_title']);
        ?>"><ul id="deployment-position-options" role="listbox" aria-label="Official deployment position titles" hidden></ul></div><p id="deployment-position-help" class="editor-help">Choose an official deployment title from the position inventory. Applied positions use a separate list.</p></div>
      </div>
      <p class="interview-completion-note"><?php
        echo $record['completed_at'] ? 'Deployment completed · '.CandidateView::cifEscape(CandidateView::cifLocalDate($record['completed_at'])) : 'Save incomplete details anytime. Date hired and a registered position title are required to complete deployment. Employee ID is optional. Completing deployment makes the CIF and all hiring assessments view-only.';
        ?></p>
      <?php
        if ($record['updated_at']) {
            ?><p class="interview-completion-note">Last saved by <?php
            echo CandidateView::cifEscape($record['editor']);
            ?> · <?php
            echo CandidateView::cifEscape(CandidateView::cifLocalDate($record['updated_at']));
            ?></p><?php
        }
        ?>
      </fieldset><div class="save-bar stage-save-bar"><span data-stage-save-state><?php
        echo $readOnly ? 'View-only after deployment' : ($record['revision'] ? 'All changes saved' : 'No changes saved yet');
        ?></span><div class="save-actions"><button type="button" class="draft-button" data-stage-publish="deployment">Update candidate</button><button type="submit" class="draft-button" data-stage-save<?php
        echo $readOnly ? ' disabled data-deployment-locked hidden' : '';
        echo $blocked ? ' disabled data-prerequisite-blocked' : '';
        ?>>Save changes</button><button type="button" class="primary-button" data-stage-finish<?php
        echo $readOnly ? ' disabled data-deployment-locked hidden' : '';
        echo $blocked ? ' disabled data-prerequisite-blocked' : '';
        ?>>Complete deployment</button></div></div>
     </form>
     <script type="application/json" id="deployment-positions"><?php
        echo json_encode(HiringPositions::cifHiringPositions(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        ?></script>
    </section>
    <?php
    }
}
