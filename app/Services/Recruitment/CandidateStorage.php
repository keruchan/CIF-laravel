<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use DateTimeImmutable;

final class CandidateStorage
{
    // Candidate phone inputs have a fixed country prefix. Store a complete number,
    // accepting equivalent local/full-number pastes without changing old HR records.
    public static function cifContactNumber(mixed $value, string $label): string
    {
        if (! is_string($value)) {
            throw new \InvalidArgumentException('Enter a valid '.$label.'.');
        }
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (strlen($value) > 50 || ! preg_match('/^\+?[0-9\s()\-]+$/D', $value)) {
            throw new \InvalidArgumentException('Enter a numeric '.$label.' with 10 digits after +63.');
        }
        $digits = preg_replace('/\D/', '', $value);
        if (str_starts_with($value, '+') && ! (strlen($digits) === 12 && str_starts_with($digits, '63'))) {
            throw new \InvalidArgumentException('Use the fixed +63 prefix for '.$label.'.');
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (! preg_match('/^[1-9][0-9]{9}$/D', $digits)) {
            throw new \InvalidArgumentException('Enter 10 digits after +63 for '.$label.'.');
        }

        return '+63-'.substr($digits, 0, 3).'-'.substr($digits, 3, 3).'-'.substr($digits, 6);
    }

    public static function cifValue(mixed $value, array $field, ?string $existing = null, string $key = ''): string
    {
        if (! is_string($value)) {
            throw new \InvalidArgumentException('Enter a valid '.$field['label'].'.');
        }
        $value = TextFormat::cifSavedText(trim($value), $key, $field['type']);
        if (mb_strlen($value) > $field['max'] || $field['required'] && $value === '') {
            throw new \InvalidArgumentException($field['label'].' is required or exceeds '.$field['max'].' characters.');
        }
        if ($value === '') {
            return '';
        }
        $type = $field['type'];
        if ($type === 'select' && ! in_array($value, $field['options'], true) && $value !== $existing) {
            throw new \InvalidArgumentException('Select a valid '.$field['label'].'.');
        }
        if ($type === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Enter a valid email address.');
        }
        if ($type === 'photo') {
            if (! preg_match('/^data:image\/(jpeg|png|webp);base64,([A-Za-z0-9+\/=]+)$/D', $value, $match)) {
                throw new \InvalidArgumentException('Choose a JPEG, PNG or WebP ID picture.');
            }
            $bytes = base64_decode($match[2], true);
            $image = $bytes !== false ? @getimagesizefromstring($bytes) : false;
            $mime = $bytes !== false ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) : '';
            if ($bytes === false || strlen($bytes) > 2 * 1024 * 1024 || ! $image || $image[0] > 4096 || $image[1] > 4096 || $image[0] * $image[1] > 12000000 || ($image['mime'] ?? '') !== 'image/'.$match[1] || $mime !== $image['mime']) {
                throw new \InvalidArgumentException('Use a valid ID picture under 2 MB and no larger than 4096 pixels per side.');
            }
        }
        if (in_array($type, ['money', 'measurement', 'rating'], true)) {
            $max = $type === 'rating' ? 100 : ($type === 'measurement' ? 1000 : 9999999999.99);
            if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $value) || (float) $value > $max || $type === 'measurement' && (float) $value <= 0) {
                throw new \InvalidArgumentException('Enter a valid '.$field['label'].'.');
            }
        }
        if ($type === 'year' && (! preg_match('/^\d{4}$/D', $value) || (int) $value < 1900 || (int) $value > (int) date('Y'))) {
            throw new \InvalidArgumentException('Enter a valid '.$field['label'].'.');
        }
        if ($type === 'year_range') {
            CifEducation::validateCifEducationYears($value, $field['required'], $existing);
        }
        if ($type === 'date') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (! $date || $date->format('Y-m-d') !== $value || $date > new DateTimeImmutable('today')) {
                throw new \InvalidArgumentException('Enter a valid '.$field['label'].'.');
            }
        }
        if ($type === 'month' && (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $value) || $value < '1900-01' || $value > date('Y-m'))) {
            throw new \InvalidArgumentException('Enter a valid '.$field['label'].'.');
        }

        return $value;
    }

    public static function validateCifInformation(array $input, bool $submission = true, array $existing = []): array
    {
        $form = [];
        foreach (CifFields::cifScalarFields() as $name => $field) {
            if (! $submission) {
                $field['required'] = false;
            }
            $value = $input[$name] ?? '';
            if ($submission && $field['type'] === 'tel') {
                $value = CandidateStorage::cifContactNumber($value, $field['label']);
            }
            if ($name === 'permanent_address' && $submission && ($input['same_as_present_address'] ?? false) === true) {
                $value = $input['adr'] ?? '';
            }
            $form[$name] = CandidateStorage::cifValue($value, $field, ! $submission ? (string) ($existing[$name] ?? '') : null, $name);
        }
        foreach (CifFields::cifDefinition()['groups'] as $group => $definition) {
            $rows = $input[$group] ?? [];
            if (! is_array($rows) || ! array_is_list($rows) || count($rows) > $definition['max']) {
                throw new \InvalidArgumentException('Invalid '.$definition['label'].' records.');
            }
            $form[$group] = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new \InvalidArgumentException('Invalid '.$definition['label'].' record.');
                }
                $record = [];
                foreach ($definition['fields'] as $key => $field) {
                    $value = $row[$key] ?? '';
                    if ($submission && $field['type'] === 'tel') {
                        $value = CandidateStorage::cifContactNumber($value, $field['label']);
                    }
                    $record[$key] = CandidateStorage::cifValue($value, $field, null, $key === 'position_title' ? 'previous_position_title' : $key);
                }
                if (! array_filter($record, static fn ($value) => $value !== '')) {
                    continue;
                }
                if ($submission && $record[$definition['anchor']] === '') {
                    throw new \InvalidArgumentException('Enter '.$definition['fields'][$definition['anchor']]['label'].' for each completed '.$definition['label'].' record.');
                }
                $form[$group][] = $record;
            }
        }

        return $form;
    }

    public static function validateCif(array $input): array
    {
        $form = CandidateStorage::validateCifInformation($input);
        if (($input['consent'] ?? false) !== true || ($input['background_consent'] ?? false) !== true) {
            throw new \InvalidArgumentException('Accept both the certification of information and background check consent.');
        }
        $form['consent'] = true;
        $form['background_consent'] = true;
        $form['form_version'] = 'REC-REV-24-03 SBR:web-v2';
        $form['accepted_at'] = gmdate('Y-m-d H:i:s');

        return $form;
    }

    // The public submission route always validates the individual address parts.
    // Internal legacy/demo imports can still use validateCif with the original fields.
    public static function validateCandidateCif(array $input): array
    {
        $input['desired_position'] = HiringPositions::validateCifAppliedPosition($input['desired_position'] ?? '');
        $addresses = CandidateAddress::cifCandidateAddresses($input);
        $input['adr'] = CandidateAddress::cifCandidateAddressLine($addresses['present']);
        $input['permanent_address'] = CandidateAddress::cifCandidateAddressLine($addresses['permanent']);
        $input['emergency_address'] = CandidateAddress::cifCandidateAddressLine($addresses['emergency']);
        $input['city'] = $addresses['present']['municipality'];
        $input['prov'] = $addresses['present']['province'];
        $input['zip'] = '';
        $form = CandidateStorage::validateCif($input);
        // Preserve the individual answers in the submission's database JSON snapshot;
        // existing admin, analytics and print views consume the combined fields above.
        $form['residential_addresses'] = $addresses;
        $form['form_version'] = 'REC-REV-24-03 SBR:web-v3';

        return $form;
    }

    public static function cifSqlType(array $field): string
    {
        return match ($field['type']) {
            'photo' => 'MEDIUMTEXT',
            'date' => 'DATE',
            'month' => 'CHAR(7)',
            'year' => 'SMALLINT UNSIGNED',
            'money' => 'DECIMAL(12,2)',
            'rating', 'measurement' => 'DECIMAL(6,2)',
            default => $field['max'] > 150 ? 'TEXT' : 'VARCHAR('.$field['max'].')',
        };
    }

    public static function saveCifDetails(\PDO $pdo, int $candidateId, array $form): void
    {
        $details = ['candidate_id' => $candidateId];
        foreach (CifFields::cifScalarFields() as $name => $field) {
            $details[$name] = $form[$name] === '' ? null : $form[$name];
        }
        $details += ['certification_accepted' => 1, 'background_consent_accepted' => 1, 'accepted_at' => $form['accepted_at'], 'form_version' => $form['form_version']];
        $insert = static function (string $table, array $values) use ($pdo): void {
            $columns = implode(', ', array_map(static fn ($key) => '`'.$key.'`', array_keys($values)));
            $query = $pdo->prepare('INSERT INTO `'.$table.'` ('.$columns.') VALUES ('.implode(', ', array_fill(0, count($values), '?')).')');
            $query->execute(array_values($values));
        };
        $insert('candidate_details', $details);
        foreach (CifFields::cifDefinition()['groups'] as $group => $definition) {
            foreach ($form[$group] as $index => $row) {
                $values = ['candidate_id' => $candidateId, 'record_number' => $index + 1];
                foreach ($row as $key => $value) {
                    $values[$key] = $value === '' ? null : $value;
                }
                $insert('candidate_'.$group, $values);
            }
        }
    }
}
