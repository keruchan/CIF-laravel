<?php
declare(strict_types=1);

namespace App\Services\Recruitment;

use DateTimeImmutable;
use DateTimeZone;

final class MrfRecordContent
{
    public static function renderMrfRecordContent(array $record, string $mrfPortal): void
    {
        $date = (new DateTimeImmutable($record['created_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('M d, Y h:i A');
        $approvalNote = match ($record['approval_status']) {
            'Approved' => 'Management approval has been verified by HR. The approval applies to the entire request and all headcount files. The recorded decision and notes are final.',
            'Denied' => 'This request was denied and cannot be selected for recruitment. The denial applies to the entire request and all headcount files. The recorded decision and notes are final.',
            default => 'Awaiting HR verification of management approval. This request cannot be selected for recruitment until approved. The decision will apply to the entire request and all headcount files.',
        };
        ?>
<div class="mrf-record-wrap mrf-in-portal" data-mrf-id="<?= (int) $record['id'] ?>" data-revision="<?= (int) $record['revision'] ?>" data-portal="<?= $mrfPortal ?>">
 <div class="mrf-record-top"><div><h1>Manpower request <?= Mrf::mrfEscape($record['mrf_number']) ?></h1><p>Submitted <?= Mrf::mrfEscape($date) ?> · <?= Mrf::mrfEscape($record['owner_username']) ?></p></div><div class="mrf-record-actions"><a class="btn g" href="./#mrf">Back to requests</a><button class="btn" id="mrf-print" type="button">Print / Save PDF</button></div></div>
 <div class="mrf-record-content">
 <?php if ($mrfPortal === 'admin' && $record['import'] && isset($_GET['imported'])) { ?><div class="mrf-success" role="status"><?= count($record['files']) ?> separate MRF file(s) were created from the uploaded request. Each has its own page, printable form and HR status.</div><?php } ?>
 <?php if ($mrfPortal === 'admin') { ?><div class="mrf-record-overview"><?php } ?>
 <?php if ($mrfPortal === 'requestor' && isset($_GET['submitted'])) { ?><div id="mrf-submission-success" class="mrf-success" role="status">Manpower request <?= Mrf::mrfEscape($record['mrf_number']) ?> was completed for <?= (int) $record['headcount'] ?> headcount(s). Print this form for management approval, then deliver the approved copy or confirmation to HR. Recruitment can start only after HR records Approved.</div><?php } ?>
 <section class="card mrf-management mrf-approval-management"><div class="mrf-heading"><div><h2>Management approval</h2><span class="mrf-status mrf-approval-status" data-status="<?= Mrf::mrfEscape($record['approval_status']) ?>"><?= $record['approval_status'] === 'Pending' ? 'Awaiting approval' : Mrf::mrfEscape($record['approval_status']) ?></span></div><?php if ($mrfPortal === 'admin' && $record['approval_status'] === 'Pending') { ?><button class="btn" id="mrf-record-approval" type="button">Record approval / denial</button><?php } ?></div><p class="mrf-list-note"><?= Mrf::mrfEscape($approvalNote) ?></p>
 <?php if ($record['approval_status'] === 'Approved') { ?><dl class="mrf-field-grid mrf-approval-dates"><div><dt>Date Approved (management signed)</dt><dd><?= Mrf::mrfEscape($record['date_approved'] ?? 'Not recorded') ?></dd></div><div><dt>Position category / lead time</dt><dd><?= ! empty($record['position_category']) ? Mrf::mrfEscape($record['position_category'].' · '.$record['lead_time_days'].' calendar days') : 'Not recorded' ?></dd></div><div><dt>Hiring due date</dt><dd><?= Mrf::mrfEscape($record['hiring_due_date'] ?? 'Not recorded') ?></dd></div></dl><?php } ?>
 <?php if ($record['approval_admin']) { ?><p class="mrf-list-note">Acknowledged by HR: <?= Mrf::mrfEscape($record['approval_admin']) ?> · <?= Mrf::mrfEscape((new DateTimeImmutable($record['approval_updated_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('M d, Y h:i A')) ?></p><?php } ?>
 <?php if ($record['approval_notes']) { ?><p class="mrf-approval-notes"><?= nl2br(Mrf::mrfEscape($record['approval_notes'])) ?></p><?php } ?>
 <details class="mrf-history"><summary>Approval history</summary><?php foreach ($record['approval_history'] as $entry) { ?><div class="mrf-history-entry"><?= Mrf::mrfEscape($entry['previous_decision'].' → '.$entry['decision'].' · '.$entry['admin_name']) ?><br><?= Mrf::mrfEscape((new DateTimeImmutable($entry['created_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('M d, Y h:i A')) ?><br><?= nl2br(Mrf::mrfEscape($entry['notes'])) ?></div><?php } ?><?php if (! $record['approval_history']) { ?><p class="mrf-list-note">No management decision recorded yet.</p><?php } ?></details></section>
 <?php if ($mrfPortal === 'admin') {
     MrfCandidateFunnel::renderMrfCandidateFunnel($record['candidate_funnel'] ?? MrfCandidateFunnel::cifMrfCandidateFunnelSummary([]), $record['mrf_number']); ?></div><?php } ?>
 <?php if ($mrfPortal === 'admin' && $record['approval_status'] === 'Approved') {
     $assignment = $record['hr_assignment']; ?>
 <section class="card mrf-management"><h2>Assigned HR</h2><p class="mrf-list-note">One owner per headcount file. KPI targets and hires follow the currently assigned HR member; all assignment changes are recorded. Leaving it unassigned allows automatic ownership on the first qualifying recruitment action.</p>
 <form id="mrf-assignment-form" data-revision="<?= $assignment['revision'] ?>"><div class="mrf-field-grid"><div class="mrf-field"><label for="mrf-assigned-hr">HR member <small>(optional)</small></label><select id="mrf-assigned-hr"><option value="">Unassigned / automatic</option><?php foreach ($record['hr_staff_options'] as $person) { ?><option value="<?= $person['id'] ?>"<?= $assignment['hr_admin_id'] === $person['id'] ? ' selected' : '' ?>><?= Mrf::mrfEscape($person['name']) ?></option><?php } ?></select></div><div class="mrf-field"><label for="mrf-assignment-reason">Assignment note / reason for change</label><textarea id="mrf-assignment-reason" rows="2" maxlength="2000"></textarea></div></div><div class="settings-actions"><button class="btn" type="submit">Save HR assignment</button></div><p id="mrf-assignment-feedback" class="settings-feedback" role="status" hidden></p><p id="mrf-assignment-error" class="settings-error" role="alert" hidden></p></form>
 <details class="mrf-history"><summary>Assignment history</summary><div id="mrf-assignment-history-list"><?php foreach ($assignment['history'] as $entry) { ?><div class="mrf-history-entry"><?= Mrf::mrfEscape(($entry['previous_name'] ?? 'Unassigned').' → '.($entry['name'] ?? 'Unassigned').' · '.$entry['actor']) ?><br><?= Mrf::mrfEscape($entry['created_at'].' UTC · '.$entry['source']) ?><br><?= Mrf::mrfEscape($entry['reason']) ?></div><?php } ?><?php if (! $assignment['history']) { ?>No HR assignment recorded yet.<?php } ?></div></details></section>
 <?php } ?>
 <section class="card mrf-management"><h2><?= $mrfPortal === 'requestor' ? 'Request status & HR updates' : 'HR management' ?></h2><span id="mrf-record-status" class="mrf-status" data-status="<?= Mrf::mrfEscape($record['status']) ?>"><?= Mrf::mrfEscape($record['status']) ?></span>
 <?php if ($mrfPortal === 'admin') { ?>
 <form id="mrf-management-form"><div class="mrf-field-grid"><div class="mrf-field"><label for="mrf-manage-status">Recruitment progress</label><select id="mrf-manage-status" required><?php foreach (Mrf::mrfStatuses() as $status) {
     if ($record['status'] === 'Cancelled' && ! in_array($status, ['Cancelled', 'In Progress'], true)) {
         continue;
     } if (in_array($status, ['On Hold', 'Cancelled'], true) && ($record['approval_status'] !== 'Approved' || $record['status'] === 'Completed')) {
         continue;
     } ?><option value="<?= Mrf::mrfEscape($status) ?>"<?= $status === $record['status'] ? ' selected' : '' ?>><?= Mrf::mrfEscape($status.($status === 'In Progress' && in_array($record['status'], ['On Hold', 'Cancelled'], true) ? ($record['status'] === 'Cancelled' ? ' — Reinstate' : ' — Resume') : '')) ?></option><?php } ?></select><small class="mrf-hint">Hold or cancel only after approval and before completion. Resume or reinstate returns the file to In Progress after confirmation. The original hiring due date stays unchanged.</small></div><div class="mrf-field"><label for="mrf-hr-notes">HR notes / status reason</label><textarea id="mrf-hr-notes" rows="3" maxlength="4000"><?= Mrf::mrfEscape($record['hr_notes']) ?></textarea></div></div>
 <div class="mrf-heading-actions"><button class="btn" id="mrf-manage-save" type="submit">Save changes</button><button class="btn g" id="mrf-record-refresh" type="button">Refresh record</button></div><p id="mrf-manage-error" class="settings-error" role="alert" hidden></p><p id="mrf-manage-success" class="settings-feedback" role="status" aria-live="polite" hidden></p></form>
 <?php } else { ?><p class="mrf-list-note" style="margin-top:14px">Headcount progress: <?= Mrf::mrfEscape(implode(' · ', array_map(static fn ($status, $count) => $status.': '.$count, array_keys($record['status_counts']), $record['status_counts']))) ?>. Mixed headcount statuses are shown as In Progress.</p><div class="mrf-value" style="margin-top:14px"><dt>HR notes</dt><dd><?= nl2br(Mrf::mrfEscape($record['hr_notes'])) ?></dd></div><?php } ?>
 <details class="mrf-history"><summary>Status & notes history</summary><div id="mrf-history-list"><?php foreach ($record['history'] as $entry) { ?><div class="mrf-history-entry"><?= $mrfPortal === 'requestor' ? Mrf::mrfEscape('Headcount '.$entry['headcount_index'].' · ') : '' ?><?= Mrf::mrfEscape($entry['previous_status'].' → '.$entry['status'].' · '.$entry['admin_name']) ?><br><?= Mrf::mrfEscape((new DateTimeImmutable($entry['created_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('M d, Y h:i A')) ?><br><?= nl2br(Mrf::mrfEscape($entry['hr_notes'])) ?></div><?php } ?><?php if (! $record['history']) { ?><p class="mrf-list-note">No HR changes yet.</p><?php } ?></div></details>
 </section>
 <?php if ($mrfPortal === 'admin') { ?><section class="card mrf-detail-section"><h2>Separate MRF files · <?= (int) $record['headcount'] ?> headcount(s)</h2><p class="mrf-list-note">Viewing headcount file <?= (int) $record['headcount_index'] ?> of <?= (int) $record['headcount'] ?>. Each file has the same submitted information, its own printable form and an independently managed HR status.</p><div class="mrf-reference-list"><?php foreach ($record['files'] as $file) { ?><a class="mrf-related-file<?= (int) $file['id'] === (int) $record['id'] ? ' current' : '' ?>" href="mrf.php?id=<?= (int) $file['id'] ?>"<?= (int) $file['id'] === (int) $record['id'] ? ' aria-current="page"' : '' ?>><?= Mrf::mrfEscape($file['mrf_number']) ?><small><?= Mrf::mrfEscape($file['status']) ?></small></a><?php } ?></div></section><?php } ?>
 <?php MrfView::renderMrfDetails($record); ?>
 <p class="mrf-list-note">Names entered under signatories are not electronic signatures or approval records. Use the printable signature spaces when needed.</p>
 </div><div class="mrf-print-only"><?php MrfView::renderMrfPrint($record); ?></div>
 </div>
    <?php
    }
}
