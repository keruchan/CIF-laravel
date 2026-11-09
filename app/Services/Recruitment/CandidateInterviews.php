<?php
declare(strict_types=1);

namespace App\Services\Recruitment;

use InvalidArgumentException;
use PDO;

final class CandidateInterviews
{
    public static function cifInterviewDecisions(string $stage = 'initial_interview'): array
    {
        if ($stage === 'cif') {
            return ['Passed', 'Failed', 'Backout'];
        }
        if ($stage === 'job_offer') {
            return ['Accepted', 'Declined', 'Backout', 'Pending Decision', 'Pooling'];
        }

        return ['Pending Decision', 'Passed', 'Failed', 'Backout', 'Pooling', 'No Response'];
    }

    public static function cifStageSuccessfulDecision(string $stage): string
    {
        return $stage === 'job_offer' ? 'Accepted' : 'Passed';
    }

    public static function cifInterviewStages(): array
    {
        // Keep persisted stage keys stable: this is a label change, not a new stage.
        return ['cif' => 'CIF screening', 'initial_interview' => 'HR interview', 'department_interview' => 'Department interview', 'lie_detection' => 'Background check', 'job_offer' => 'Job offer'];
    }

    public static function cifInterviewRecord(array $candidate, string $stage): array
    {
        $record = $candidate['interviews'][$stage] ?? ['decision' => 'Pending Decision', 'remarks' => '', 'completed_at' => $stage === 'initial_interview' && ($candidate['workflow_legacy_status'] ?? $candidate['status'] ?? '') === 'Interview Completed' ? ($candidate['edited_at'] ?? $candidate['submitted_at'] ?? null) : null, 'revision' => 0, 'editor' => null, 'updated_at' => null];
        // Interpret previous labels without overwriting the original audit history.
        if ($stage === 'job_offer') {
            $record['decision'] = match ($record['decision']) {
                'Passed' => 'Accepted', 'Failed' => 'Declined', 'No Response' => 'Pending Decision', default => $record['decision']
            };
        }
        if ($record['decision'] === 'For Schedule!') {
            $record['decision'] = 'Pending Decision';
        }
        if ($stage === 'exam' && $record['decision'] === 'Pending Decision') {
            $record['decision'] = 'Pending Result';
        }

        return $record;
    }

    public static function validateCifInterview(array $input, string $stage = 'initial_interview'): array
    {
        if (! isset(CandidateInterviews::cifInterviewStages()[$stage])) {
            throw new InvalidArgumentException('Choose an available hiring stage.');
        }
        $decision = $input['decision'] ?? 'Pending Decision';
        $remarks = $input['remarks'] ?? '';
        if (! is_string($decision) || ! in_array($decision, CandidateInterviews::cifInterviewDecisions($stage), true)) {
            throw new InvalidArgumentException($stage === 'cif' ? 'Choose Passed, Failed or Backout to complete CIF screening.' : ($stage === 'job_offer' ? 'Choose a valid job offer response.' : 'Choose a valid interview decision.'));
        }
        if (! is_string($remarks)) {
            throw new InvalidArgumentException('Remarks must be 2000 characters or fewer.');
        }
        $remarks = TextFormat::cifProperText(trim($remarks));
        if (mb_strlen($remarks) > 2000) {
            throw new InvalidArgumentException('Remarks must be 2000 characters or fewer.');
        }

        return ['decision' => $decision, 'remarks' => $remarks];
    }

    public static function cifInterviewPrerequisite(array $candidate, string $stage): ?string
    {
        if ($stage !== 'deployment' && ! isset(CandidateInterviews::cifInterviewStages()[$stage])) {
            return 'This hiring stage is no longer available.';
        }
        if ($stage !== 'cif') {
            $screening = CandidateInterviews::cifInterviewRecord($candidate, 'cif');
            if (empty($screening['completed_at']) || $screening['decision'] !== 'Passed') {
                return 'Complete CIF screening with a Passed decision before proceeding to interviews.';
            }
        }
        if (in_array($stage, ['department_interview', 'lie_detection'], true) && ! CandidateInterviews::cifAssessmentRequired($candidate, $stage)) {
            return 'Not required for this candidate. Existing decisions and remarks are retained.';
        }
        $previousStages = in_array($stage, ['department_interview', 'lie_detection'], true) ? ['initial_interview'] : [];
        if (in_array($stage, ['job_offer', 'deployment'], true)) {
            $previousStages = ['initial_interview'];
            foreach (['department_interview', 'lie_detection'] as $assessment) {
                if (CandidateInterviews::cifAssessmentRequired($candidate, $assessment)) {
                    $previousStages[] = $assessment;
                }
            }
            if ($stage === 'deployment') {
                $previousStages[] = 'job_offer';
            }
        }
        foreach ($previousStages as $previous) {
            $record = CandidateInterviews::cifInterviewRecord($candidate, $previous);
            $success = CandidateInterviews::cifStageSuccessfulDecision($previous);
            if (empty($record['completed_at']) || $record['decision'] !== $success) {
                return 'Finish the '.strtolower(CandidateInterviews::cifInterviewStages()[$previous]).' with the '.'decision'.' set to '.$success.' before recording this stage.';
            }
        }

        return null;
    }

