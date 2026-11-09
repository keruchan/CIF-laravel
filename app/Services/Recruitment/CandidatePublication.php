<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use Illuminate\Http\Exceptions\HttpResponseException;

final class CandidatePublication
{
    /** Only public workflow facts: never copy notes, scores, contact info or remarks. */
    public static function cifPublicFacts(array $candidate): array
    {
        $stages = [];
        foreach (CandidateInterviews::cifInterviewStages() as $key => $label) {
            $r = CandidateInterviews::cifInterviewRecord($candidate, $key);
            $stages[$key] = ['decision' => $r['decision'], 'completed_at' => $r['completed_at'], 'revision' => (int) $r['revision']];
        }
        $r = CandidateInterviews::cifAssessmentRequirements($candidate);
        $d = CandidateDeployment::cifDeploymentRecord($candidate);

        return ['candidate_number' => $candidate['candidate_number'], 'position' => $candidate['position'], 'submitted_at' => $candidate['submitted_at'], 'interviews' => $stages, 'assessment_requirements' => ['department_required' => (bool) $r['department_required'], 'ldt_required' => (bool) $r['ldt_required'], 'revision' => (int) ($r['revision'] ?? 0)], 'deployment' => ['completed_at' => $d['completed_at'], 'revision' => (int) $d['revision']]];
    }

    /** Schema must be initialized before entering a save transaction. */
    public static function cifEnsurePublicBaseline(\PDO $pdo, array $candidate): array
    {
        $q = $pdo->prepare('INSERT IGNORE INTO candidate_public_state(candidate_id,state_json) VALUES (?,?)');
        $q->execute([$candidate['id'], json_encode(CandidatePublication::cifPublicFacts($candidate), JSON_THROW_ON_ERROR)]);
        $q = $pdo->prepare('SELECT * FROM candidate_public_state WHERE candidate_id=?');
        $q->execute([$candidate['id']]);
        $row = $q->fetch();
        $row['state'] = json_decode($row['state_json'], true, 32, JSON_THROW_ON_ERROR);
        $row['revision'] = (int) $row['revision'];

        return $row;
    }

