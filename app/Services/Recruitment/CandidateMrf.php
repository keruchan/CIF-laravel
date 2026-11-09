<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class CandidateMrf
{
    public static function validateCandidateMrf(\PDO $pdo, mixed $selected, ?int $current, mixed $text = null, bool $required = true): ?int
    {
        if ($text !== null && (! is_string($text) || mb_strlen($text) > 150)) {
            throw new \InvalidArgumentException('Choose a registered MRF from the available options.');
        }
        if ($selected === null) {
            if (! $required && ($text === null || trim($text) === '')) {
                return null;
            }
            throw new \InvalidArgumentException('MRF is required. Choose an available registered MRF from the results before saving.');
        }
        if (! is_int($selected) || $selected < 1) {
            throw new \InvalidArgumentException('Choose a registered MRF from the search results.');
        }
        $query = $pdo->prepare("SELECT m.status, m.mrf_number, COALESCE(ap.decision, 'Pending') AS approval_status FROM manpower_requests m LEFT JOIN manpower_request_approvals ap ON ap.batch_number = m.batch_number WHERE m.id = ? FOR UPDATE");
        $query->execute([$selected]);
        $request = $query->fetch();
        if (! $request) {
            throw new \InvalidArgumentException('The selected MRF no longer exists. Choose another registered request.');
        }
        if ($request['approval_status'] !== 'Approved') {
            throw new \InvalidArgumentException('This MRF is not approved. HR must verify management approval before it can be selected or saved, including an existing assignment. Choose an approved request.');
        }
        if ($text !== null && mb_strtoupper(trim($text)) !== mb_strtoupper($request['mrf_number'])) {
            throw new \InvalidArgumentException('The entered MRF does not match the selected request. Choose an available option again.');
        }
        $status = $request['status'];
        if (in_array($status, ['On Hold', 'Cancelled'], true)) {
            throw new \InvalidArgumentException('This MRF is '.$status.' and cannot be used for recruitment, including an existing assignment. Choose another approved, available MRF.');
        }
        if ($selected !== $current && ! in_array($status, ['Pending', 'In Progress'], true)) {
            throw new \InvalidArgumentException('Only approved, Pending or In Progress MRFs can be selected. This request changed status; choose another available request.');
        }

        return $selected;
    }

    public static function renderCifMrfPicker(array $candidate, string $prefix = 'candidate-mrf', bool $required = true, string $scope = 'initial_interview'): void
    {
        $mrf = $candidate['mrf'] ?? null;
        ?>
    <div class="candidate-mrf-field" data-candidate-mrf data-mrf-prefix="<?php
        echo CandidateView::cifEscape($prefix);
        ?>" data-mrf-scope="<?php
        echo CandidateView::cifEscape($scope);
        ?>" data-mrf-required="<?php
        echo $required ? 'true' : 'false';
        ?>">
     <div class="candidate-mrf-label"><label for="<?php
        echo CandidateView::cifEscape($prefix);
        ?>-search">Manpower request (MRF) <?php
        if ($required) {
            ?><span id="<?php
            echo CandidateView::cifEscape($prefix);
            ?>-required" class="required-mark" aria-label="required">*</span><?php
        } else {
            ?><span class="optional-label">Optional</span><?php
        }
        ?></label><button id="<?php
        echo CandidateView::cifEscape($prefix);
        ?>-clear" type="button" class="draft-button">Clear selection</button></div>
     <div class="candidate-mrf-picker"><input id="<?php
        echo CandidateView::cifEscape($prefix);
        ?>-search" type="search"<?php
        echo $required ? ' required aria-required="true"' : '';
        ?> maxlength="150" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?php
        echo CandidateView::cifEscape($prefix);
        ?>-options" aria-describedby="<?php
        echo CandidateView::cifEscape($prefix);
        ?>-help <?php
        echo CandidateView::cifEscape($prefix);
        ?>-selected <?php
        echo CandidateView::cifEscape($prefix);
        ?>-error" placeholder="Search approved MRF number, position, department or site" value="<?php
        echo CandidateView::cifEscape($mrf['mrf_number'] ?? '');
        ?>"><input id="<?php
        echo CandidateView::cifEscape($prefix);
        ?>-id" type="hidden" value="<?php
        echo (int) ($mrf['id'] ?? 0) ?: '';
        ?>"><ul id="<?php
        echo CandidateView::cifEscape($prefix);
        ?>-options" role="listbox" aria-label="Approved and available manpower requests" hidden></ul></div>
     <p id="<?php
        echo CandidateView::cifEscape($prefix);
        ?>-selected" class="candidate-mrf-selected" aria-live="polite"><?php
        echo $mrf ? CandidateView::cifEscape($mrf['mrf_number'].' · '.$mrf['position_title'].' · '.$mrf['department_name'].' · '.$mrf['status']) : 'No MRF selected';
        ?></p>
     <p id="<?php
        echo CandidateView::cifEscape($prefix);
        ?>-error" class="candidate-mrf-error" role="alert" hidden></p>
     <p id="<?php
        echo CandidateView::cifEscape($prefix);
        ?>-help" class="editor-help"><?php
        echo $required ? 'Required for Save and Finish Interview.' : 'Optional during CIF screening. Your selection carries forward to Interview and can be changed there.';
        ?> Choose an Approved MRF with Pending or In Progress recruitment progress. Requests awaiting approval or Denied cannot be selected; typed text without a selection is invalid.</p>
    </div>
    <?php
    }

    public static function startCandidateMrf(\PDO $pdo, int $requestId, int $adminId, bool $claimOwner = true, string $source = 'started'): bool
    {
        // Run inside candidate_save's transaction, after all CIF/completion checks.
        // The selected MRF row is already locked by validateCandidateMrf().
        $query = $pdo->prepare("SELECT m.status, m.hr_notes, COALESCE(ap.decision, 'Pending') AS approval_status FROM manpower_requests m LEFT JOIN manpower_request_approvals ap ON ap.batch_number = m.batch_number WHERE m.id = ? FOR UPDATE");
        $query->execute([$requestId]);
        $request = $query->fetch();
        if (! $request || $request['approval_status'] !== 'Approved' || ! in_array($request['status'], ['Pending', 'In Progress'], true)) {
            return false;
        }
        if ($claimOwner) {
            MrfOwnership::cifAssignMrfHr($pdo, $requestId, $adminId, $adminId, $source, 'First qualifying recruitment use.', null, true);
        }
        if ($request['status'] !== 'Pending') {
            return false;
        }
        $query = $pdo->prepare("UPDATE manpower_requests SET status = 'In Progress', revision = revision + 1, updated_at = UTC_TIMESTAMP(), updated_by = ? WHERE id = ? AND status = 'Pending'");
        $query->execute([$adminId, $requestId]);
        $query = $pdo->prepare("INSERT INTO manpower_request_history (request_id, admin_id, previous_status, status, hr_notes, created_at) VALUES (?, ?, 'Pending', 'In Progress', ?, UTC_TIMESTAMP())");
        $query->execute([$requestId, $adminId, $request['hr_notes'] ?? '']);

        return true;
    }
}
