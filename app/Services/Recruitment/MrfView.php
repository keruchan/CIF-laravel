<?php
declare(strict_types=1);

namespace App\Services\Recruitment;

use DateTimeImmutable;
use DateTimeZone;

final class MrfView
{
    public static function renderMrfForm(bool $import = false): void
    {
        $account = session()->get('department') ?? [];
        $fixed = $import ? [] : ['requestor_name' => $account['name'] ?? '', 'requestor_position' => $account['position'] ?? '', 'department_name' => $account['department_name'] ?? '', 'group_name' => $account['group_name'] ?? ''];
        $prefix = $import ? 'mrf-import' : 'mrf';
        ?>
    <div class="card mrf-form-card" id="<?= $prefix ?>-form-card">
    <div class="mrf-heading"><div><h2><?= $import ? 'Review recognized fields' : 'Manpower Request Form' ?></h2><p><?= $import ? 'Verify every recognized value and fill in missing information before saving.' : 'Based on APEC Homes MRF version 2025-1.' ?></p></div></div>
    <div class="mrf-generated"><span><strong>MRF no.</strong> <output id="<?= $prefix ?>-generated-number">Generated automatically after creation</output></span><span><strong>Date requested</strong> Recorded when the request is created</span></div>
    <form id="<?= $prefix ?>-request-form">
    <?php if ($import) { ?><div class="mrf-field mrf-import-owner"><label for="mrf-import-owner">Responsible MRF requestor</label><select id="mrf-import-owner" required></select><small class="mrf-hint">This account can view the request. Requestor details below come from the document and remain editable during review.</small></div><?php } ?>
    <?php foreach (Mrf::mrfFields() as $section => $fields) { ?>
    <fieldset class="mrf-section"><legend><?= Mrf::mrfEscape($section) ?></legend>
    <?php if ($section === 'Signatories') { ?><p class="mrf-section-note">Prepared by uses the requestor’s name. Other names are optional; entering a name is not an electronic approval. Signature spaces remain available in print.</p><?php } ?>
    <div class="mrf-field-grid">
    <?php foreach ($fields as $key => $field) { ?>
     <?php if (in_array($key, ['buddy_1_name', 'buddy_2_name'], true)) { ?><div class="mrf-buddy-row"><?php } ?>
     <div class="mrf-field <?= $key === 'reason_details' ? 'mrf-wide' : ($field['type'] === 'textarea' ? 'mrf-description' : '') ?>"<?= $key === 'replacement_of' ? ' id="'.$prefix.'-replacement-field" hidden' : '' ?>>
      <label for="<?= $prefix ?>-<?= Mrf::mrfEscape($key) ?>"><?= Mrf::mrfEscape($field['label']) ?><?= ! $field['required'] ? ' <small data-optional-label>(optional)</small>' : '' ?></label>
      <?php $attrs = ' id="'.$prefix.'-'.$key.'" name="'.$key.'"'.($field['required'] ? ' required' : ''); ?>
      <?php if ($key === 'position_title') {
          HiringPositions::renderCifPositionPicker($prefix.'-'.$key, $attrs); ?>
      <?php } elseif ($field['type'] === 'select') { ?><select<?= $attrs ?>><option value="">Select</option><?php foreach ($field['options'] as $option) { ?><option><?= Mrf::mrfEscape($option) ?></option><?php } ?></select>
      <?php } elseif ($field['type'] === 'textarea') { ?><textarea<?= $attrs ?> rows="3" maxlength="<?= $field['max'] ?>"></textarea>
      <?php } elseif ($field['type'] === 'number') { ?><input<?= $attrs ?> type="number" min="<?= $field['min'] ?>" max="<?= $field['max'] ?>" step="<?= $field['step'] ?>" inputmode="<?= $key === 'headcount' ? 'numeric' : 'decimal' ?>">
      <?php } elseif ($field['type'] === 'tel') { ?><div class="mrf-contact-number"><span aria-hidden="true">+63-</span><input<?= $attrs ?> type="tel" maxlength="<?= $field['max'] ?>" data-contact-number inputmode="numeric" pattern="[1-9][0-9]{2}-[0-9]{3}-[0-9]{4}" placeholder="9XX-XXX-XXXX" aria-describedby="<?= $prefix ?>-<?= Mrf::mrfEscape($key) ?>-hint"></div><small class="mrf-hint" id="<?= $prefix ?>-<?= Mrf::mrfEscape($key) ?>-hint">+63 is fixed. Enter the remaining 10 digits.</small>
      <?php } else { ?><input<?= $attrs ?> type="<?= $field['type'] ?>" maxlength="<?= $field['max'] ?>"<?= array_key_exists($key, $fixed) ? ' readonly data-account-field value="'.Mrf::mrfEscape($fixed[$key]).'" aria-describedby="mrf-account-hint"' : '' ?>><?php } ?>
      <?php if ($key === 'headcount') { ?><small id="<?= $prefix ?>-headcount-hint" class="mrf-hint"><?= $import ? 'HR creates a separate numbered file for each headcount.' : 'You receive one request number. HR tracks each headcount separately.' ?></small><?php } ?>
     </div>
     <?php if (in_array($key, ['buddy_1_contact', 'buddy_2_contact'], true)) { ?></div><?php } ?>
    <?php } ?></div></fieldset>
    <?php } ?>
    <?php if (! $import) { ?><p id="mrf-account-hint" class="mrf-section-note">Name, position, department and group come from your requestor account. Only your group and password can be changed in Account settings.</p><?php } ?>
    <p id="<?= $prefix ?>-form-error" class="settings-error" role="alert" hidden></p>
    <div class="mrf-submit-footer"><p><?= $import ? 'Saving a new request generates its unique MRF number automatically. The number stays the same after approval or denial. HR must verify management approval separately. The uploaded document is kept as a source copy.' : 'Creating this request generates its unique MRF number automatically. It cannot be edited and stays the same whether approval is pending, approved or denied. Print the generated form for management approval, then deliver the approved copy or confirmation to HR.' ?></p><div class="mrf-heading-actions"><button class="btn g" id="<?= $prefix ?>-submit" type="submit"><?= $import ? 'Save uploaded request' : 'Complete request' ?></button><?php if (! $import) { ?><button class="btn" id="mrf-submit-print" type="submit" data-print="true">Complete &amp; print</button><?php } ?></div></div>
    </form></div>
    <?php
    }

