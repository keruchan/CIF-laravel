<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class Mrf
{
    public static function mrfEscape(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function mrfStatuses(): array
    {
        return ['Pending', 'In Progress', 'Completed', 'Rejected', 'On Hold', 'Cancelled'];
    }

    public static function mrfApprovalStatuses(): array
    {
        return ['Pending', 'Approved', 'Denied'];
    }

    public static function mrfFields(): array
    {
        $text = static fn (string $label, bool $required = false, int $max = 150) => ['label' => $label, 'type' => 'text', 'required' => $required, 'max' => $max];
        $long = static fn (string $label, bool $required = false) => ['label' => $label, 'type' => 'textarea', 'required' => $required, 'max' => 2000];
        $select = static fn (string $label, array $options, bool $required = false) => ['label' => $label, 'type' => 'select', 'options' => $options, 'required' => $required, 'max' => 150];

        return [
            'Requestor information' => [
                'requestor_name' => $text('Requestor’s name', true), 'requestor_position' => $text('Requestor’s position', true),
            ],
            'Request details' => [
                'position_title' => $text('Applied position', true), 'construction_phase' => $text('Construction phase'),
                'site_location' => $text('Site location', true, 250),
                'headcount' => ['label' => 'Headcount needed', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 999, 'step' => '1'],
                'reason' => $select('Reason for request', ['Additional manpower', 'Replacement', 'New position', 'Other'], true),
                'replacement_of' => $text('Replacement of'), 'reason_details' => $long('Request justification', true),
            ],
            'Employment & qualifications' => [
                'employment_status' => $select('Employment status', ['Probationary', 'Regular', 'Project-based', 'Fixed-term', 'Seasonal', 'Other'], true),
                'department_name' => $text('Department', true), 'group_name' => $text('Group'),
                'education_level' => $select('Education level', ['No specific requirement', 'Elementary', 'High school', 'Senior high school', 'Vocational / Technical', 'College undergraduate', 'Bachelor’s degree', 'Master’s degree', 'Doctorate', 'Other'], true),
                'preferred_course' => $text('Preferred course'),
                'years_experience' => ['label' => 'Required years of experience', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 60, 'step' => '0.5'],
                'other_qualifications' => $long('Other qualifications'), 'job_roles' => $long('Job roles', true), 'remarks' => $long('Remarks'),
            ],
            'Onboarding details' => [
                'computer' => $select('Computer / laptop', ['Not required', 'Desktop computer', 'Laptop', 'Either desktop or laptop'], true),
                'table_chair' => $select('Table & chair', ['Required', 'Not required'], true),
                'company_sim' => $select('Company SIM', ['Required', 'Not required'], true),
                'staff_house' => $select('Staff house / barracks', ['Required', 'Not required'], true),
                'vehicle' => $select('Vehicle (for drivers / HEO)', ['Required', 'Not required'], true), 'vehicle_unit' => $text('Vehicle unit number'),
                'buddy_1_name' => $text('Ka-APEC buddy 1 name'), 'buddy_1_contact' => ['label' => 'Ka-APEC buddy 1 contact number', 'type' => 'tel', 'required' => false, 'max' => 50],
                'buddy_2_name' => $text('Ka-APEC buddy 2 name'), 'buddy_2_contact' => ['label' => 'Ka-APEC buddy 2 contact number', 'type' => 'tel', 'required' => false, 'max' => 50],
            ],
            'Signatories' => [
                'reviewed_reporting' => $text('Reviewed by — Reporting officer / SPE'), 'reviewed_psd' => $text('Reviewed by — PSD group'),
                'reviewed_housing' => $text('Reviewed by — Housing support group'), 'reviewed_group' => $text('Reviewed by — Group head (if applicable)'),
                'approved_department' => $text('Approved by — Department head'),
            ],
        ];
    }

    public static function formatSavedMrf(array $input): array
    {
        foreach (Mrf::mrfFields() as $fields) {
            foreach ($fields as $key => $field) {
                if (is_string($input[$key] ?? null)) {
                    $input[$key] = TextFormat::cifSavedText($input[$key], $key, $field['type']);
                }
            }
        }

        return $input;
    }

    public static function validateMrf(array $input): array
    {
        foreach (['mrf_number', 'batch_number', 'headcount_index', 'legacy_mrf_number'] as $key) {
            if (array_key_exists($key, $input)) {
                throw new InvalidArgumentException('MRF numbers are generated automatically and cannot be entered or changed.');
            }
        }
        $input = Mrf::formatSavedMrf($input);
        $form = [];
        foreach (Mrf::mrfFields() as $fields) {
            foreach ($fields as $key => $field) {
                $value = $input[$key] ?? '';
                if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
                    throw new InvalidArgumentException('Invalid '.$field['label'].'.');
                }
                $value = trim((string) $value);
                if ($field['required'] && $value === '') {
                    throw new InvalidArgumentException('Please complete '.$field['label'].'.');
                }
                if ($field['type'] === 'number') {
                    if (! preg_match($key === 'headcount' ? '/^[0-9]+$/' : '/^[0-9]+(?:\.[0-9]+)?$/D', $value)) {
                        throw new InvalidArgumentException('Enter a valid '.$field['label'].'.');
                    }
                    $number = (float) $value;
                    if ($number < $field['min'] || $number > $field['max'] || ($key === 'years_experience' && fmod($number * 2, 1) !== 0.0)) {
                        throw new InvalidArgumentException($field['label'].' is outside the allowed range.');
                    }
                    $form[$key] = $key === 'headcount' ? (int) $value : $number;

                    continue;
                }
                if (mb_strlen($value) > $field['max'] || str_contains($value, "\0")) {
                    throw new InvalidArgumentException($field['label'].' is too long or invalid.');
                }
                if ($field['type'] === 'select' && $value !== '' && ! in_array($value, $field['options'], true)) {
                    throw new InvalidArgumentException('Select a valid '.$field['label'].'.');
                }
                if ($field['type'] === 'tel' && $value !== '') {
                    $value = CandidateStorage::cifContactNumber($value, $field['label']);
                }
                $form[$key] = $value;
            }
        }
        $form['position_title'] = HiringPositions::validateCifAppliedPosition($form['position_title']);
        if ($form['reason'] === 'Replacement' && $form['replacement_of'] === '') {
            throw new InvalidArgumentException('Enter the name / position being replaced.');
        }
        if ($form['reason'] !== 'Replacement') {
            $form['replacement_of'] = '';
        }
        foreach ([1, 2] as $index) {
            if (($form["buddy_{$index}_name"] === '') !== ($form["buddy_{$index}_contact"] === '')) {
                throw new InvalidArgumentException("Provide both the name and contact number for ka-buddy $index, or leave both blank.");
            }
        }

        return $form;
    }

    public static function loadMrf(PDO $pdo, int $id, ?int $owner = null): ?array
    {

        $query = $pdo->prepare("SELECT m.*, d.username AS owner_username, COALESCE(ap.decision, 'Pending') AS approval_status, COALESCE(ap.revision, 0) AS approval_revision, ap.notes AS approval_notes, ap.date_approved, ap.position_category, ap.lead_time_days, ap.hiring_due_date, ap.updated_at AS approval_updated_at, a.name AS approval_admin FROM manpower_requests m JOIN department_users d ON d.id = m.department_user_id LEFT JOIN manpower_request_approvals ap ON ap.batch_number = m.batch_number LEFT JOIN admins a ON a.id = ap.updated_by WHERE m.id = ?".($owner !== null ? ' AND m.department_user_id = ?' : ''));
        $query->execute($owner !== null ? [$id, $owner] : [$id]);
        $record = $query->fetch();
        if (! $record) {
            return null;
        }
        $record['form'] = json_decode($record['form_data'], true, 32, JSON_THROW_ON_ERROR);
        unset($record['form_data'], $record['submission_key']);
        $query = $pdo->prepare('SELECT id, mrf_number, headcount_index, status FROM manpower_requests WHERE batch_number = ? AND department_user_id = ? ORDER BY headcount_index');
        $query->execute([$record['batch_number'], $record['department_user_id']]);
        $record['files'] = $query->fetchAll();
        $query = $pdo->prepare('SELECT h.previous_status, h.status, h.hr_notes, h.created_at, a.name AS admin_name FROM manpower_request_history h JOIN admins a ON a.id = h.admin_id WHERE h.request_id = ? ORDER BY h.id DESC');
        $query->execute([$id]);
        $record['history'] = $query->fetchAll();
        $query = $pdo->prepare('SELECT i.original_filename, i.file_size, i.imported_at, a.name AS imported_by FROM manpower_request_imports i JOIN admins a ON a.id = i.admin_id JOIN manpower_requests origin ON origin.id = i.request_id WHERE origin.batch_number = ? AND origin.headcount_index = 1');
        $query->execute([$record['batch_number']]);
        $record['import'] = $query->fetch() ?: null;
        $query = $pdo->prepare('SELECT h.*, a.name AS admin_name FROM manpower_request_approval_history h JOIN admins a ON a.id = h.admin_id WHERE h.batch_number = ? ORDER BY h.id DESC');
        $query->execute([$record['batch_number']]);
        $record['approval_history'] = $query->fetchAll();
        $record['hr_assignment'] = MrfOwnership::cifMrfHrAssignment($pdo, $id);
        $record['hr_staff_options'] = $owner === null ? MrfOwnership::cifMrfHrStaff($pdo) : [];

        return $record;
    }

    public static function submitMrf(PDO $pdo, int $owner, array $form, string $submissionKey, ?array $import = null): array
    {
        $pdo->beginTransaction();
        try {
            // Lock the submitting account: serializes retries and double taps, even
            // before a submission exists. The annual counter serializes all accounts.
            $query = $pdo->prepare('SELECT id, name, position, department_name, group_name, account_status, archived_at FROM department_users WHERE id = ? FOR UPDATE');
            $query->execute([$owner]);
            $account = $query->fetch();
            if (! $account) {
                throw new RuntimeException('MRF requestor account not found.');
            }
            if (! DepartmentAccount::cifRequestorCanSignIn($account)) {
                throw new InvalidArgumentException('This requestor account is suspended or archived. Contact HR.');
            }
            $query = $pdo->prepare('SELECT id, form_data FROM manpower_requests WHERE department_user_id = ? AND submission_key = ?');
            $query->execute([$owner, $submissionKey]);
            $existing = $query->fetch();
            // These values are authoritative account details, never client-supplied.
            // Retried submissions keep their original snapshot even if the account changed.
            $identity = $existing ? json_decode($existing['form_data'], true, 32, JSON_THROW_ON_ERROR) : ($import !== null ? $form : [
                'requestor_name' => $account['name'], 'requestor_position' => $account['position'], 'department_name' => $account['department_name'], 'group_name' => $account['group_name'],
            ]);
            foreach (['requestor_name', 'requestor_position', 'department_name', 'group_name'] as $field) {
                if ($field !== 'group_name' && $import === null && trim((string) ($identity[$field] ?? '')) === '') {
                    throw new InvalidArgumentException('Your requestor account needs a name, position and department. Contact HR to configure your account before submitting.');
                }
                $form[$field] = $identity[$field] ?? '';
            }
            $form = Mrf::validateMrf($form);
            if ($existing) {
                if (Mrf::formatSavedMrf(json_decode($existing['form_data'], true, 32, JSON_THROW_ON_ERROR)) != $form) {
                    throw new InvalidArgumentException('This submission was already sent with different details. Start a new request.');
                }
                $pdo->commit();

                return Mrf::loadMrf($pdo, (int) $existing['id'], $owner);
            }
            if ($import !== null) {
                // The import endpoint constructs this metadata from the authenticated
                // admin's staged upload; it is never accepted from a request payload.
                $query = $pdo->prepare('SELECT request_id FROM manpower_request_imports WHERE file_sha256 = ?');
                $query->execute([$import['sha256']]);
                if ($query->fetchColumn()) {
                    throw new InvalidArgumentException('This document has already been imported. Open the existing request instead.');
                }
            }
            $year = (int) date('Y');
            $query = $pdo->prepare('INSERT INTO mrf_counters (request_year, last_number) VALUES (?, 0) ON DUPLICATE KEY UPDATE request_year = VALUES(request_year)');
            $query->execute([$year]);
            $query = $pdo->prepare('SELECT last_number FROM mrf_counters WHERE request_year = ? FOR UPDATE');
            $query->execute([$year]);
            $lastNumber = (int) $query->fetchColumn();
            // Keep numbering unique even when an older backup left the counter behind.
            $query = $pdo->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(batch_number, ?, -1) AS UNSIGNED)), 0) FROM manpower_requests WHERE batch_number LIKE ?');
            $query->execute(['-', $year.'-%']);
            $next = max($lastNumber, (int) $query->fetchColumn()) + 1;
            $batch = MrfHeadcounts::mrfBatchNumber($year, $next);
            $query = $pdo->prepare('UPDATE mrf_counters SET last_number = ? WHERE request_year = ?');
            $query->execute([$next, $year]);
            $query = $pdo->prepare('INSERT INTO manpower_requests (mrf_number, batch_number, headcount_index, department_user_id, requestor_name, requestor_position, position_title, department_name, site_location, headcount, form_data, submission_key, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $id = 0;
            $json = json_encode($form, JSON_THROW_ON_ERROR);
            $timestamp = gmdate('Y-m-d H:i:s');
            for ($index = 1; $index <= $form['headcount']; $index++) {
                $key = $index === 1 ? $submissionKey : hash('sha256', $submissionKey.':headcount:'.$index);
                $query->execute([MrfHeadcounts::mrfHeadcountNumber($batch, $index), $batch, $index, $owner, $form['requestor_name'], $form['requestor_position'], $form['position_title'], $form['department_name'], $form['site_location'], $form['headcount'], $json, $key, $timestamp, $timestamp]);
                if ($index === 1) {
                    $id = (int) $pdo->lastInsertId();
                }
            }
            $approval = $pdo->prepare('INSERT INTO manpower_request_approvals (batch_number, request_id) VALUES (?, ?)');
            $approval->execute([$batch, $id]);
            if ($import !== null) {
                $query = $pdo->prepare('INSERT INTO manpower_request_imports (request_id, admin_id, original_filename, mime_type, file_size, file_sha256, original_file, extracted_text, imported_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())');
                $query->execute([$id, $import['admin_id'], $import['filename'], $import['mime'], strlen($import['bytes']), $import['sha256'], $import['bytes'], $import['text']]);
            }
            $pdo->commit();

            return Mrf::loadMrf($pdo, $id, $owner);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($import !== null && $e instanceof PDOException && $e->getCode() === '23000') {
                $query = $pdo->prepare('SELECT request_id FROM manpower_request_imports WHERE file_sha256 = ?');
                $query->execute([$import['sha256']]);
                if ($query->fetchColumn()) {
                    throw new InvalidArgumentException('This document has already been imported. Open the existing request instead.');
                }
            }
            throw $e;
        }
    }
}