    public static function cifProgressFromPublicFacts(array $facts): array
    {
        $roadmap = CandidateRoadmap::cifHiringRoadmap($facts);
        $steps = [];
        foreach ($roadmap['steps'] as $step) {
            $state = match ($step['state']) {
                'completed' => 'completed','not-required' => 'not-required','held' => 'under-review','current','next' => 'current',default => 'pending'
            };
            $steps[] = ['key' => $step['key'], 'label' => $step['label'], 'status' => $step['status'], 'state' => $state, 'connected' => $step['connector_active']];
        }
        $progress = ['applicant_id' => $facts['candidate_number'], 'position' => $facts['position'], 'submitted_at' => $facts['submitted_at'],
            'status' => CandidateStatuses::cifAutomaticCandidateStatus($facts), 'steps' => $steps, 'completed' => $roadmap['completed'], 'total' => $roadmap['total'], 'next_schedule' => CandidateSchedule::cifPublicNextSchedule($facts),
            'required_assessments' => ['department_interview' => CandidateInterviews::cifAssessmentRequired($facts, 'department_interview'), 'lie_detection' => CandidateInterviews::cifAssessmentRequired($facts, 'lie_detection')],
            'interview_completed' => ! empty(CandidateInterviews::cifInterviewRecord($facts, 'initial_interview')['completed_at']),
            'assessment_requirements_confirmed' => ! empty($facts['assessment_requirements_confirmed']) || ! empty(CandidateInterviews::cifInterviewRecord($facts, 'initial_interview')['completed_at'])];
        $stage = $facts['released_stage'] ?? null;
        if (! is_string($stage) || ! isset(HrStaff::cifHrStages()[$stage]) || CandidateInterviews::cifInterviewPrerequisite($facts, $stage) === null) {
            return $progress;
        }
        // The selected phase is visible even if its earlier results were not shared.
        $successful = [];
        foreach ($progress['steps'] as &$step) {
            $key = $step['key'];
            $done = $key === 'deployment' ? ! empty($facts['deployment']['completed_at']) :
                (! empty(CandidateInterviews::cifInterviewRecord($facts, $key)['completed_at']) && CandidateInterviews::cifInterviewRecord($facts, $key)['decision'] === CandidateInterviews::cifStageSuccessfulDecision($key));
            $successful[$key] = $done;
            $step['connected'] = false;
            if ($step['state'] === 'not-required' && $key !== $stage) {
                continue;
            }
            $step['state'] = $done ? 'completed' : 'pending';
            $step['status'] = $done ? ($key === 'deployment' ? 'Completed' : CandidateInterviews::cifStageSuccessfulDecision($key)) : 'Not started';
        }
        unset($step);
        $current = [$stage];
        if ($successful[$stage]) {
            $current = match ($stage) {
                'cif' => ['initial_interview'],
                'initial_interview', 'department_interview', 'lie_detection' => array_values(array_filter(['department_interview', 'lie_detection'], static fn (string $key): bool => CandidateInterviews::cifAssessmentRequired($facts, $key) && ! $successful[$key])),
                'job_offer' => ['deployment'], 'deployment' => [],
            };
            if (! $current && in_array($stage, ['initial_interview', 'department_interview', 'lie_detection'], true)) {
                $current = ['job_offer'];
            }
        }
        foreach ($progress['steps'] as &$step) {
            if (! in_array($step['key'], $current, true) || $step['state'] === 'completed') {
                continue;
            }
            $key = $step['key'];
            $record = $key === 'deployment' ? ($facts['deployment'] ?? []) : CandidateInterviews::cifInterviewRecord($facts, $key);
            $step['status'] = $key === 'deployment' ? 'In progress' : $record['decision'];
            $step['state'] = in_array($step['status'], ['Failed', 'Backout', 'Declined', 'Pooling', 'No Response'], true) ? 'under-review' : 'current';
        }
        unset($step);
        $active = array_values(array_filter($progress['steps'], static fn (array $step): bool => in_array($step['state'], ['current', 'under-review'], true)));
        $progress['status'] = count($active) === 2 ? 'Department Interview & Background Check' : ($active ? match ($active[0]['key']) {
            'cif' => $active[0]['status'] === 'Backout' ? 'Screening Backout' : ($active[0]['status'] === 'Failed' ? 'Screening Failed' : 'Screening'),
            'initial_interview' => 'Initial Interview', 'department_interview' => 'Department Interview',
            'lie_detection' => 'Background Check', 'job_offer' => 'Job Offer', 'deployment' => 'Deployment',
        } : ($successful['deployment'] ? 'Deployed' : $progress['status']));
        $progress['completed'] = count(array_filter($progress['steps'], static fn (array $step): bool => $step['state'] === 'completed'));
        $progress['total'] = count(array_filter($progress['steps'], static fn (array $step): bool => $step['state'] !== 'not-required'));

        return CandidatePublication::cifCompletePublicPrerequisites($progress, $stage);
    }