    public static function renderMrfList(bool $requestor): void
    {
        ?>
    <div class="mrf-heading"><div><h2><?= $requestor ? 'Your manpower requests' : 'Manpower overview' ?></h2><p><?= $requestor ? 'Complete and print requests for management approval, then track HR verification.' : 'Review approval decisions, monitor open headcounts and track completed requests.' ?></p></div><div class="mrf-heading-actions"><button class="btn g" id="mrf-refresh" type="button">Refresh</button><?php if ($requestor) { ?><a class="btn" id="mrf-new" href="mrf-form.php" style="text-decoration:none">New manpower request</a><?php } else { ?><button class="btn" id="mrf-upload-open" type="button" aria-haspopup="dialog" aria-controls="mrf-upload-dialog">Upload request</button><?php } ?></div></div>
    <?php if ($requestor) {
        MrfView::renderRequestorCoverage('mrf');
    } ?>
    <div class="stats mrf-stats" id="mrf-stats" aria-label="Manpower request summaries"></div>
    <?php if (! $requestor) { ?><div id="mrf-dashboard-filter" class="candidate-insight hide"><span id="mrf-dashboard-filter-label"></span><button id="mrf-dashboard-filter-clear" class="btn g" type="button">Clear dashboard filter</button></div><?php } ?>
    <div class="card"><div class="tb table-filters"><input class="s" id="mrf-search" type="search" maxlength="254" placeholder="Search MRF no., position, requestor, department or site" aria-label="Search manpower requests"><select id="mrf-filter-approval" aria-label="Filter management approval"><option value="">All approvals</option><?php foreach (Mrf::mrfApprovalStatuses() as $status) { ?><option value="<?= $status ?>"><?= $status === 'Pending' ? 'Awaiting approval' : $status ?></option><?php } ?></select><select id="mrf-filter-status" aria-label="Filter request status"><option value="">All recruitment progress</option><?php foreach (Mrf::mrfStatuses() as $status) { ?><option><?= Mrf::mrfEscape($status) ?></option><?php } ?></select></div>
    <p class="mrf-list-note"><?= $requestor ? 'One row per request. Print it for approval outside the system, then send the approved copy or confirmation to HR.' : 'Approval applies to the whole printed request and all its headcount files. Only Approved requests with available recruitment progress appear in MRF selectors.' ?></p>
    <div class="tw"><table class="mrf-table"><thead><tr><th>MRF no.</th><th>Position / site</th><th>Requestor / department</th><?php if ($requestor) { ?><th>Headcount needed</th><?php } ?><th>Approval</th><th>Recruitment progress</th><th class="mrf-created" aria-sort="descending"><button class="sort-header" id="mrf-sort-date" type="button">Created <span class="sort-direction" aria-hidden="true">↓</span></button></th><th>Actions</th></tr></thead><tbody id="mrf-rows"></tbody></table></div>
    <p id="mrf-list-error" class="settings-error" role="alert" hidden></p>
    <div class="pgn"><span id="mrf-info" role="status" aria-live="polite"></span><div id="mrf-pages" aria-label="Manpower request pagination"></div></div></div>
    <?php
    }

