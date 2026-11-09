<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use Illuminate\Http\Exceptions\HttpResponseException;

final class MailManagement
{
    public static function cifArchiveMail(\PDO $pdo, array $input, int $actor): array
    {
        if (! is_int($input['id'] ?? null) || $input['id'] < 1 || ! is_int($input['revision'] ?? null) || ! is_bool($input['archived'] ?? null)) {
            throw new \InvalidArgumentException('Invalid email archive request.');
        }
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('SELECT revision FROM hr_email_outbox WHERE id=? FOR UPDATE');
            $q->execute([$input['id']]);
            $revision = $q->fetchColumn();
            $q = $pdo->prepare('SELECT event FROM hr_email_events WHERE message_id=? ORDER BY id DESC LIMIT 1');
            $q->execute([$input['id']]);
            $last = $q->fetchColumn();
            if ($revision === false || (int) $revision !== $input['revision'] || $last === 'Sending') {
                $pdo->rollBack();

                return ['code' => 409, 'error' => 'This email changed or is being sent. Refresh before archiving or restoring.'];
            }
            $q = $pdo->prepare('SELECT message_id FROM hr_email_archives WHERE message_id=?');
            $q->execute([$input['id']]);
            $archived = (bool) $q->fetchColumn();
            if ($archived === $input['archived']) {
                $pdo->rollBack();

                return ['code' => 409, 'error' => 'This email is already in that view. Refresh the list.'];
            }
            if ($input['archived']) {
                $q = $pdo->prepare('INSERT INTO hr_email_archives(message_id,archived_by,archived_at) VALUES (?,?,UTC_TIMESTAMP())');
                $q->execute([$input['id'], $actor]);
            } else {
                $q = $pdo->prepare('DELETE FROM hr_email_archives WHERE message_id=?');
                $q->execute([$input['id']]);
            }
            $q = $pdo->prepare('UPDATE hr_email_outbox SET revision=revision+1,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE id=?');
            $q->execute([$actor, $input['id']]);
            $q = $pdo->prepare('INSERT INTO hr_email_events(message_id,event,admin_id,created_at) VALUES (?,?,?,UTC_TIMESTAMP())');
            $q->execute([$input['id'], $input['archived'] ? 'Archived' : 'Restored', $actor]);
            $pdo->commit();

            return ['code' => 200, 'message' => $input['archived'] ? 'Email archived. The message and sending result are preserved.' : 'Email restored to the active list.'];
        } catch (\Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Resending creates a new snapshot/result; the original sending result is preserved. */
    public static function cifResendMail(\PDO $pdo, array $input, int $actor, ?callable $transport = null, ?array $settings = null): array
    {
        if (! is_int($input['id'] ?? null) || $input['id'] < 1 || ! is_int($input['revision'] ?? null) || ! is_string($input['request_key'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $input['request_key'])) {
            throw new \InvalidArgumentException('Reopen the message before resending.');
        }
        if ($settings === null) {
            $a = MailTransport::cifSmtpAvailability();
            if (! $a['sending_enabled']) {
                return ['code' => 409, 'error' => $a['transport']];
            }
        }
        $fingerprint = hash('sha256', 'resend:'.$input['id'].':'.$input['revision']);
        $q = $pdo->prepare('SELECT id,status,revision,last_error,preparation_hash FROM hr_email_outbox WHERE preparation_key=?');
        $q->execute([$input['request_key']]);
        $copy = $q->fetch();
        if ($copy) {
            if (! hash_equals($copy['preparation_hash'], $fingerprint)) {
                return ['code' => 409, 'error' => 'This resend request was already used for another email.'];
            }
            if ($copy['status'] !== 'Prepared') {
                return ['code' => 200, 'id' => (int) $copy['id'], 'status' => $copy['status'], 'message' => $copy['status'] === 'Sent' ? 'This resend was already sent. No duplicate was sent.' : ($copy['last_error'] ?: 'This resend was already processed.')];
            }

            return array_merge(['id' => (int) $copy['id']], MailTransport::cifSendPreparedMail($pdo, ['id' => (int) $copy['id'], 'revision' => (int) $copy['revision']], $actor, 'send', $transport, $settings));
        }
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('SELECT m.*,a.archived_at FROM hr_email_outbox m LEFT JOIN hr_email_archives a ON a.message_id=m.id WHERE m.id=? FOR UPDATE');
            $q->execute([$input['id']]);
            $m = $q->fetch();
            $q = $pdo->prepare('SELECT event FROM hr_email_events WHERE message_id=? ORDER BY id DESC LIMIT 1');
            $q->execute([$input['id']]);
            $last = $q->fetchColumn();
            if (! $m || (int) $m['revision'] !== $input['revision'] || $m['archived_at'] || (int) $m['is_preview'] || ! in_array($m['status'], ['Sent', 'Failed'], true) || $last === 'Sending') {
                $pdo->rollBack();

                return ['code' => 409, 'error' => 'Only active Sent or Failed emails can be resent. Refresh the message first.'];
            }
            $kind = $m['candidate_id'] ? 'candidate' : 'invitation';
            $target = HrEmail::cifMailTarget($pdo, $kind, (int) ($m['candidate_id'] ?: $m['invitation_id']), true);
            if (strcasecmp($target['email'], $m['recipient_email']) !== 0) {
                $pdo->rollBack();

                return ['code' => 409, 'error' => 'The recipient email changed. Send a new message to the current address instead.'];
            }
            $q = $pdo->prepare('INSERT INTO hr_email_outbox(template_id,template_name,template_revision,candidate_id,invitation_id,recipient_name,recipient_email,subject,body,preparation_key,preparation_hash,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
            $q->execute([$m['template_id'], $m['template_name'], $m['template_revision'], $m['candidate_id'], $m['invitation_id'], $m['recipient_name'], $m['recipient_email'], $m['subject'], $m['body'], $input['request_key'], $fingerprint, $actor, $actor]);
            $id = (int) $pdo->lastInsertId();
            $q = $pdo->prepare('INSERT INTO hr_email_events(message_id,event,admin_id,created_at) VALUES (?,?,?,UTC_TIMESTAMP())');
            $q->execute([$id, 'Resent from #'.$m['id'], $actor]);
            $q->execute([$m['id'], 'Resend requested', $actor]);
            $q = $pdo->prepare('UPDATE hr_email_outbox SET revision=revision+1,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE id=?');
            $q->execute([$actor, $m['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return array_merge(['id' => $id], MailTransport::cifSendPreparedMail($pdo, ['id' => $id, 'revision' => 0], $actor, 'send', $transport, $settings));
    }
}