    public static function cifAssessmentRequirements(array $candidate): array
    {
        return $candidate['assessment_requirements'] ?? ['department_required' => true, 'ldt_required' => true, 'reason' => '', 'revision' => 0];
    }

    public static function cifAssessmentRequired(array $candidate, string $stage): bool
    {
        $requirements = CandidateInterviews::cifAssessmentRequirements($candidate);

        return (bool) ($requirements[$stage === 'department_interview' ? 'department_required' : 'ldt_required']);
    }

    public static function cifAssessmentRequirementLocks(array $candidate): array
    {
        $locks = ['department_required' => null, 'ldt_required' => null];
        $offer = CandidateInterviews::cifInterviewRecord($candidate, 'job_offer');
        $deploymentStarted = in_array($candidate['status'] ?? '', ['Deployment', 'Deployed'], true)
            || ! empty($candidate['deployment']['revision']) || ! empty($candidate['deployment']['completed_at']);
        if ($offer['decision'] !== 'Pending Decision' || $deploymentStarted) {
            return array_fill_keys(array_keys($locks), 'Required assessments are locked once a job offer response has been recorded.');
        }
        $atOffer = ($candidate['status'] ?? '') === 'Job Offer' || ! empty($offer['revision'])
            || ! empty($offer['updated_at']) || ! empty($offer['completed_at']);
        if (! $atOffer) {
            return $locks;
        }
        $requirements = CandidateInterviews::cifAssessmentRequirements($candidate);
        foreach (['department_required' => 'department_interview', 'ldt_required' => 'lie_detection'] as $flag => $stage) {
            $record = CandidateInterviews::cifInterviewRecord($candidate, $stage);
            $hasData = ! empty($record['revision']) || ! empty($record['updated_at']) || ! empty($record['completed_at'])
                || trim($record['remarks'] ?? '') !== '' || $record['decision'] !== 'Pending Decision';
            if ($requirements[$flag] && $hasData) {
                $locks[$flag] = CandidateInterviews::cifInterviewStages()[$stage].' has saved information and cannot be unchecked at Job Offer.';
            }
        }

        return $locks;
    }

    public static function validateCifAssessmentRequirements(mixed $input, array $candidate): array
    {
        $current = CandidateInterviews::cifAssessmentRequirements($candidate);
        if ($input === null) {
            return $current;
        }
        if (! is_array($input) || ! is_bool($input['department_required'] ?? null) || ! is_bool($input['ldt_required'] ?? null)) {
            throw new InvalidArgumentException('Choose valid required assessments.');
        }
        foreach (CandidateInterviews::cifAssessmentRequirementLocks($candidate) as $flag => $reason) {
            if ($reason !== null && $input[$flag] !== $current[$flag]) {
                throw new InvalidArgumentException($reason);
            }
        }
        $changed = $input['department_required'] !== $current['department_required'] || $input['ldt_required'] !== $current['ldt_required'];

        return $changed ? array_replace($current, ['department_required' => $input['department_required'], 'ldt_required' => $input['ldt_required'], 'reason' => '']) : $current;
    }

    public static function saveCifAssessmentRequirements(PDO $pdo, int $id, array $candidate, array $values, int $adminId): void
    {
        $current = CandidateInterviews::cifAssessmentRequirements($candidate);
        if ($current['department_required'] === $values['department_required'] && $current['ldt_required'] === $values['ldt_required']) {
            return;
        }
        $query = $pdo->prepare('INSERT INTO candidate_assessment_requirements (candidate_id, department_required, ldt_required, reason, revision, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE department_required = VALUES(department_required), ldt_required = VALUES(ldt_required), reason = VALUES(reason), revision = VALUES(revision), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)');
        $query->execute([$id, (int) $values['department_required'], (int) $values['ldt_required'], $values['reason'], $current['revision'] + 1, $adminId]);
        $query = $pdo->prepare('INSERT INTO candidate_assessment_requirement_history (candidate_id, department_required, ldt_required, reason, revision, admin_id, created_at) SELECT candidate_id, department_required, ldt_required, reason, revision, updated_by, updated_at FROM candidate_assessment_requirements WHERE candidate_id = ?');
        $query->execute([$id]);
    }

    public static function saveCifInterview(PDO $pdo, int $candidateId, string $stage, array $values, int $revision, int $adminId, bool $finished): void
    {
        if (! isset(CandidateInterviews::cifInterviewStages()[$stage])) {
            throw new InvalidArgumentException('Invalid interview stage.');
        }
        $values = CandidateInterviews::validateCifInterview($values, $stage);
        $query = $pdo->prepare('INSERT INTO candidate_interview_stages (candidate_id, stage, decision, remarks, completed_at, revision, updated_by, updated_at) VALUES (?, ?, ?, ?, IF(?, UTC_TIMESTAMP(), NULL), ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE decision = VALUES(decision), remarks = VALUES(remarks), completed_at = IF(VALUES(completed_at) IS NULL, NULL, COALESCE(completed_at, VALUES(completed_at))), revision = VALUES(revision), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)');
        $query->execute([$candidateId, $stage, $values['decision'], $values['remarks'], $finished ? 1 : 0, $revision + 1, $adminId]);
        $query = $pdo->prepare('INSERT INTO candidate_interview_stage_history (candidate_id, stage, decision, remarks, completed_at, revision, admin_id, created_at) SELECT candidate_id, stage, decision, remarks, completed_at, revision, updated_by, updated_at FROM candidate_interview_stages WHERE candidate_id = ? AND stage = ?');
        $query->execute([$candidateId, $stage]);
    }