    public static function renderMrfApprovalDialog(): void
    {
        ?>
    <dialog id="mrf-approval-dialog" aria-labelledby="mrf-approval-title">
     <div class="mrf-heading"><h2 id="mrf-approval-title">Record management decision</h2><button type="button" class="btn g" id="mrf-approval-close">Close</button></div>
     <p id="mrf-approval-summary" class="mrf-list-note"></p><p class="mrf-list-note">HR acknowledges management’s decision made outside the system. It applies to the entire request and all headcount files. Check carefully: the decision, approval details and notes are final once confirmed. HR assignment can be managed separately later.</p>
     <form id="mrf-approval-form">
      <div class="f"><label for="mrf-approval-decision">Management decision</label><select id="mrf-approval-decision" required><option>Approved</option><option>Denied</option></select></div>
      <fieldset id="mrf-approval-timing" class="mrf-approval-timing"><legend>Management approval details</legend>
       <div class="mrf-approval-timing-grid">
        <div class="f"><label for="mrf-approval-date">Date Approved <span aria-hidden="true">*</span></label><input id="mrf-approval-date" type="date" min="1900-01-01" required aria-describedby="mrf-approval-date-help"><small id="mrf-approval-date-help" class="mrf-hint">Use the date management signed the approval, not the date HR records it. An earlier date is allowed.</small></div>
        <div class="f"><label for="mrf-approval-category">Position category <span aria-hidden="true">*</span></label><select id="mrf-approval-category" required><option value="">Select category</option><?php foreach (MrfApproval::cifMrfPositionCategories() as $category => $days) { ?><option value="<?= Mrf::mrfEscape($category) ?>" data-days="<?= $days ?>"><?= Mrf::mrfEscape($category) ?> — <?= $days ?> calendar days</option><?php } ?></select></div>
       </div><p id="mrf-approval-due" class="mrf-approval-due" role="status" aria-live="polite">Select an approval date and category to see the hiring due date.</p><p class="mrf-hint">Hiring due date = Date Approved + category days, including weekends and holidays.</p>
       <div class="f"><label for="mrf-approval-hr">Assigned HR <small>(optional)</small></label><select id="mrf-approval-hr"><option value="">Assign later / automatic</option></select><small class="mrf-hint">Applies to all headcount files in this request. If left unassigned, the first HR member to use a file in passed screening or start its recruitment becomes its owner. You can assign individual files later.</small></div>
      </fieldset>
      <div class="f"><label for="mrf-approval-notes">Notes / denial reason</label><textarea id="mrf-approval-notes" rows="3" maxlength="2000"></textarea></div>
      <label class="mrf-approval-check"><input id="mrf-approval-verified" type="checkbox">I have verified management approval from the delivered copy or confirmation.</label>
      <p id="mrf-approval-error" class="settings-error" role="alert" hidden></p>
      <div class="settings-actions"><button id="mrf-approval-cancel" class="btn g" type="button">Cancel</button><button id="mrf-approval-save" class="btn" type="submit">Confirm final decision</button></div>
     </form>
    </dialog>
    <?php
    }

