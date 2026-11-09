<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class CifRecordContent
{
    public static function renderAdminCandidateRecord(array $candidate): void
    {
        $statuses = CandidateStatuses::cifCandidateStatuses();
        ?>
<aside id="record-notification" class="record-notification" hidden aria-label="Record notification"><span id="notification-icon" class="notification-icon" aria-hidden="true">✓</span><div class="notification-content" role="status" aria-live="polite" aria-atomic="true"><strong id="notification-title"></strong><p id="notification-body"></p></div><button id="notification-dismiss" type="button" aria-label="Dismiss notification">×</button></aside>
<div class="record-toolbar">
 <span class="record-status-note">HR progress is internal. Use Update candidate to release a stage and send its email.</span>
 <div class="record-actions"><button id="prepare-record-email" type="button"<?php
        echo $candidate['archived_at'] ? ' disabled' : '';
        ?>>Send email</button><button id="archive-record" type="button"><?php
        echo $candidate['archived_at'] ? 'Restore' : 'Archive';
        ?></button><button id="print-record" type="button">Print / Save PDF</button></div>
 <p id="record-message" role="status" aria-live="polite"></p>
</div>
<?php
        CifEditor::renderCifEditor($candidate);
        CifPrint::renderCifPrint($candidate);
        ?>
<script id="record-config" type="application/json"><?php
        echo json_encode(['id' => (int) $candidate['id'], 'revision' => $candidate['revision'], 'archived' => (bool) $candidate['archived_at'], 'readOnly' => CandidateDeployment::cifCandidateIsDeployed($candidate), 'screeningPassed' => CandidateInterviews::cifInterviewPrerequisite($candidate, 'initial_interview') === null, 'autoPrint' => (request()->query()['print'] ?? '') === '1'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        ?></script>
    <?php
    }
}