    public static function renderCifInterviewPanel(array $candidate, string $stage): void
    {
        if ($stage === 'deployment') {
            CandidateDeployment::renderCifDeploymentPanel($candidate);

            return;
        }
        $label = CandidateInterviews::cifInterviewStages()[$stage] ?? ($stage === 'job_offer' ? 'Job offer' : 'Deployment');
        $available = isset(CandidateInterviews::cifInterviewStages()[$stage]);
        $readOnly = CandidateDeployment::cifCandidateIsDeployed($candidate);
        $offer = $stage === 'job_offer';
        ?>
    <section id="stage-panel-<?= $stage ?>" class="hide editor-card interview-stage-card" role="tabpanel" aria-labelledby="roadmap-tab-<?= $stage ?>" data-record-panel="<?= $stage ?>">
     <div class="card-title"><span class="section-number"><?= PortalIcons::portalIcon($stage === 'department_interview' ? 'candidates' : ($stage === 'lie_detection' ? 'screening' : 'job-offer')) ?></span><div><h2><?= CandidateView::cifEscape($label) ?></h2><p><?= $offer ? 'Record the applicant’s response. Finish with Accepted to proceed to deployment.' : 'Record the interview decision and remarks.' ?></p></div></div>
     <?php if ($available) {
         $record = CandidateInterviews::cifInterviewRecord($candidate, $stage);
         $blocked = CandidateInterviews::cifInterviewPrerequisite($candidate, $stage); ?>
      <?php if ($blocked) { ?><p class="stage-prerequisite" role="status"><?= CandidateView::cifEscape($blocked) ?></p><?php } ?>
      <form data-interview-form="<?= $stage ?>" data-revision="<?= $record['revision'] ?>" data-requirements-revision="<?= CandidateInterviews::cifAssessmentRequirements($candidate)['revision'] ?>"><fieldset<?= $blocked || $readOnly ? ' disabled' : '' ?>><legend class="roadmap-sr-only"><?= CandidateView::cifEscape($label) ?> outcome</legend>
       <div class="interview-decision-fields"><label for="<?= $stage ?>-decision"><?= $offer ? 'Offer response' : 'Decision' ?></label><select id="<?= $stage ?>-decision" name="decision" required><?php foreach (CandidateInterviews::cifInterviewDecisions($stage) as $decision) { ?><option<?= $record['decision'] === $decision ? ' selected' : '' ?>><?= CandidateView::cifEscape($decision) ?></option><?php } ?></select>
       <label for="<?= $stage ?>-remarks">Remarks <span class="optional-label">Optional</span></label><textarea id="<?= $stage ?>-remarks" name="remarks" rows="5" maxlength="2000"><?= CandidateView::cifEscape($record['remarks']) ?></textarea></div>
       <p class="interview-completion-note"><?= $record['completed_at'] ? ($offer ? 'Job offer finished · ' : 'Interview finished · ').CandidateView::cifEscape(CandidateView::cifLocalDate($record['completed_at'])) : ($offer ? 'Job offer not finished yet. Finish Job Offer saves the current response and finishes together.' : 'Interview not finished yet. Save changes keeps your work; Finish Interview saves and finishes together.') ?></p>
       <?php if ($record['updated_at']) { ?><p class="interview-completion-note">Last saved by <?= CandidateView::cifEscape($record['editor']) ?> · <?= CandidateView::cifEscape(CandidateView::cifLocalDate($record['updated_at'])) ?></p><?php } ?>
       </fieldset><div class="save-bar stage-save-bar"><span data-stage-save-state><?= $readOnly ? 'View-only after deployment' : ($record['revision'] ? 'All changes saved' : 'No changes saved yet') ?></span><div class="save-actions"><button type="button" class="draft-button" data-stage-publish="<?= $stage ?>">Update candidate</button><button type="submit" class="draft-button" data-stage-save<?= $readOnly ? ' disabled data-deployment-locked hidden' : '' ?><?= $blocked ? ' disabled data-prerequisite-blocked' : '' ?>>Save changes</button><button type="button" class="primary-button" data-stage-finish<?= $readOnly ? ' disabled data-deployment-locked hidden' : '' ?><?= $blocked ? ' disabled data-prerequisite-blocked' : '' ?>><?= $offer ? 'Finish Job Offer' : 'Finish Interview' ?></button></div></div>
      </form>
     <?php } else { ?><p class="stage-prerequisite">You can navigate here, but this stage cannot be completed until its module is available.</p><?php } ?>
    </section>
    <?php
    }
}
