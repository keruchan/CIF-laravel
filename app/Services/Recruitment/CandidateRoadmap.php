<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class CandidateRoadmap
{
    public static function cifHiringRoadmap(array $candidate): array
    {
        $labels = ['cif' => 'CIF screening', 'initial_interview' => 'HR interview', 'department_interview' => 'Department interview', 'lie_detection' => 'Background check', 'job_offer' => 'Job offer', 'deployment' => 'Deployment'];
        $received = ! empty($candidate['submitted_at']);
        $screening = CandidateInterviews::cifInterviewRecord($candidate, 'cif');
        $screened = $received && ! empty($screening['completed_at']) && $screening['decision'] === 'Passed';
        $steps = [];
        $advance = $screened;
        $completed = $screened ? 1 : 0;
        $active = $screened ? 1 : 0;
        $required = 0;
        $passed = 0;
        $waiting = [];
        $initialPassed = false;
        foreach (['department_interview', 'lie_detection'] as $stage) {
            if (CandidateInterviews::cifAssessmentRequired($candidate, $stage)) {
                $required++;
            }
        }
        $summary = ! $received ? 'CIF · Awaiting submission' : ($screened ? 'HR interview · Awaiting HR review' : 'CIF screening · '.(in_array($screening['decision'], ['Failed', 'Backout'], true) && $screening['completed_at'] ? $screening['decision'] : 'Awaiting review'));
        foreach ($labels as $key => $label) {
            $index = count($steps);
            $state = 'pending';
            $status = 'Not started';
            if ($index === 0) {
                $state = $screened ? 'completed' : (! empty($screening['completed_at']) && in_array($screening['decision'], ['Failed', 'Backout'], true) ? 'held' : 'current');
                $status = $screened ? 'Passed' : ($received ? $state === 'held' ? $screening['decision'] : 'Awaiting screening' : 'Awaiting submission');
            } elseif (in_array($key, ['department_interview', 'lie_detection'], true) && ! CandidateInterviews::cifAssessmentRequired($candidate, $key)) {
                $state = 'not-required';
                $status = 'Not required';
            } elseif ((in_array($key, ['department_interview', 'lie_detection'], true) ? $initialPassed : $advance) && isset(CandidateInterviews::cifInterviewStages()[$key])) {
                $record = CandidateInterviews::cifInterviewRecord($candidate, $key);
                if (! empty($record['completed_at']) && $record['decision'] === CandidateInterviews::cifStageSuccessfulDecision($key)) {
                    $state = 'completed';
                    $status = $record['decision'];
                    $completed++;
                    $active = $index + 1;
                    if ($key === 'initial_interview') {
                        $initialPassed = true;
                    } elseif ($key === 'job_offer') {
                        $advance = true;
                    } else {
                        $passed++;
                    }
                } else {
                    $advance = false;
                    $active = $index;
                    $state = in_array($record['decision'], ['Failed', 'Declined', 'Backout', 'Pooling', 'No Response'], true) ? 'held' : ($record['revision'] === 0 && $index > 1 ? 'next' : 'current');
                    $status = $record['decision'];
                    if ($index === 1 && empty($record['completed_at']) && $record['decision'] === 'Pending Decision') {
                        $status = $record['revision'] ? 'In progress' : 'Awaiting HR review';
                    }
                    if (empty($record['completed_at']) && $record['decision'] === CandidateInterviews::cifStageSuccessfulDecision($key)) {
                        $status = $key === 'job_offer' ? 'Accepted · Offer not finished' : 'Passed · Interview not finished';
                    }
                    $summary = $state === 'next' ? 'Next: '.$label : $label.' · '.$status;
                    if (in_array($key, ['department_interview', 'lie_detection'], true)) {
                        $waiting[] = $label.' ('.$status.')';
                    }
                }
            } elseif ($key === 'deployment' && $advance) {
                $deployment = CandidateDeployment::cifDeploymentRecord($candidate);
                $active = $index;
                if (! empty($deployment['completed_at'])) {
                    $state = 'completed';
                    $status = 'Completed';
                    $completed++;
                    $summary = 'Hiring process completed · Deployed';
                } else {
                    $state = $deployment['revision'] ? 'current' : 'next';
                    $status = $deployment['revision'] ? 'In progress' : 'Next step';
                    $summary = $deployment['revision'] ? 'Deployment · In progress' : 'Next: Deployment';
                }
                $advance = false;
            } elseif ($advance) {
                $state = 'next';
                $status = 'Next step';
                $advance = false;
                $active = $index;
                $summary = 'Next: '.$label;
            }
            $steps[] = compact('key', 'label', 'state', 'status');
            if ($key === 'lie_detection') {
                $advance = $initialPassed && $passed === $required;
                if ($initialPassed && ! $advance) {
                    $summary = 'Required assessments: '.$passed.' of '.$required.' passed · Awaiting '.implode(' · ', $waiting);
                }
            }
        }
        // Connectors describe prerequisites, not the selected tab or a mandatory
        // Department -> Background check order. Both links open after Initial interview passes.
        $states = array_column($steps, 'state', 'key');
        $connections = ['cif' => false, 'initial_interview' => $screened, 'department_interview' => $initialPassed, 'lie_detection' => $initialPassed, 'job_offer' => $initialPassed && $passed === $required, 'deployment' => $states['job_offer'] === 'completed'];
        foreach ($steps as &$step) {
            $step['connector_active'] = $connections[$step['key']];
        }
        unset($step);

        return ['steps' => $steps, 'completed' => $completed, 'total' => 4 + $required, 'summary' => $summary, 'active' => $active];
    }

    public static function renderCifHiringRoadmap(array $candidate): void
    {
        $roadmap = CandidateRoadmap::cifHiringRoadmap($candidate);
        $icons = ['cif' => 'cif', 'initial_interview' => 'interview', 'department_interview' => 'candidates', 'lie_detection' => 'screening', 'job_offer' => 'job-offer', 'deployment' => 'deployment'];
        ?>
    <section class="hiring-roadmap" aria-labelledby="roadmap-title" aria-describedby="roadmap-help">
     <div class="roadmap-heading"><h2 id="roadmap-title">Hiring progress</h2><span id="roadmap-count"><?php
        echo $roadmap['completed'];
        ?> / <?php
        echo $roadmap['total'];
        ?> completed</span></div>
     <p class="roadmap-parallel-label">Department interview &amp; Background check · Any order</p>
     <ol role="tablist" aria-label="Hiring process stages">
      <?php
        foreach ($roadmap['steps'] as $step) {
            ?>
       <li role="presentation" class="roadmap-step roadmap-<?php
            echo $step['state'];
            echo $step['connector_active'] ? ' roadmap-connector-active' : '';
            ?>" data-stage="<?php
            echo $step['key'];
            ?>" data-state="<?php
            echo $step['state'];
            ?>" title="<?php
            echo CandidateView::cifEscape($step['status']);
            ?>"<?php
            echo in_array($step['state'], ['current', 'next', 'held'], true) ? ' aria-current="step"' : '';
            ?>>
        <button type="button" role="tab" class="roadmap-tab" id="roadmap-tab-<?php
            echo $step['key'];
            ?>" data-stage-tab="<?php
            echo $step['key'];
            ?>" aria-controls="stage-panel-<?php
            echo $step['key'];
            ?>" aria-selected="<?php
            echo $step['key'] === 'initial_interview' ? 'true' : 'false';
            ?>" tabindex="<?php
            echo $step['key'] === 'initial_interview' ? '0' : '-1';
            ?>">
        <span class="roadmap-dot" aria-hidden="true"><?php
            echo PortalIcons::portalIcon($icons[$step['key']]);
            if ($step['state'] === 'completed') {
                ?><span class="roadmap-done-mark"><?php
                echo PortalIcons::portalIcon('check');
                ?></span><?php
            }
            ?></span><span class="roadmap-step-label"><?php
            echo CandidateView::cifEscape($step['label']);
            ?></span><?php
            if ($step['state'] === 'not-required') {
                ?><small class="roadmap-excluded-label">Not required</small><?php
            }
            ?><span class="roadmap-sr-only"> — <?php
            echo CandidateView::cifEscape($step['status']);
            ?></span>
        </button></li>
      <?php
        }
        ?>
     </ol>
     <p class="roadmap-summary"><?php
        echo CandidateView::cifEscape($roadmap['summary']);
        ?></p>
     <p id="roadmap-help" class="roadmap-sr-only">CIF screening must be completed as Passed before HR interview. Failed or Backout screening blocks interviews. Department interview and Background check may finish in either order. Not required assessments do not count as passed. Job offer opens after HR interview and all required assessments pass. Deployment opens after the offer is finished as Accepted and completes when HR saves all required deployment details using Complete deployment. Candidate status follows these saved results automatically.</p>
    </section>
    <?php
    }
}
