<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use Illuminate\Http\Exceptions\HttpResponseException;

final class MailTransport
{
    public static function cifSmtpSettings(?array $input = null): array
    {
        $s = $input ?? MailConfiguration::cifMailConfiguration();
        if (! is_array($s) || ($s['enabled'] ?? false) !== true) {
            throw new \RuntimeException('Email sending is disabled. Configure Gmail SMTP and CIF_MAIL_ENABLED in the private .env file.');
        }
        foreach (['host', 'username', 'password', 'from_email', 'from_name', 'encryption'] as $key) {
            if (! is_string($s[$key] ?? null) || $s[$key] === '' || preg_match('/[\r\n\x00]/', $s[$key])) {
                throw new \RuntimeException('Complete the private SMTP configuration.');
            }
        }
        if (is_string($s['port'] ?? null) && preg_match('/^[0-9]{3}$/D', $s['port'])) {
            $s['port'] = (int) $s['port'];
        }
        if (! preg_match('/^[a-z0-9.-]+$/iD', $s['host']) || ! in_array([$s['port'] ?? null, $s['encryption']], [[465, 'ssl'], [587, 'tls']], true) || ! filter_var($s['from_email'], FILTER_VALIDATE_EMAIL) || ! filter_var($s['username'], FILTER_VALIDATE_EMAIL) || strcasecmp($s['username'], $s['from_email']) !== 0 || str_contains($s['password'], 'YOUR_MAILBOX_PASSWORD') || str_contains($s['password'], 'YOUR_GOOGLE_APP_PASSWORD')) {
            throw new \RuntimeException('Review the SMTP host, TLS port, matching sender/username and mailbox password.');
        }
        if (! extension_loaded('openssl')) {
            throw new \RuntimeException('Enable the PHP OpenSSL extension for secure email.');
        }
        $origin = (string) config('recruitment.public_origin');
        $localOrigin = app()->environment('local', 'testing') && preg_match('~^http://(?:localhost|127\.0\.0\.1|\[::1\])(?::[0-9]+)?/?$~iD', $origin);
        if ((! preg_match('~^https://[^/]+/?$~D', $origin) && ! $localOrigin) || ! filter_var($origin, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('Configure CIF_PUBLIC_ORIGIN with an HTTPS website address, or a localhost HTTP address for local testing.');
        }

        return $s;
    }

    /** Return only fixed diagnostic labels, never raw SMTP replies or credentials. */
    public static function cifSmtpSafeError(\Throwable $error): string
    {
        $text = strtolower($error->getMessage());
        $reason = match (true) {
            str_contains($text, 'authenticate'), str_contains($text, 'authentication') => 'Email sign-in failed. Contact your system administrator for help.',
            str_contains($text, 'certificate'), str_contains($text, 'crypto'), str_contains($text, 'tls') => 'A secure email connection could not be established. Contact your system administrator for help.',
            str_contains($text, 'connect'), str_contains($text, 'timed out'), str_contains($text, 'getaddrinfo') => 'The email service could not be reached. Please try again later or contact your system administrator.',
            str_contains($text, 'recipient'), str_contains($text, 'recipients') => "The email was not accepted. Please verify that the recipient's email address is correct and the mailbox is active.",
            str_contains($text, 'data not accepted'), str_contains($text, 'rate'), str_contains($text, 'quota') => "The email was not accepted. Please verify that the recipient's email address is correct and the mailbox is active.",
            default => "Email sending did not complete. Please verify that the recipient's email address is correct and the mailbox is active.",
        };

        return $reason.' Delivery is unconfirmed; confirm receipt before resending.';
    }

    public static function cifSmtpAvailability(): array
    {
        try {
            MailTransport::cifSmtpSettings();

            return ['sending_enabled' => true, 'transport' => 'Email sending is set up.'];
        } catch (\RuntimeException $e) {
            return ['sending_enabled' => false, 'transport' => 'Email sending is currently unavailable. Contact your system administrator for help.'];
        }
    }

    public static function cifSmtpSend(array $settings, array $message): string
    {
        return LaravelMailTransport::send($message);
    }

    /** Persist a reviewed snapshot and send in the same explicit submission. */
    public static function cifSubmitMail(\PDO $pdo, array $input, int $actor, ?callable $transport = null, ?array $settings = null): array
    {
        if ($settings === null) {
            $availability = MailTransport::cifSmtpAvailability();
            if (! $availability['sending_enabled']) {
                return ['code' => 409, 'error' => $availability['transport']];
            }
        }
        foreach (['status', 'sent_at', 'last_error', 'is_preview'] as $key) {
            if (array_key_exists($key, $input)) {
                throw new \InvalidArgumentException('Sending results cannot be set manually.');
            }
        }
        if (! is_string($input['preparation_key'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $input['preparation_key']) || ! is_string($input['preview_hash'] ?? null)) {
            throw new \InvalidArgumentException('Review the message before submitting.');
        }
        $hasEdits = ($input['target_kind'] ?? null) === 'invitation' && array_key_exists('subject', $input) && array_key_exists('body', $input);
        $fingerprint = hash('sha256', json_encode([$input['template_id'] ?? null, $input['target_kind'] ?? null, $input['target_id'] ?? null, $input['preview_hash'], $input['subject'] ?? null, $input['body'] ?? null, $input['reviewed_recipient_email'] ?? null], JSON_THROW_ON_ERROR));
        $find = function () use ($pdo, $input): array|false {
            $q = $pdo->prepare('SELECT * FROM hr_email_outbox WHERE preparation_key=?');
            $q->execute([$input['preparation_key']]);

            return $q->fetch();
        };
        $existing = $find();
        if (! $existing) {
            try {
                $pdo->beginTransaction();
                $preview = HrEmail::cifMailPreview($pdo, $input, true, $hasEdits);
                if ($hasEdits && (! is_string($input['reviewed_recipient_email'] ?? null) || strcasecmp($preview['recipient_email'], $input['reviewed_recipient_email']) !== 0)) {
                    $pdo->rollBack();

                    return ['code' => 409, 'error' => 'The recipient email changed. Reopen the email window to check the address.'];
                }
                if (! $hasEdits && ! hash_equals($preview['preview_hash'], $input['preview_hash'])) {
                    $pdo->rollBack();

                    return ['code' => 409, 'error' => 'Template or recipient changed. Review the updated message before submitting.'];
                }
                $preview = HrEmail::cifMailApplyEdits($preview, $input);
                $q = $pdo->prepare('INSERT INTO hr_email_outbox(template_id,template_name,template_revision,candidate_id,invitation_id,recipient_name,recipient_email,subject,body,preparation_key,preparation_hash,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
                $q->execute([$preview['template_id'], $preview['template_name'], $preview['template_revision'], $preview['target_kind'] === 'candidate' ? $preview['target_id'] : null, $preview['target_kind'] === 'invitation' ? $preview['target_id'] : null, $preview['recipient_name'], $preview['recipient_email'], $preview['subject'], $preview['body'], $input['preparation_key'], $fingerprint, $actor, $actor]);
                $id = (int) $pdo->lastInsertId();
                $q = $pdo->prepare("INSERT INTO hr_email_events(message_id,event,admin_id,created_at) VALUES (?,'Submitted',?,UTC_TIMESTAMP())");
                $q->execute([$id, $actor]);
                $pdo->commit();
            } catch (\PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($e->getCode() !== '23000' || ! $find()) {
                    throw $e;
                }
            } catch (\Throwable $e) {
                if ($e instanceof HttpResponseException) {
                    throw $e;
                }
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            $existing = $find();
        }
        if (! hash_equals($existing['preparation_hash'], $fingerprint)) {
            return ['code' => 409, 'error' => 'This submission was already used for a different message. Reopen the email window.'];
        }
        $id = (int) $existing['id'];
        if ($existing['status'] === 'Sent') {
            return ['code' => 200, 'id' => $id, 'status' => 'Sent', 'message' => 'This email was already sent. No duplicate was sent.'];
        }
        if ($existing['status'] !== 'Prepared') {
            return ['code' => 200, 'id' => $id, 'status' => $existing['status'], 'message' => $existing['last_error'] ?: 'This submission was already processed. Check Mail management.'];
        }

        return array_merge(['id' => $id], MailTransport::cifSendPreparedMail($pdo, ['id' => $id, 'revision' => (int) $existing['revision']], $actor, 'send', $transport, $settings));
    }

    /** Only explicit HR Send/Retry calls reach this function; no background bulk sends. */
    public static function cifSendPreparedMail(\PDO $pdo, array $input, int $actor, string $action, ?callable $transport = null, ?array $settings = null): array
    {
        $availability = MailTransport::cifSmtpAvailability();
        if ($settings === null && ! $availability['sending_enabled']) {
            return ['code' => 409, 'error' => $availability['transport']];
        }
        $settings ??= MailTransport::cifSmtpSettings();
        if (! is_int($input['id'] ?? null) || $input['id'] < 1 || ! is_int($input['revision'] ?? null)) {
            return ['code' => 409, 'error' => 'Reopen the message before sending.'];
        }
        $lock = 'cif-mail-'.$input['id'];
        $q = $pdo->prepare('SELECT GET_LOCK(?, 0)');
        $q->execute([$lock]);
        if ((int) $q->fetchColumn() !== 1) {
            return ['code' => 409, 'error' => 'This message is already being processed. Refresh its details.'];
        }
        try {
            $pdo->beginTransaction();
            $q = $pdo->prepare('SELECT m.*,a.archived_at FROM hr_email_outbox m LEFT JOIN hr_email_archives a ON a.message_id=m.id WHERE m.id=? FOR UPDATE');
            $q->execute([$input['id']]);
            $m = $q->fetch();
            $q = $pdo->prepare('SELECT event FROM hr_email_events WHERE message_id=? ORDER BY id DESC LIMIT 1');
            $q->execute([$input['id']]);
            $last = $q->fetchColumn();
            if (! $m || $m['archived_at'] || (int) $m['revision'] !== $input['revision'] || (int) $m['is_preview'] !== 0 || $last === 'Sending' || $m['status'] !== ($action === 'retry' ? 'Failed' : 'Prepared')) {
                $pdo->rollBack();

                return ['code' => 409, 'error' => 'Message changed, is a preview, was already sent, or has an unresolved sending attempt. Refresh before proceeding.'];
            }
            // Verify registered recipient is still eligible and matches this saved snapshot.
            $kind = $m['candidate_id'] ? 'candidate' : 'invitation';
            $target = HrEmail::cifMailTarget($pdo, $kind, (int) ($m['candidate_id'] ?: $m['invitation_id']), true);
            if (strcasecmp($target['email'], $m['recipient_email']) !== 0) {
                $pdo->rollBack();

                return ['code' => 409, 'error' => 'Recipient email changed. Prepare a new message with the current details.'];
            }
            if (str_contains($m['body'], 'http://localhost')) {
                $pdo->rollBack();

                return ['code' => 409, 'error' => 'This message contains a localhost link. Prepare a new message after configuring the public website address.'];
            }
            $q = $pdo->prepare("INSERT INTO hr_email_events(message_id,event,admin_id,created_at) VALUES (?,'Sending',?,UTC_TIMESTAMP())");
            $q->execute([$m['id'], $actor]);
            $q = $pdo->prepare('UPDATE hr_email_outbox SET revision=revision+1,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE id=?');
            $q->execute([$actor, $m['id']]);
            $pdo->commit();
            // Persist intent before SMTP: a crash must not silently resend.
            $status = 'Sent';
            $error = null;
            $reference = null;
            try {
                $reference = ($transport ?? [self::class, 'cifSmtpSend'])($settings, $m);
            } catch (\Throwable $e) {
                if ($e instanceof HttpResponseException) {
                    throw $e;
                }
                $status = 'Failed';
                // Never expose raw provider errors: they can contain credentials/addresses.
                $error = MailTransport::cifSmtpSafeError($e);
            }
            $pdo->beginTransaction();
            // Compare the outcome in PHP: native string parameters and SQL literals
            // can have different collations on hosting, even after SMTP succeeds.
            $q = $pdo->prepare('UPDATE hr_email_outbox SET status=?,last_error=?,sent_at=IF(?,UTC_TIMESTAMP(),NULL),revision=revision+1,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE id=?');
            $q->execute([$status, $error, (int) ($status === 'Sent'), $actor, $m['id']]);
            $q = $pdo->prepare('INSERT INTO hr_email_attempts(message_id,status,provider_message_id,error_message,created_at) VALUES (?,?,?,?,UTC_TIMESTAMP())');
            $q->execute([$m['id'], $status, $reference, $error]);
            $q = $pdo->prepare('INSERT INTO hr_email_events(message_id,event,admin_id,created_at) VALUES (?,?,?,UTC_TIMESTAMP())');
            $q->execute([$m['id'], $status, $actor]);
            $pdo->commit();

            return ['code' => 200, 'status' => $status, 'message' => $status === 'Sent' ? 'Email sent. Receipt and reading have not been confirmed.' : $error];
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $q = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $q->execute([$lock]);
        }
    }
}