    public static function renderMrfUpload(): void
    {
        ?>
    <dialog id="mrf-upload-dialog" aria-labelledby="mrf-upload-title">
     <div class="mrf-heading"><div><h2 id="mrf-upload-title">Upload manpower request</h2><p>Recognize, review and save an existing MRF.</p></div><button class="btn g" id="mrf-upload-close" type="button">Close</button></div>
     <form id="mrf-upload-file-form"><label for="mrf-upload-file">Completed Word or PDF request</label><input id="mrf-upload-file" type="file" accept=".docx,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required><p class="mrf-hint">Word .docx or searchable PDF · Up to 2 MB · Scanned PDFs need OCR first. Older .doc files must be saved as .docx in Word.</p><button class="btn" id="mrf-upload-recognize" type="submit">Recognize fields</button></form>
     <p id="mrf-upload-error" class="settings-error" role="alert" hidden></p>
     <a id="mrf-upload-existing" class="btn g" hidden>Open existing request</a>
     <div id="mrf-upload-discard-confirm" class="mrf-import-summary" hidden><strong>Discard this upload review?</strong><p>The request has not been saved. Closing will discard the recognized fields and your corrections.</p><div class="settings-actions"><button class="btn g" id="mrf-upload-keep" type="button">Keep reviewing</button><button class="btn" id="mrf-upload-discard" type="button">Discard review</button></div></div>
     <div id="mrf-upload-review" hidden>
      <div id="mrf-upload-number-conflict" class="mrf-import-summary mrf-number-conflict" role="alert" hidden><strong>MRF number already exists</strong><p id="mrf-upload-number-conflict-text"></p><a id="mrf-upload-number-conflict-link" class="btn g" target="_blank" rel="noopener">Check existing MRF</a><label class="mrf-approval-check"><input id="mrf-upload-confirm-new" type="checkbox">I checked the existing MRF. This is a different request and needs a new generated MRF number.</label></div>
      <div class="mrf-import-summary" role="status"><strong id="mrf-upload-summary"></strong><p>Recognition is a starting point, not a guarantee. Blank or uncertain answers are not guessed. The original document’s MRF number and date do not replace system-generated values.</p><ul id="mrf-upload-warnings"></ul></div>
      <details class="mrf-import-source"><summary>Extracted source text</summary><pre id="mrf-upload-source"></pre></details>
      <?php MrfView::renderMrfForm(true); ?>
     </div>
    </dialog>
    <?php
    }

    public static function renderMrfDetails(array $record): void
    {
        foreach (Mrf::mrfFields() as $section => $fields) {
            echo '<section class="card mrf-detail-section"><h2>'.Mrf::mrfEscape($section).'</h2><dl class="mrf-field-grid">';
            if ($section === 'Signatories') {
                echo '<div class="mrf-value"><dt>Prepared by</dt><dd>'.Mrf::mrfEscape($record['requestor_name']).'</dd></div>';
            }
            foreach ($fields as $key => $field) {
                if ($key === 'replacement_of' && ($record['form']['reason'] ?? '') !== 'Replacement') {
                    continue;
                }
                if (in_array($key, ['buddy_1_name', 'buddy_2_name'], true)) {
                    echo '<div class="mrf-buddy-row">';
                }
                echo '<div class="mrf-value '.($field['type'] === 'textarea' ? 'mrf-wide' : '').'"><dt>'.Mrf::mrfEscape($field['label']).'</dt><dd>'.nl2br(Mrf::mrfEscape($record['form'][$key] ?? '')).'</dd></div>';
                if (in_array($key, ['buddy_1_contact', 'buddy_2_contact'], true)) {
                    echo '</div>';
                }
            }
            echo '</dl></section>';
        }
    }

