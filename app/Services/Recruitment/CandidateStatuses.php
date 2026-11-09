<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use PDO;
use RuntimeException;
use Throwable;

final class CandidateStatuses
{
    public static function cifCandidateStatuses(): array
    {
        return ['Screening', 'Screening Failed', 'Screening Backout', 'Initial Interview', 'Department Interview & Background Check', 'Department Interview', 'Background Check', 'Job Offer', 'Deployment', 'Deployed'];
    }

    public static function cifCandidateStatusLabel(string $status): string
    {
        return $status === 'Initial Interview' ? 'HR interview' : $status;
    }

    public static function cifAutomaticCandidateStatus(array $candidate): string
    {
        $screening = CandidateInterviews::cifInterviewRecord($candidate, 'cif');
        if (empty($screening['completed_at'])) {
            return 'Screening';
        }
        if ($screening['decision'] !== 'Passed') {
            return $screening['decision'] === 'Backout' ? 'Screening Backout' : 'Screening Failed';
        }
        $initial = CandidateInterviews::cifInterviewRecord($candidate, 'initial_interview');
        if (empty($initial['completed_at']) || $initial['decision'] !== 'Passed') {
            return 'Initial Interview';
        }
        $waiting = [];
        foreach (['department_interview' => 'Department Interview', 'lie_detection' => 'Background Check'] as $stage => $label) {
            $record = CandidateInterviews::cifInterviewRecord($candidate, $stage);
            if (CandidateInterviews::cifAssessmentRequired($candidate, $stage) && (empty($record['completed_at']) || $record['decision'] !== 'Passed')) {
                $waiting[] = $label;
            }
        }
        if (count($waiting) === 2) {
            return 'Department Interview & Background Check';
        }
        if ($waiting) {
            return $waiting[0];
        }
        $offer = CandidateInterviews::cifInterviewRecord($candidate, 'job_offer');
        if (empty($offer['completed_at']) || $offer['decision'] !== 'Accepted') {
            return 'Job Offer';
        }

        return empty(CandidateDeployment::cifDeploymentRecord($candidate)['completed_at']) ? 'Deployment' : 'Deployed';
    }

    public static function syncCifCandidateStatus(PDO $pdo, int $id): string
    {
        $candidate = CandidateView::loadCifCandidate($pdo, $id);
        if (! $candidate) {
            throw new RuntimeException('Candidate no longer exists.');
        }
        $status = CandidateStatuses::cifAutomaticCandidateStatus($candidate);
        $pdo->prepare('UPDATE candidates SET status = ? WHERE id = ?')->execute([$status, $id]);

        return $status;
    }

    public static function refreshCifRetiredExamStatuses(PDO $pdo): void
    {
        foreach ($pdo->query("SELECT id FROM candidates WHERE status = 'Exam'")->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $pdo->beginTransaction();
            try {
                $lock = $pdo->prepare("SELECT id FROM candidates WHERE id = ? AND status = 'Exam' FOR UPDATE");
                $lock->execute([$id]);
                if ($lock->fetchColumn()) {
                    CandidateStatuses::syncCifCandidateStatus($pdo, (int) $id);
                }
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                } throw $error;
            }
        }
    }

    public static function cifNextStageAfterCompletion(array $candidate, string $completedStage): ?string
    {
        if ($completedStage === 'deployment') {
            return null;
        }
        $record = CandidateInterviews::cifInterviewRecord($candidate, $completedStage);
        if (empty($record['completed_at']) || $record['decision'] !== CandidateInterviews::cifStageSuccessfulDecision($completedStage)) {
            return null;
        }
        $next = match (CandidateStatuses::cifAutomaticCandidateStatus($candidate)) {
            'Screening', 'Screening Failed' => 'cif',
            'Initial Interview' => 'initial_interview',
            'Department Interview & Background Check', 'Department Interview' => 'department_interview',
            'Background Check' => 'lie_detection',
            'Job Offer' => 'job_offer',
            'Deployment', 'Deployed' => 'deployment',
        };

        return $next === $completedStage ? null : $next;
    }

    public static function cifCompletionMissing(array $candidate, bool $includeHr = true): array
    {
        $missing = [];
        foreach (CifFields::cifScalarFields() as $name => $field) {
            if ($field['required'] && $field['type'] === 'year_range') {
                foreach (CifEducation::cifEducationYearParts($candidate['form'][$name] ?? '') as $part => $year) {
                    if ($year === '') {
                        $missing[] = 'Inclusive years — '.($part === 'from' ? 'From year' : 'To year');
                    }
                }

                continue;
            }
            if ($field['required'] && trim((string) ($candidate['form'][$name] ?? '')) === '') {
                $missing[] = $field['label'];
            }
        }
        foreach (CifFields::cifDefinition()['groups'] as $group => $definition) {
            foreach ($candidate['form'][$group] ?? [] as $row) {
                $filled = false;
                foreach ($definition['fields'] as $name => $field) {
                    if (trim((string) ($row[$name] ?? '')) !== '') {
                        $filled = true;
                        break;
                    }
                }
                if ($filled && trim((string) ($row[$definition['anchor']] ?? '')) === '') {
                    $missing[] = $definition['label'].': '.$definition['fields'][$definition['anchor']]['label'];
                }
            }
        }
        if (empty($candidate['form']['consent'])) {
            $missing[] = 'Original certification acceptance';
        }
        if (empty($candidate['form']['background_consent'])) {
            $missing[] = 'Original background check acceptance';
        }
        foreach ($includeHr ? HrFields::cifHrFields() : [] as $fields) {
            foreach ($fields as $name => $label) {
                if (HrFields::cifHrRequired($name) && trim((string) ($candidate['hr'][$name] ?? '')) === '') {
                    $missing[] = 'HR: '.$label;
                }
            }
        }

        return $missing;
    }
}