    public static function cifReleasedProgress(\PDO $pdo, int $candidateId): array
    {
        $q = $pdo->prepare('SELECT state_json FROM candidate_public_state WHERE candidate_id=?');
        $q->execute([$candidateId]);
        $json = $q->fetchColumn();
        if ($json !== false) {
            return CandidatePublication::cifProgressFromPublicFacts(json_decode($json, true, 32, JSON_THROW_ON_ERROR));
        }
        $owns = ! $pdo->inTransaction();
        if ($owns) {
            $pdo->beginTransaction();
        }
        try {
            $q = $pdo->prepare('SELECT id FROM candidates WHERE id=? FOR UPDATE');
            $q->execute([$candidateId]);
            $candidate = CandidateView::loadCifCandidate($pdo, $candidateId);
            if (! $candidate) {
                throw new \RuntimeException('Submitted application not found.');
            }
            $row = CandidatePublication::cifEnsurePublicBaseline($pdo, $candidate);
            if ($owns) {
                $pdo->commit();
            }

            return CandidatePublication::cifProgressFromPublicFacts($row['state']);
        } catch (\Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            if ($owns && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function cifProposedStageRelease(array $candidate, array $public, string $stage): array
    {
        if (! isset(HrStaff::cifHrStages()[$stage])) {
            throw new \InvalidArgumentException('Choose a valid hiring stage.');
        }
        if ($candidate['archived_at']) {
            throw new \InvalidArgumentException('Restore this candidate before sending an update.');
        }
        $facts = CandidatePublication::cifPublicFacts($candidate);
        $next = $public['state'];
        $next['released_stage'] = $stage;
        if ($stage === 'deployment') {
            $next['deployment'] = $facts['deployment'];
        } else {
            $next['interviews'][$stage] = $facts['interviews'][$stage];
        }
        if ($stage === 'cif') {
            $next['position'] = $facts['position'];
        }
        // Finishing Interview auto-opens the next tab. Update candidate from that
        // tab must publish the chosen route too, without releasing Interview's result.
        if (! empty($facts['interviews']['initial_interview']['completed_at'])) {
            $next['assessment_requirements'] = $facts['assessment_requirements'];
            $next['assessment_requirements_confirmed'] = true;
        } elseif ($stage === 'initial_interview') {
            $next['assessment_requirements'] = $facts['assessment_requirements'];
            $next['assessment_requirements_confirmed'] = false;
        }

        return $next;
    }

    public static function cifCompletePublicPrerequisites(array $progress, string $stage): array
    {
        $required = $progress['required_assessments'] ?? ['department_interview' => true, 'lie_detection' => true];
        if (in_array($stage, ['department_interview', 'lie_detection'], true) && ! ($required[$stage] ?? true)) {
            return $progress;
        }
        $previous = match ($stage) {
            'initial_interview' => ['cif'],
            'department_interview', 'lie_detection' => ['cif', 'initial_interview'],
            'job_offer', 'deployment' => array_merge(['cif', 'initial_interview'], array_keys(array_filter($required)), $stage === 'deployment' ? ['job_offer'] : []),
            default => [],
        };
        if (! $previous) {
            return $progress;
        }
        foreach ($progress['steps'] as &$step) {
            if (in_array($step['key'], $previous, true) && $step['state'] !== 'not-required') {
                $step['state'] = 'completed';
                $step['status'] = CandidateInterviews::cifStageSuccessfulDecision($step['key']);
            }
        }
        unset($step);
        if (in_array('initial_interview', $previous, true)) {
            $progress['interview_completed'] = true;
        }
        $states = array_column($progress['steps'], 'state', 'key');
        $screened = ($states['cif'] ?? '') === 'completed';
        $interviewed = ($states['initial_interview'] ?? '') === 'completed';
        $assessed = $interviewed;
        foreach ($required as $key => $needed) {
            if ($needed && ($states[$key] ?? '') !== 'completed') {
                $assessed = false;
            }
        }
        foreach ($progress['steps'] as &$step) {
            $step['connected'] = $step['state'] !== 'not-required' && match ($step['key']) {
                'initial_interview' => $screened,
                'department_interview', 'lie_detection' => $interviewed,
                'job_offer' => $assessed,
                'deployment' => ($states['job_offer'] ?? '') === 'completed',
                default => false,
            };
        }
        unset($step);
        $progress['completed'] = count(array_filter($progress['steps'], static fn (array $step): bool => $step['state'] === 'completed'));
        $progress['total'] = count(array_filter($progress['steps'], static fn (array $step): bool => $step['state'] !== 'not-required'));

        return $progress;
    }
}
