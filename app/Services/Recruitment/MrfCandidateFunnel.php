<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class MrfCandidateFunnel
{
    public static function cifMrfCandidateFunnelSummary(array $rows): array
    {
        $counts = array_fill_keys(['applied', 'interviewed', 'offers', 'accepted', 'hires'], 0);
        $totals = ['total' => 0, 'ongoing' => 0, 'hired' => 0, 'closed' => 0];
        $seen = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (isset($seen[$id])) {
                continue;
            } $seen[$id] = true;
            $totals['total']++;
            $counts['applied']++;
            $hired = ! empty($row['deployment_completed_at']) || $row['status'] === 'Deployed';
            $closed = false;
            $records = [];
            $required = ['department_interview' => (bool) $row['department_required'], 'lie_detection' => (bool) $row['ldt_required']];
            foreach (['cif', 'initial_interview', 'department_interview', 'lie_detection', 'job_offer'] as $stage) {
                $record = CandidateInterviews::cifInterviewRecord(['interviews' => [$stage => ['decision' => $row['decision_'.$stage] ?? 'Pending Decision', 'completed_at' => $row['completed_'.$stage] ?? null, 'revision' => (int) ($row['revision_'.$stage] ?? 0)]]], $stage);
                $records[$stage] = $record;
                $needed = $required[$stage] ?? true;
                if ($needed && $record['completed_at'] && in_array($record['decision'], ['Failed', 'Backout', 'Declined'], true)) {
                    $closed = true;
                }
                if (! $needed) {
                    continue;
                }
            }
            if ($hired) {
                $totals['hired']++;
            } elseif ($closed || in_array($row['status'], ['Screening Failed', 'Screening Backout'], true)) {
                $totals['closed']++;
            } else {
                $totals['ongoing']++;
            }
            // Match the KPI funnel: only finished interviews and offers count.
            $interview = $records['initial_interview'];
            $offer = $records['job_offer'];
            if (! $interview['completed_at'] || in_array($interview['decision'], ['No Response', 'Backout'], true)) {
                continue;
            }
            $counts['interviewed']++;
            if (! $offer['completed_at']) {
                continue;
            }
            $counts['offers']++;
            if (! in_array($offer['decision'], ['Accepted', 'Passed'], true)) {
                continue;
            }
            $counts['accepted']++;
            if (! empty($row['deployment_completed_at'])) {
                $counts['hires']++;
            }
        }

        return ['totals' => $totals, 'counts' => $counts];
    }

    public static function cifMrfCandidateFunnel(\PDO $pdo, int $requestId): array
    {
        $columns = '';
        $joins = '';
        foreach (['cif', 'initial_interview', 'department_interview', 'lie_detection', 'job_offer'] as $stage) {
            $columns .= ",s_$stage.decision AS decision_$stage,s_$stage.completed_at AS completed_$stage,s_$stage.revision AS revision_$stage";
            $joins .= " LEFT JOIN candidate_interview_stages s_$stage ON s_$stage.candidate_id=c.id AND s_$stage.stage='$stage'";
        }
        $q = $pdo->prepare("SELECT c.id,c.status,d.completed_at AS deployment_completed_at,d.revision AS deployment_revision,
        COALESCE(required.department_required,1) AS department_required,COALESCE(required.ldt_required,1) AS ldt_required$columns
        FROM candidates c
        LEFT JOIN candidate_admin_revisions r ON r.id=(SELECT MAX(latest.id) FROM candidate_admin_revisions latest WHERE latest.candidate_id=c.id)
        LEFT JOIN candidate_mrf_assignments link ON link.candidate_id=c.id AND link.revision_id=r.id
        LEFT JOIN candidate_deployments d ON d.candidate_id=c.id
        LEFT JOIN candidate_assessment_requirements required ON required.candidate_id=c.id$joins
        WHERE c.archived_at IS NULL AND COALESCE(d.request_id,link.request_id)=?");
        $q->execute([$requestId]);

        return MrfCandidateFunnel::cifMrfCandidateFunnelSummary($q->fetchAll(\PDO::FETCH_ASSOC));
    }

    public static function renderMrfCandidateFunnel(array $data, string $number): void
    {
        $totals = $data['totals'];
        $counts = $data['counts'];
        $stages = ['applied' => ['Applied', '#147dbb'], 'interviewed' => ['Interviewed', '#117aaa'], 'offers' => ['Got offers', '#087798'], 'accepted' => ['Job offer accepted', '#008c98'], 'hires' => ['Hired', '#00a6a6']];
        $keys = array_keys($stages);
        $percent = static fn (?float $value): string => $value === null ? '—' : rtrim(rtrim(number_format($value, 1, '.', ','), '0'), '.').'%';
        ?>
    <section class="card mrf-candidate-funnel" aria-labelledby="mrf-funnel-title">
     <div class="mrf-funnel-heading"><div><h2 id="mrf-funnel-title">Candidate funnel</h2><p>Applicants currently linked to MRF <?= Mrf::mrfEscape($number) ?>.</p></div><button id="mrf-funnel-refresh" type="button" class="btn g">Refresh</button></div>
     <?php if (! $totals['total']) { ?><p class="mrf-funnel-empty">No applicants are linked to this MRF yet.</p><?php } ?>
     <p class="mrf-funnel-caption">Number of applicants who reached each stage.</p>
     <ol class="hr-kpi-funnel-stages mrf-kpi-funnel" aria-label="Applicants by hiring stage">
     <?php foreach ($keys as $i => $key) {
         [$label,$color] = $stages[$key];
         $count = $counts[$key];
         $share = $totals['total'] ? 100 * $count / $totals['total'] : null;
         $previous = $i ? $counts[$keys[$i - 1]] : 0;
         $conversion = $previous ? 100 * $count / $previous : null; ?>
      <li data-funnel-stage="<?= $key ?>"><div class="hr-kpi-funnel-label"><span><?= $label ?></span><strong class="mrf-funnel-count"><?= $count ?></strong></div><div class="hr-kpi-funnel-track" aria-hidden="true"><div class="hr-kpi-funnel-bar" style="--funnel-width:<?= round($share ?? 0, 2) ?>%;--funnel-delay:<?= $i * 50 ?>ms;--funnel-color:<?= $color ?>"></div></div><div class="hr-kpi-funnel-percent"><span><?= $percent($share) ?> of applicants</span><span><?= $i ? $percent($conversion).' from previous step' : 'Starting point' ?></span></div></li>
     <?php } ?>
     </ol><p class="mrf-funnel-note">Archived applicants are excluded. Counts update when you refresh this page.</p>
    </section>
    <?php
    }
}