    public static function renderMrfPrint(array $record): void
    {
        $form = $record['form'];
        $cell = static fn (mixed $value) => nl2br(Mrf::mrfEscape($value));
        $join = static fn (array $values) => implode(' — ', array_filter($values, static fn ($value) => (string) $value !== ''));
        $row = static function (string $label, mixed $value) use ($cell): void {
            echo '<tr><th>'.Mrf::mrfEscape($label).'</th><td colspan="3">'.$cell($value).'</td></tr>';
        };
        $band = static function (string $label): void {
            echo '<h2 class="mrf-print-band">'.Mrf::mrfEscape($label).'</h2>';
        };
        $date = (new DateTimeImmutable($record['created_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('M d, Y h:i A');
        ?>
    <article class="mrf-paper">
     <div class="mrf-paper-logo"><?php Branding::renderCifBrandLogo(); ?></div>
     <?php $band('MANPOWER REQUEST FORM'); ?>
     <table class="mrf-paper-table mrf-paper-meta"><colgroup><col style="width:24%"><col style="width:32%"><col style="width:16%"><col style="width:28%"></colgroup>
      <tr><th>REQUESTOR’S NAME</th><td><?= $cell($form['requestor_name']) ?></td><th>DATE REQUEST</th><td><?= $cell($date) ?></td></tr>
      <tr><th>REQUESTOR’S POSITION</th><td><?= $cell($form['requestor_position']) ?></td><th>MRF NO.</th><td><?= $cell($record['mrf_number']) ?></td></tr>
     </table>
     <?php $band('REQUEST DETAILS'); ?>
     <table class="mrf-paper-table"><colgroup><col style="width:24%"><col style="width:32%"><col style="width:16%"><col style="width:28%"></colgroup>
      <?php $row('REQUEST', $join([$form['position_title'], $form['construction_phase']])); ?>
      <tr><th>SITE LOCATION</th><td><?= $cell($form['site_location']) ?></td><th>NO. OF REQUEST</th><td><?= $cell($form['headcount']) ?></td></tr>
      <?php $row('DETAILS', $join([$form['reason'], $form['reason'] === 'Replacement' ? $form['replacement_of'] : '', $form['reason_details']])); ?>
     </table>
     <?php $band('REQUEST DETAILS'); ?>
     <table class="mrf-paper-table">
      <?php $row('EMPLOYMENT STATUS', $form['employment_status']);
        $row('DEPARTMENT - GROUP', $join([$form['department_name'], $form['group_name']])); ?>
      <tr><th>QUALIFICATIONS</th><td colspan="3"><b>EDUCATION LEVEL:</b> <?= $cell($form['education_level']) ?><br><b>COURSE:</b> <?= $cell($form['preferred_course']) ?><br><b>YRS OF EXPERIENCE:</b> <?= $cell($form['years_experience']) ?><br><br><b>OTHER QUALIFICATIONS:</b><br><?= $cell($form['other_qualifications']) ?></td></tr>
      <?php $row('JOB ROLES', $form['job_roles']);
        $row('REMARKS', $form['remarks']); ?>
     </table>
     <?php $band('ONBOARDING DETAILS'); ?>
     <table class="mrf-paper-table"><?php foreach (Mrf::mrfFields()['Onboarding details'] as $key => $field) {
         $row(mb_strtoupper($field['label']), $form[$key]);
     } ?></table>
     <?php $band('SIGNATORIES'); ?>
     <table class="mrf-paper-table mrf-signatures"><colgroup><col style="width:24%"><col style="width:26%"><col style="width:24%"><col style="width:26%"></colgroup>
      <tr><th>PREPARED BY</th><td><?= $cell($form['requestor_name']) ?></td><th>REVIEWED BY<br><small>(REPORTING OFFICER / SPE)</small></th><td><?= $cell($form['reviewed_reporting']) ?></td></tr>
      <tr><th>REVIEWED BY<br><small>(PSD GROUP)</small></th><td><?= $cell($form['reviewed_psd']) ?></td><th>REVIEWED BY<br><small>(HOUSING SUPPORT GROUP)</small></th><td><?= $cell($form['reviewed_housing']) ?></td></tr>
      <tr><th>REVIEWED BY<br><small>(GROUP HEAD — if applicable)</small></th><td><?= $cell($form['reviewed_group']) ?></td><th>APPROVED BY<br><small>(DEPARTMENT HEAD)</small></th><td><?= $cell($form['approved_department']) ?><small class="mrf-approval-caption">ACC / AMC / MCC</small></td></tr>
     </table>
     <footer class="mrf-paper-footer">sbr ver 2025-1 · MRF <?= $cell($record['mrf_number']) ?></footer>
    </article>
    <?php
    }

    public static function renderRequestorCoverage(string $prefix): void
    {
        ?>
    <form id="<?= $prefix ?>-coverage-form" class="card hr-monitor-filters hr-kpi-filter-preview requestor-coverage requestor-coverage-<?= $prefix ?>">
     <label>From<input type="date" min="1900-01-01" max="9998-12-31" id="<?= $prefix ?>-date-from"></label>
     <label>To<input type="date" min="1900-01-01" max="9998-12-31" id="<?= $prefix ?>-date-to"></label>
     <?php if ($prefix === 'mrf') { ?><label>Hiring deadline<select id="mrf-deadline"><option value="">All deadlines</option><option value="overdue">Overdue</option><option value="upcoming">Due within 7 days</option><option value="outstanding">All outstanding</option></select></label><?php } ?>
     <div class="hr-kpi-filter-toolbar"><div class="hr-kpi-shortcuts" role="group" aria-label="Date coverage shortcuts">
      <?php foreach (['week' => 'This week', 'month' => 'This month', 'year' => 'This year', 'last12' => 'Last 12 months', 'all' => 'All dates'] as $key => $label) { ?><button type="button" data-coverage="<?= $key ?>"><?= $label ?></button><?php } ?>
     </div><div class="hr-kpi-filter-actions"><button type="button" class="btn g" id="<?= $prefix ?>-coverage-reset">Reset</button></div></div>
     <p id="<?= $prefix === 'dashboard' ? 'dashboard-coverage' : 'mrf-coverage-preview' ?>" class="hr-kpi-coverage-preview" role="status" aria-live="polite">All dates &middot; Date submitted &middot; Both dates included, in Philippine time. Results update automatically.</p>
    </form>
    <?php
    }
}
