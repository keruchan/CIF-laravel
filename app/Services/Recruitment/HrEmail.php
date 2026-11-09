<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use DateTimeImmutable;
use DateTimeZone;

final class HrEmail
{
    public static function cifMailCategories(): array
    {
        return ['invitation' => 'CIF invitation', 'screening' => 'CIF screening', 'initial_interview' => 'Initial interview', 'department_interview' => 'Department interview', 'lie_detection' => 'Background check', 'job_offer' => 'Job offer', 'deployment' => 'Deployment', 'general' => 'General message'];
    }

    public static function cifMailVariables(): array
    {
        return ['phase_name' => 'Selected hiring stage', 'phase_result' => 'Result released by HR', 'candidate_name' => 'Recipient’s full name (Candidate for unsubmitted invitations)', 'applicant_id' => 'Permanent App ID (submitted candidates only)', 'applied_position' => 'Applied position', 'application_status' => 'Current application phase (submitted candidates only)', 'candidate_email' => 'Recipient email', 'portal_url' => 'Candidate portal link', 'invitation_code' => 'Invitation code (invitation templates only)', 'cif_deadline' => 'Code expiry if unused; no expiry once used (invitation templates only)'];
    }

    public static function cifMailSeed(\PDO $pdo): void
    {
        $insert = $pdo->prepare('INSERT IGNORE INTO hr_email_templates (slug,name,category,subject,body,created_at,updated_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
        $messages = [
            ['invitation', 'CIF Invitation', 'invitation', 'APEC Homes — Candidate Information Form', 'Hello {{candidate_name}},\n\nPlease complete your application at {{portal_url}}\n\nEmail: {{candidate_email}}\nInvitation code: {{invitation_code}}\nCode expiry: {{cif_deadline}}\n\nUnused codes expire after 10 days. Once used, your code will not expire. Your progress saves automatically. Return with the same email and code to finish your form or track your application.\n\nAPEC Homes HR'],
            ['initial_interview', 'Initial Interview Update', 'initial_interview', 'APEC Homes — Initial Interview Update', 'Hello {{candidate_name}},\n\nApp ID: {{applicant_id}}\nApplied Position: {{applied_position}}\n\nYour Application Is At {{application_status}}. Please Contact Our HR Team For Scheduling And Instructions.\n\nTrack Your Application At {{portal_url}}\n\nAPEC Homes HR'],
            ['screening', 'CIF Screening Update', 'screening', 'APEC Homes — CIF Screening Update', 'Hello {{candidate_name}},\n\nApp ID: {{applicant_id}}\nCurrent Application Phase: {{application_status}}\n\nTrack Your Application At {{portal_url}}. Please Contact HR For Clarification Or Next Steps.\n\nAPEC Homes HR'],
            ['department_interview', 'Department Interview Update', 'department_interview', 'APEC Homes — Department Interview', 'Hello {{candidate_name}},\n\nApp ID: {{applicant_id}}\nPlease Contact HR For Your Department Interview Instructions.\n\nTrack Your Application At {{portal_url}}\n\nAPEC Homes HR'],
            ['lie_detection', 'Background Check Update', 'lie_detection', 'APEC Homes — Assessment Update', 'Hello {{candidate_name}},\n\nApp ID: {{applicant_id}}\nPlease Contact HR For Your Required Assessment Instructions.\n\nTrack Your Application At {{portal_url}}\n\nAPEC Homes HR'],
            ['job_offer', 'Job Offer Update', 'job_offer', 'APEC Homes — Job Offer Update', 'Hello {{candidate_name}},\n\nApp ID: {{applicant_id}}\nPlease Contact HR Regarding Your Job Offer For {{applied_position}}. This Message Does Not Replace The Official Offer Document.\n\nAPEC Homes HR'],
            ['deployment', 'Deployment Update', 'deployment', 'APEC Homes — Deployment Update', 'Hello {{candidate_name}},\n\nApp ID: {{applicant_id}}\nPlease Coordinate With HR For Your Confirmed Hiring And Deployment Instructions.\n\nAPEC Homes HR'],
            ['general', 'Application Status Update', 'general', 'APEC Homes — Application Update', 'Hello {{candidate_name}},\n\nApplied Position: {{applied_position}}\nYou Can Check Your Application At {{portal_url}} Using Your Invited Email And Code.\n\nPlease Contact HR If You Need Assistance.\n\nAPEC Homes HR'],
        ];
        foreach ($messages as $row) {
            $row[4] = str_replace('\\n', "\n", $row[4]);
            $insert->execute($row);
        }
        // Refresh only the untouched original default; keep HR's edited templates.
        $oldInvitationBody = "Hello {{candidate_name}},\n\nPlease Complete Your CIF At {{portal_url}}\n\nEmail: {{candidate_email}}\nInvitation Code: {{invitation_code}}\nCIF Deadline: {{cif_deadline}}\n\nYour Progress Saves Automatically. Use The Same Email And Code To Resume Before The Deadline. After Submission, Use Them To Track Your Application.\n\nAPEC Homes HR";
        $update = $pdo->prepare("UPDATE hr_email_templates SET body=?,revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE slug='invitation' AND updated_by IS NULL AND BINARY body=BINARY ?");
        $update->execute([str_replace('\\n', "\n", $messages[0][4]), $oldInvitationBody]);
        $pdo->exec("UPDATE hr_email_templates SET archived=1 WHERE slug='exam' AND archived=0");
    }

    public static function cifMailText(mixed $value, int $max, bool $subject = false): string
    {
        if (! is_string($value) || str_contains($value, "\x00") || $subject && preg_match('/[\r\n]/', $value)) {
            throw new \InvalidArgumentException('Use plain text within the allowed length; subjects cannot contain line breaks.');
        }
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        // Keep placeholder names intact while applying the shared prose format.
        $parts = preg_split('/(\{\{[a-z_]+\}\})/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as &$part) {
            if (! preg_match('/^\{\{[a-z_]+\}\}$/D', $part)) {
                $part = TextFormat::cifProperText($part);
            }
        }
        unset($part);
        $value = implode('', $parts);
        if ($value === '' || mb_strlen($value) > $max) {
            throw new \InvalidArgumentException('Complete the message within the allowed field lengths.');
        }
        preg_match_all('/\{\{([a-z_]+)\}\}/', $value, $matches);
        foreach ($matches[1] as $key) {
            if (! isset(HrEmail::cifMailVariables()[$key])) {
                throw new \InvalidArgumentException('Unknown placeholder: '.$key.'. Use the provided variables.');
            }
        }
        if (str_contains(preg_replace('/\{\{[a-z_]+\}\}/', '', $value), '{{') || str_contains(preg_replace('/\{\{[a-z_]+\}\}/', '', $value), '}}')) {
            throw new \InvalidArgumentException('Use exact placeholders such as {{candidate_name}}.');
        }

        return $value;
    }

    public static function cifMailTarget(\PDO $pdo, string $kind, int $id, bool $lock = false): array
    {
        if ($kind === 'candidate') {
            $q = $pdo->prepare('SELECT id FROM candidates WHERE id=? AND archived_at IS NULL'.($lock ? ' FOR UPDATE' : ''));
            $q->execute([$id]);
            if (! $q->fetchColumn()) {
                throw new \InvalidArgumentException('Choose an active, registered candidate.');
            }
            $c = CandidateView::loadCifCandidate($pdo, $id);
            $f = $c['form'];
            $email = trim((string) ($f['contact_email'] ?? ''));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = $c['email'];
            }
            $progress = CandidatePublication::cifReleasedProgress($pdo, $id);

            return ['kind' => $kind, 'id' => $id, 'email' => $email, 'name' => trim(implode(' ', array_filter([$f['fn'] ?? '', $f['mn'] ?? '', $f['ln'] ?? '', $f['sx'] ?? '']))), 'position' => $c['position'] ?? '', 'applicant_id' => $c['candidate_number'], 'status' => $progress['status'], 'public_steps' => $progress['steps'], 'code' => '', 'deadline' => ''];
        }
        if ($kind !== 'invitation') {
            throw new \InvalidArgumentException('Choose a registered candidate or invitation.');
        }
        $q = $pdo->prepare('SELECT *,'.ApplicantAccess::cifInvitationCodeValidSql().' AS valid_code FROM invitations WHERE id=? AND archived_at IS NULL'.($lock ? ' FOR UPDATE' : ''));
        $q->execute([$id]);
        $i = $q->fetch();
        if (! $i || $i['submitted_at'] || ! $i['valid_code'] || ! $i['access_code']) {
            throw new \InvalidArgumentException('Choose an unsubmitted invitation with a valid code.');
        }

        return ['kind' => $kind, 'id' => $id, 'email' => $i['email'], 'name' => 'Candidate', 'position' => $i['position'] ?? '', 'applicant_id' => '', 'status' => '', 'code' => substr($i['access_code'], 0, 4).'-'.substr($i['access_code'], 4), 'deadline' => $i['redeemed_at'] ? 'No expiry (already used)' : (new DateTimeImmutable($i['expires_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('M d, Y h:i A')];
    }

    public static function cifMailPreview(\PDO $pdo, array $input, bool $lock = false, bool $useEdits = false): array
    {
        MailConfiguration::cifMailConfiguration();
        // Preview and Submit must load the same website settings.
        if (! is_int($input['target_id'] ?? null) || $input['target_id'] < 1 || ! in_array($input['target_kind'] ?? null, ['candidate', 'invitation'], true)) {
            throw new \InvalidArgumentException('Choose a valid recipient.');
        }
        if ($input['target_kind'] === 'invitation') {
            $q = $pdo->prepare("SELECT * FROM hr_email_templates WHERE slug='invitation' AND category='invitation' AND archived=0".($lock ? ' FOR UPDATE' : ''));
            $q->execute();
            $t = $q->fetch();
            if (! $t) {
                throw new \InvalidArgumentException('The CIF invitation template is unavailable. Restore it in Email templates.');
            }
            if (isset($input['template_id']) && $input['template_id'] !== (int) $t['id']) {
                throw new \InvalidArgumentException('Invitations use the CIF invitation template only.');
            }
        } else {
            if (! is_int($input['template_id'] ?? null)) {
                throw new \InvalidArgumentException('Choose an email template.');
            }
            $q = $pdo->prepare('SELECT * FROM hr_email_templates WHERE id=? AND archived=0'.($lock ? ' FOR UPDATE' : ''));
            $q->execute([$input['template_id']]);
            $t = $q->fetch();
            if (! $t) {
                throw new \InvalidArgumentException('Select an active email template.');
            }
        }
        $target = HrEmail::cifMailTarget($pdo, $input['target_kind'], $input['target_id'], $lock);
        if ($t['category'] === 'invitation' && $target['kind'] !== 'invitation') {
            throw new \InvalidArgumentException('CIF invitations require a valid, unsubmitted invitation.');
        }
        if ($t['category'] !== 'invitation' && str_contains($t['subject'].$t['body'], '{{invitation_code}}')) {
            throw new \InvalidArgumentException('Invitation codes are allowed only in CIF invitation templates.');
        }
        // Use the configured website root, never a client-supplied Host header.
        $vars = ['candidate_name' => $target['name'], 'candidate_email' => $target['email'], 'applied_position' => $target['position'], 'applicant_id' => $target['applicant_id'], 'application_status' => $target['status'], 'portal_url' => MailConfiguration::cifMailPortalUrl(), 'invitation_code' => $target['code'], 'cif_deadline' => $target['deadline']];
        $phaseKey = $t['category'] === 'screening' ? 'cif' : $t['category'];
        foreach ($target['public_steps'] ?? [] as $step) {
            if ($step['key'] === $phaseKey) {
                $vars['phase_name'] = $step['label'];
                $vars['phase_result'] = $step['status'];
            }
        }
        $render = static function (string $text) use ($vars): string {
            return preg_replace_callback('/\{\{([a-z_]+)\}\}/', static function (array $m) use ($vars): string {
                if (! isset($vars[$m[1]]) || $vars[$m[1]] === '') {
                    throw new \InvalidArgumentException('This recipient has no value for {{'.$m[1].'}}. Choose a suitable template.');
                }

                return $vars[$m[1]];
            }, $text);
        };
        if ($useEdits) {
            $edited = HrEmail::cifMailApplyEdits(['target_kind' => $target['kind']], $input);
            if (! isset($edited['subject'], $edited['body'])) {
                throw new \InvalidArgumentException('Complete the subject and message before sending.');
            }
            $subject = $edited['subject'];
            $body = $edited['body'];
        } else {
            $subject = $render($t['subject']);
            $body = $render($t['body']);
        }
        if (preg_match('/[\r\n\x00]/', $subject) || mb_strlen($subject) > 200 || mb_strlen($body) > 12000 || ! filter_var($target['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Rendered message is invalid or too long. Review the template and recipient.');
        }
        $result = ['template_id' => (int) $t['id'], 'template_revision' => (int) $t['revision'], 'template_name' => $t['name'], 'target_kind' => $target['kind'], 'target_id' => $target['id'], 'recipient_name' => $target['name'], 'recipient_email' => $target['email'], 'subject' => $subject, 'body' => $body];
        $result['preview_hash'] = hash('sha256', json_encode($result, JSON_THROW_ON_ERROR));

        return $result;
    }

    /** One-off invitation edits are saved to the sent snapshot, not the shared template. */
    public static function cifMailApplyEdits(array $preview, array $input, bool $allowCandidate = false): array
    {
        foreach (['subject' => 200, 'body' => 12000] as $field => $max) {
            if (! array_key_exists($field, $input)) {
                continue;
            }
            if ($preview['target_kind'] !== 'invitation' && ! $allowCandidate) {
                throw new \InvalidArgumentException('Message editing is available for CIF invitations only.');
            }
            $value = $input[$field];
            if (! is_string($value) || str_contains($value, "\x00") || ! mb_check_encoding($value, 'UTF-8')) {
                throw new \InvalidArgumentException('Enter a valid email '.$field.'.');
            }
            $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
            if ($value === '' || mb_strlen($value) > $max || $field === 'subject' && str_contains($value, "\n") || str_contains($value, '{{') || str_contains($value, '}}')) {
                throw new \InvalidArgumentException('Complete the email '.$field.' within '.$max.' characters. Remove unfinished placeholders and subject line breaks.');
            }
            $preview[$field] = $value;
        }

        return $preview;
    }
}
