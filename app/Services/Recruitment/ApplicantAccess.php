<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use DateTimeImmutable;
use DateTimeZone;

final class ApplicantAccess
{
    public const CIF_INVITATION_DAYS = 10;

    public static function cifInvitationStatusSql(): string
    {
        // CASE literals and native prepared parameters may inherit different
        // collations on hosting. Set the computed result's collation explicitly.
        return "(CASE WHEN submitted_at IS NOT NULL THEN 'Submitted' WHEN redeemed_at IS NOT NULL THEN 'In Progress' WHEN expires_at <= UTC_TIMESTAMP() THEN 'Expired' ELSE 'Pending' END) COLLATE utf8mb4_unicode_ci";
    }

    public static function normalizeCifInvitationCode(mixed $value): string
    {
        return is_string($value) ? strtoupper(preg_replace('/[\s-]+/', '', trim($value))) : '';
    }

    public static function generateCifInvitationCode(\PDO $pdo): string
    {
        if (! $pdo->inTransaction()) {
            throw new \LogicException('Invitation codes need a transaction.');
        }
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $exists = $pdo->prepare('SELECT 1 FROM invitations WHERE code_hash = ?');
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $exists->execute([hash('sha256', $code)]);
        } while ($exists->fetchColumn());

        return $code;
    }

    public static function cifInvitationExpiry(?DateTimeImmutable $created = null): string
    {
        return ($created ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+'.ApplicantAccess::CIF_INVITATION_DAYS.' days')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function cifApplicantName(mixed $value, string $label): string
    {
        $name = is_string($value) ? trim($value) : '';
        if ($name === '' || mb_strlen($name) > 100 || ! preg_match('/\p{L}/u', $name)) {
            throw new \InvalidArgumentException('Enter a valid '.$label.' of up to 100 characters.');
        }

        return $name;
    }

    public static function cifApplicantPrefix(string $firstName, string $lastName, ?int $year = null): string
    {
        $initials = static function (string $name): string {
            $latin = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
            $letters = preg_replace('/[^A-Z]/', '', strtoupper($latin ?: ''));
            if ($letters === '') {
                $letters = mb_strtoupper(preg_replace('/[^\p{L}]/u', '', $name));
            }

            return mb_substr($letters.'XX', 0, 2);
        };

        return $initials($lastName).$initials($firstName).substr((string) ($year ?? (int) date('Y')), -2);
    }

    // Call inside the same transaction as the invitation insert/update. A locked
    // counter per initials/year prevents duplicates and never reuses issued numbers.
    public static function allocateCifApplicantId(\PDO $pdo, string $firstName, string $lastName, ?int $issuedYear = null): string
    {
        if (! $pdo->inTransaction()) {
            throw new \LogicException('Applicant IDs need a transaction.');
        }
        // Historical CLI fixtures may supply their actual submission year. Live
        // submission always omits this argument and uses the current local year.
        if ($issuedYear !== null && ($issuedYear < 2000 || $issuedYear > (int) date('Y'))) {
            throw new \InvalidArgumentException('Invalid applicant ID issuance year.');
        }
        $prefix = ApplicantAccess::cifApplicantPrefix($firstName, $lastName, $issuedYear);
        $query = $pdo->prepare('INSERT IGNORE INTO applicant_id_counters (prefix, last_number) VALUES (?, 0)');
        $query->execute([$prefix]);
        $query = $pdo->prepare('SELECT last_number FROM applicant_id_counters WHERE prefix = ? FOR UPDATE');
        $query->execute([$prefix]);
        $number = (int) $query->fetchColumn();
        $exists = $pdo->prepare('SELECT 1 FROM invitations WHERE applicant_id = ? UNION ALL SELECT 1 FROM candidates WHERE candidate_number = ? LIMIT 1');
        do {
            $id = $prefix.str_pad((string) ++$number, 2, '0', STR_PAD_LEFT);
            if (mb_strlen($id) > 32) {
                throw new \RuntimeException('Applicant ID counter exhausted.');
            }
            $exists->execute([$id, $id]);
        } while ($exists->fetchColumn());
        $query = $pdo->prepare('UPDATE applicant_id_counters SET last_number = ? WHERE prefix = ?');
        $query->execute([$number, $prefix]);

        return $id;
    }

    public static function assignCifApplicantId(\PDO $pdo, int $invitationId, string $firstName, string $lastName, ?int $submittedYear = null): string
    {
        $query = $pdo->prepare('SELECT applicant_id FROM invitations WHERE id = ? FOR UPDATE');
        $query->execute([$invitationId]);
        $row = $query->fetch();
        if (! $row) {
            throw new \InvalidArgumentException('Invitation not found.');
        }
        if ($row['applicant_id']) {
            return $row['applicant_id'];
        }
        $id = ApplicantAccess::allocateCifApplicantId($pdo, $firstName, $lastName, $submittedYear);
        $query = $pdo->prepare('UPDATE invitations SET applicant_id = ?, first_name = ?, last_name = ? WHERE id = ?');
        $query->execute([$id, $firstName, $lastName, $invitationId]);
        $query = $pdo->prepare('UPDATE candidates SET legacy_candidate_number = COALESCE(legacy_candidate_number, candidate_number), candidate_number = ? WHERE invitation_id = ?');
        $query->execute([$id, $invitationId]);

        return $id;
    }

    // Partial drafts use the same shape/limits as CIF data without requiring answers,
    // identifying fields or consent. Unknown fields and all HR-only data are dropped.
    public static function validateCifDraft(array $input): array
    {
        // Preserve unfinished text (including an email being typed) rather than
        // applying final-submission format checks to an incomplete draft.
        $value = static function (mixed $value, array $field, string $key = ''): string {
            if (! is_string($value) || mb_strlen($value) > $field['max'] || str_contains($value, "\x00")) {
                throw new \InvalidArgumentException('Invalid '.$field['label'].'.');
            }
            if ($field['type'] === 'photo' || $field['type'] === 'select') {
                $field['required'] = false;

                return CandidateStorage::cifValue($value, $field, null, $key);
            }
            $value = TextFormat::cifSavedText($value, $key, $field['type']);
            if (mb_strlen($value) > $field['max']) {
                throw new \InvalidArgumentException($field['label'].' exceeds its maximum length after formatting.');
            }

            return $value;
        };
        $form = [];
        foreach (CifFields::cifScalarFields() as $name => $field) {
            $form[$name] = $value($input[$name] ?? '', $field, $name);
        }
        foreach (CifFields::cifDefinition()['groups'] as $group => $definition) {
            $rows = $input[$group] ?? [];
            if (! is_array($rows) || ! array_is_list($rows) || count($rows) > $definition['max']) {
                throw new \InvalidArgumentException('Invalid '.$definition['label'].' records.');
            }
            $form[$group] = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new \InvalidArgumentException('Invalid record.');
                }
                $record = [];
                foreach ($definition['fields'] as $name => $field) {
                    $record[$name] = $value($row[$name] ?? '', $field, $name === 'position_title' ? 'previous_position_title' : $name);
                }
                // Keep row indices, even for blanks, so resumed answers stay in place.
                $form[$group][] = $record;
            }
        }
        foreach (['consent', 'background_consent'] as $name) {
            if (isset($input[$name]) && ! is_bool($input[$name])) {
                throw new \InvalidArgumentException('Invalid consent value.');
            }
            $form[$name] = $input[$name] ?? false;
        }
        foreach (['present', 'permanent', 'emergency'] as $group) {
            foreach (CandidateAddress::cifCandidateAddressFields() as $name => $field) {
                $key = $group.'_'.$name;
                $form[$key] = $value($input[$key] ?? '', $field, $name);
            }
        }
        if (isset($input['same_as_present_address']) && ! is_bool($input['same_as_present_address'])) {
            throw new \InvalidArgumentException('Invalid address checkbox.');
        }
        $form['same_as_present_address'] = $input['same_as_present_address'] ?? false;
        $original = $input['separate_permanent_address'] ?? [];
        if (! is_array($original)) {
            throw new \InvalidArgumentException('Invalid permanent address draft.');
        }
        $form['separate_permanent_address'] = [];
        foreach (CandidateAddress::cifCandidateAddressFields() as $name => $field) {
            $form['separate_permanent_address'][$name] = $value($original[$name] ?? '', $field, $name);
        }

        return $form;
    }

    public static function cifInvitationCodeValidSql(string $alias = ''): string
    {
        $prefix = $alias === '' ? '' : $alias.'.';

        return "({$prefix}redeemed_at IS NOT NULL OR {$prefix}submitted_at IS NOT NULL OR {$prefix}expires_at > UTC_TIMESTAMP())";
    }
}
