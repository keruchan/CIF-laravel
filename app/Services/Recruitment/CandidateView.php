<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class CandidateView
{
    public static function loadCifCandidate(PDO $pdo, int $id): ?array
    {
        $query = $pdo->prepare('SELECT * FROM candidates WHERE id = ?');
        $query->execute([$id]);
        $candidate = $query->fetch();
        if (! $candidate) {
            return null;
        }
        $form = json_decode($candidate['form_data'], true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($form)) {
            $form = [];
        }
        $query = $pdo->prepare('SELECT * FROM candidate_details WHERE candidate_id = ?');
        $query->execute([$id]);
        $details = $query->fetch();
        if ($details) {
            foreach (CifFields::cifScalarFields() as $name => $field) {
                $form[$name] = $details[$name] ?? ($form[$name] ?? ($name === 'contact_email' ? $candidate['email'] : ''));
            }
            $form['consent'] = (bool) $details['certification_accepted'];
            $form['background_consent'] = (bool) $details['background_consent_accepted'];
            $form['accepted_at'] = $details['accepted_at'];
            $form['form_version'] = $details['form_version'];
            foreach (CifFields::cifDefinition()['groups'] as $group => $definition) {
                $query = $pdo->prepare('SELECT * FROM candidate_'.$group.' WHERE candidate_id = ? ORDER BY record_number');
                $query->execute([$id]);
                $form[$group] = $query->fetchAll();
            }
        }
        // Legacy submissions remain readable even without newer structured details.
        $form['fn'] = $candidate['first_name'];
        $form['mn'] = $candidate['middle_name'];
        $form['ln'] = $candidate['last_name'];
        $form['desired_position'] = $candidate['position'];
        if (empty($form['employment']) && ! empty($form['emr'])) {
            $form['employment'] = [['company_name' => $form['emr']]];
        }
        $candidate['form'] = $form;
        $candidate['hr'] = [];
        $candidate['revision'] = 0;
        $candidate['mrf'] = null;
        $query = $pdo->prepare('SELECT r.*, a.name AS editor FROM candidate_admin_revisions r JOIN admins a ON a.id = r.admin_id WHERE r.candidate_id = ? ORDER BY r.id DESC LIMIT 1');
        $query->execute([$id]);
        if ($revision = $query->fetch()) {
            $candidate['form'] = json_decode($revision['form_data'], true, 512, JSON_THROW_ON_ERROR);
            $candidate['hr'] = json_decode($revision['hr_data'], true, 512, JSON_THROW_ON_ERROR);
            $candidate['revision'] = (int) $revision['id'];
            $candidate['edited_at'] = $revision['created_at'];
            $candidate['editor'] = $revision['editor'];
            $query = $pdo->prepare('SELECT m.id, m.mrf_number, m.position_title, m.department_name, m.site_location, m.status FROM candidate_mrf_assignments l JOIN manpower_requests m ON m.id = l.request_id WHERE l.revision_id = ? AND l.candidate_id = ?');
            $query->execute([$candidate['revision'], $id]);
            $candidate['mrf'] = $query->fetch() ?: null;
        }
        if (! array_key_exists('contact_email', $candidate['form']) || $candidate['form']['contact_email'] === null) {
            $candidate['form']['contact_email'] = $candidate['email'];
        }
        $query = $pdo->prepare('SELECT s.*, a.name AS editor FROM candidate_interview_stages s JOIN admins a ON a.id = s.updated_by WHERE s.candidate_id = ?');
        $query->execute([$id]);
        $candidate['interviews'] = [];
        foreach ($query->fetchAll() as $stage) {
            $stage['revision'] = (int) $stage['revision'];
            $candidate['interviews'][$stage['stage']] = $stage;
        }
        $query = $pdo->prepare('SELECT r.*, a.name AS editor FROM candidate_assessment_requirements r JOIN admins a ON a.id = r.updated_by WHERE r.candidate_id = ?');
        $query->execute([$id]);
        if ($requirements = $query->fetch()) {
            $requirements['department_required'] = (bool) $requirements['department_required'];
            $requirements['ldt_required'] = (bool) $requirements['ldt_required'];
            $requirements['revision'] = (int) $requirements['revision'];
            $candidate['assessment_requirements'] = $requirements;
        }
        $query = $pdo->prepare('SELECT d.*, m.mrf_number, a.name AS editor FROM candidate_deployments d JOIN manpower_requests m ON m.id = d.request_id JOIN admins a ON a.id = d.updated_by WHERE d.candidate_id = ?');
        $query->execute([$id]);
        if ($deployment = $query->fetch()) {
            $deployment['revision'] = (int) $deployment['revision'];
            $deployment['request_id'] = (int) $deployment['request_id'];
            $candidate['deployment'] = $deployment;
        }
        $candidate['status'] = CandidateStatuses::cifAutomaticCandidateStatus($candidate);

        return $candidate;
    }

    public static function cifEscape(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function cifDisplay(mixed $value): string
    {
        return $value === null || $value === '' ? 'Not provided' : (string) $value;
    }

    public static function cifLocalDate(?string $date): string
    {
        return $date ? (new DateTimeImmutable($date, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('M j, Y g:i A') : 'Not recorded';
    }

    public static function renderCifCandidate(array $candidate): void
    {
        $form = $candidate['form'];
        $definition = CifFields::cifDefinition();
        $entry = static function (string $label, mixed $value): void {
            echo '<div class="cif-field"><dt>'.CandidateView::cifEscape($label).'</dt><dd>'.nl2br(CandidateView::cifEscape(CandidateView::cifDisplay($value))).'</dd></div>';
        };
        echo '<article class="cif-document"><div class="cif-heading"><h1>Candidate Information Form</h1><p>APEC Homes · REC-REV-24-03 SBR</p></div><dl class="cif-grid">';
        if (! empty($form['demo_record'])) {
            echo '<div class="cif-field"><dt>Demo record</dt><dd>Fictional candidate and contact information. Declarations are simulated for demonstration only.</dd></div>';
        }
        foreach (['Applicant ID' => $candidate['candidate_number'], 'Date applied' => CandidateView::cifLocalDate($candidate['submitted_at']), 'Candidate email' => $candidate['email'], 'Application status' => CandidateStatuses::cifCandidateStatusLabel($candidate['status']), 'Archived' => $candidate['archived_at'] ? CandidateView::cifLocalDate($candidate['archived_at']) : 'No'] as $label => $value) {
            $entry($label, $value);
        }
        echo '</dl>';
        foreach ($definition['sections'] as $section => $fields) {
            echo '<section class="cif-section"><h2>'.CandidateView::cifEscape($section).'</h2><dl class="cif-grid">';
            foreach ($fields as $name => $field) {
                if ($name === 'yr') {
                    continue;
                }
                if ($field['type'] === 'photo') {
                    echo '<div class="cif-field"><dt>'.CandidateView::cifEscape($field['label']).'</dt><dd>';
                    $photo = CifPhoto::cifPhotoSource($form[$name] ?? '');
                    echo $photo !== '' ? '<img src="'.CandidateView::cifEscape($photo).'" alt="Candidate upper-body ID picture" width="120">' : 'Not provided';
                    echo '</dd></div>';
                } else {
                    $entry($field['label'], $form[$name] ?? null);
                }
            }
            echo '</dl>';
            foreach ($definition['groups'] as $group => $record) {
                if ($record['section'] !== $section) {
                    continue;
                }
                echo '<h3>'.CandidateView::cifEscape($record['label']).'</h3>';
                $rows = is_array($form[$group] ?? null) ? $form[$group] : [];
                if (! $rows) {
                    echo '<p class="cif-muted">No records provided.</p>';
                }
                foreach ($rows as $index => $row) {
                    echo '<div class="cif-record"><h4>Record '.($index + 1).'</h4><dl class="cif-grid">';
                    foreach ($record['fields'] as $name => $field) {
                        $entry($field['label'], $row[$name] ?? null);
                    }
                    echo '</dl></div>';
                }
            }
            if ($section === 'Certification & consent') {
                echo '<dl class="cif-grid">';
                $entry('Certification of information', ! empty($form['consent']) ? 'Accepted' : 'Not recorded');
                $entry('Background check consent', ! empty($form['background_consent']) ? 'Accepted' : 'Not recorded');
                if (array_key_exists('data_privacy_consent', $form)) {
                    $entry('Data Privacy Consent', $form['data_privacy_consent'] ? 'Accepted' : 'Not accepted');
                    if (array_key_exists('future_opportunities_consent', $form)) {
                        $entry('Future Career Opportunities (historical)', $form['future_opportunities_consent'] ? 'Agreed — up to two (2) years' : 'Not agreed');
                    }
                }
                $entry('Acceptance time', CandidateView::cifLocalDate($form['accepted_at'] ?? null));
                $entry('Form version', $form['form_version'] ?? 'Legacy submission');
                echo '</dl><p class="cif-muted">Typed acknowledgements are recorded above; handwritten signature images were not collected. HR-only assessments are not part of the candidate submission.</p>';
            }
            echo '</section>';
        }
        echo '</article>';
    }
}
