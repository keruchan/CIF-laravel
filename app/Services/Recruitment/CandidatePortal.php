<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use PDO;

final class CandidatePortal
{
    public static function cifCandidateTrackerProgress(array $progress): array
    {
        // Keep optional assessments hidden until a candidate update confirms the
        // finished Interview's route. Its result can remain separately unpublished.
        // Older publication snapshots can establish completion only from a completed step.
        $interviewCompleted = $progress['interview_completed'] ?? (bool) array_filter($progress['steps'], static fn (array $step): bool => $step['key'] === 'initial_interview' && $step['state'] === 'completed');
        $progress['interview_completed'] = $interviewCompleted;
        $requirementsConfirmed = $progress['assessment_requirements_confirmed'] ?? $interviewCompleted;
        $progress['steps'] = array_values(array_filter($progress['steps'], static function (array $step) use ($progress, $requirementsConfirmed): bool {
            if ($step['key'] === 'exam' || $step['state'] === 'not-required') {
                return false;
            }
            if (in_array($step['key'], ['department_interview', 'lie_detection'], true)) {
                return $requirementsConfirmed && ($progress['required_assessments'][$step['key']] ?? true);
            }

            return true;
        }));
        if (in_array($progress['status'], ['Department Interview & Background Check', 'Department Interview', 'Background Check'], true)) {
            $active = array_values(array_filter($progress['steps'], static fn (array $step): bool => in_array($step['key'], ['department_interview', 'lie_detection'], true) && in_array($step['state'], ['current', 'under-review'], true)));
            $progress['status'] = count($active) === 2 ? 'Department Interview & Background Check' : ($active ? ($active[0]['key'] === 'department_interview' ? 'Department Interview' : 'Background Check') : 'Application review');
        }
        // Candidate wording only; keep stored stage keys and HR labels unchanged.
        foreach ($progress['steps'] as &$step) {
            if ($step['key'] === 'cif') {
                $step['label'] = 'SCREENING';
            }
            if ($step['key'] === 'initial_interview') {
                $step['label'] = 'Interview';
            }
        }
        unset($step);
        if (in_array($progress['status'], ['Initial Interview', 'Initial interview'], true)) {
            $progress['status'] = 'Interview';
        }
        if (($progress['next_schedule']['stage'] ?? null) === 'initial_interview') {
            $progress['next_schedule']['label'] = 'Interview';
        }
        $progress['completed'] = count(array_filter($progress['steps'], static fn (array $step): bool => $step['state'] === 'completed'));
        $progress['total'] = count(array_filter($progress['steps'], static fn (array $step): bool => $step['state'] !== 'not-required'));
        if (isset($progress['next_schedule']['stage']) && ! in_array($progress['next_schedule']['stage'], array_column($progress['steps'], 'key'), true)) {
            $progress['next_schedule'] = null;
        }

        return $progress;
    }

    public static function cifCandidateTrackerUpdateTimes(array $progress, array $updates, ?string $publishedAt = null): array
    {
        $times = [];
        $previous = [];
        $latest = $publishedAt;
        foreach ($updates as $update) {
            $snapshot = CandidatePortal::cifCandidateTrackerProgress(CandidatePublication::cifCompletePublicPrerequisites(json_decode($update['progress_json'], true, 32, JSON_THROW_ON_ERROR), $update['stage']));
            $latest = $update['published_at'];
            foreach ($snapshot['steps'] as $step) {
                $key = $step['key'];
                $state = [$step['state'], $step['status']];
                // An earlier release may also open the next phase. Date that visible
                // transition, without inventing update dates for untouched future phases.
                $changed = isset($previous[$key]) ? $previous[$key] !== $state : in_array($step['state'], ['current', 'under-review', 'completed'], true);
                if ($key === $update['stage'] || $changed) {
                    $times[$key] = $latest;
                }
                $previous[$key] = $state;
            }
        }
        $progress['updated_at'] = $latest;
        foreach ($progress['steps'] as &$step) {
            $step['updated_at'] = $times[$step['key']] ?? null;
        }
        unset($step);

        return $progress;
    }

    public static function cifPublicApplicationProgress(PDO $pdo, int $candidateId): array
    {
        $progress = CandidatePortal::cifCandidateTrackerProgress(CandidatePublication::cifReleasedProgress($pdo, $candidateId));
        $query = $pdo->prepare('SELECT stage,progress_json,published_at FROM candidate_public_updates WHERE candidate_id=? ORDER BY public_revision,id');
        $query->execute([$candidateId]);
        $updates = $query->fetchAll();
        $publishedAt = null;
        if (! $updates) {
            $query = $pdo->prepare('SELECT published_at FROM candidate_public_state WHERE candidate_id=?');
            $query->execute([$candidateId]);
            $publishedAt = $query->fetchColumn() ?: null;
        }

        return CandidatePortal::cifCandidateTrackerUpdateTimes($progress, $updates, $publishedAt);
    }

    public static function candidateState(): ?array
    {
        if (empty(session()->get('invitation_id')) || (session()->get('candidate_until') ?? 0) < time()) {
            session()->forget(['invitation_id', 'candidate_until', 'candidate_code_hash']);

            return null;
        }
        $query = RecruitmentDatabase::pdo()->prepare('SELECT i.email, i.position, i.submitted_at, i.redeemed_at, i.code_hash,i.access_enabled,i.access_version, i.expires_at, i.draft_form, i.draft_step, i.draft_revision, i.draft_saved_at, '.ApplicantAccess::cifInvitationCodeValidSql('i').' AS valid_code, c.id AS candidate_id FROM invitations i LEFT JOIN candidates c ON c.invitation_id = i.id WHERE i.id = ?');
        $query->execute([session()->get('invitation_id')]);
        $row = $query->fetch();
        $submitted = $row && $row['candidate_id'] !== null;
        if (! $row || ! (bool) $row['access_enabled'] || (int) $row['access_version'] !== (int) (session()->get('candidate_access_version') ?? 0) || ! $row['redeemed_at'] || ! $row['code_hash'] || ! hash_equals($row['code_hash'], (string) (session()->get('candidate_code_hash') ?? '')) || (! $submitted && (! $row['valid_code'] || $row['submitted_at']))) {
            session()->forget(['invitation_id', 'candidate_until', 'candidate_code_hash']);

            return null;
        }
        if ($submitted) {
            return ['mode' => 'tracking', 'email' => $row['email'], 'progress' => CandidatePortal::cifPublicApplicationProgress(RecruitmentDatabase::pdo(), (int) $row['candidate_id'])];
        }

        return ['mode' => 'form', 'email' => $row['email'], 'position' => $row['position'], 'expires_at' => $row['expires_at'],
            'draft' => ['form' => $row['draft_form'] === null ? null : json_decode($row['draft_form'], true, 32, JSON_THROW_ON_ERROR), 'step' => (int) $row['draft_step'], 'revision' => (int) $row['draft_revision'], 'saved_at' => $row['draft_saved_at']]];
    }
}
