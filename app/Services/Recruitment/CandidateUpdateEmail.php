<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use Illuminate\Http\Exceptions\HttpResponseException;

final class CandidateUpdateEmail
{
    public static function cifCandidateUpdatePreview(\PDO $pdo, int $id, string $stage, array $edits = []): array
    {
        $candidate = CandidateView::loadCifCandidate($pdo, $id);
        if (! $candidate) {
            throw new \InvalidArgumentException('Candidate not found.');
        }
        $public = CandidatePublication::cifEnsurePublicBaseline($pdo, $candidate);
        $facts = CandidatePublication::cifProposedStageRelease($candidate, $public, $stage);
        $sourceFacts = $facts;
        $nextOptions = CandidateSchedule::cifNextPhaseOptions($facts, $stage);
        $facts = CandidateSchedule::cifApplyNextSchedule($facts, $stage, $edits);
        $progress = CandidatePublication::cifProgressFromPublicFacts($facts);
        $target = HrEmail::cifMailTarget($pdo, 'candidate', $id);
        $slug = $stage === 'cif' ? 'screening' : $stage;
        $q = $pdo->prepare('SELECT * FROM hr_email_templates WHERE slug=? AND archived=0');
        $q->execute([$slug]);
        $template = $q->fetch();
        if (! $template) {
            throw new \InvalidArgumentException('The '.HrStaff::cifHrStages()[$stage].' email template is unavailable. Restore it in Email templates.');
        }
        $result = $stage === 'deployment' ? empty($facts['deployment']['completed_at']) ? 'In progress' : 'Completed' : CandidateInterviews::cifInterviewRecord($facts, $stage)['decision'];
        $variables = ['candidate_name' => $target['name'], 'candidate_email' => $target['email'], 'applied_position' => $facts['position'], 'applicant_id' => $facts['candidate_number'], 'application_status' => $progress['status'], 'portal_url' => MailConfiguration::cifMailPortalUrl(), 'phase_name' => HrStaff::cifHrStages()[$stage], 'phase_result' => $result];
        // Candidate details such as App ID or applied position can be absent on
        // older/incomplete records. They are informational template fields, not
        // prerequisites for releasing a stage or updating the public tracker.
        foreach (['candidate_name' => 'Candidate', 'applied_position' => 'Not provided', 'applicant_id' => 'Not available', 'application_status' => 'Under review'] as $key => $fallback) {
            if (! isset($variables[$key]) || trim((string) $variables[$key]) === '') {
                $variables[$key] = $fallback;
            }
        }
        $render = static function (string $text) use ($variables): string {
            return preg_replace_callback('/\{\{([a-z_]+)\}\}/', static function (array $m) use ($variables): string {
                if (! array_key_exists($m[1], $variables)) {
                    throw new \InvalidArgumentException('The template contains an unsupported value. Edit its email template.');
                }

                return (string) $variables[$m[1]];
            }, $text);
        };
        if (isset($edits['subject'], $edits['body'])) {
            $message = HrEmail::cifMailApplyEdits(['target_kind' => 'candidate'], $edits, true);
            $subject = $message['subject'];
            $body = $message['body'];
        } else {
            $subject = $render($template['subject']);
            $body = $render($template['body']);
            if (! str_contains($template['body'], '{{phase_result}}')) {
                $body .= "\n\n".HrStaff::cifHrStages()[$stage].' result: '.$result;
            }
        }
        $preview = ['candidate_id' => $id, 'stage' => $stage, 'stage_label' => HrStaff::cifHrStages()[$stage], 'result' => $result, 'recipient_name' => $target['name'], 'recipient_email' => $target['email'], 'template_id' => (int) $template['id'], 'template_name' => $template['name'], 'template_revision' => (int) $template['revision'], 'subject' => $subject, 'body' => $body, 'progress' => $progress, 'public_revision' => $public['revision'], 'facts' => $facts, 'next_phase_options' => $nextOptions];
        // Edits/default-template changes are allowed; recipient, selected facts and public revision are protected.
        $preview['preview_hash'] = hash('sha256', json_encode([$id, $stage, $target['email'], $public['revision'], $sourceFacts], JSON_THROW_ON_ERROR));

        return $preview;
    }

    /** Tracker publication commits even when SMTP is disabled or fails. */
    public static function cifSendCandidateUpdate(\PDO $pdo, array $input, int $actor, ?callable $transport = null, ?array $settings = null): array
    {
        $id = $input['candidate_id'] ?? null;
        $stage = $input['stage'] ?? null;
        $key = $input['request_key'] ?? null;
        if (! is_int($id) || $id < 1 || ! is_string($stage) || ! isset(HrStaff::cifHrStages()[$stage]) || ! is_string($key) || ! preg_match('/^[a-f0-9]{64}$/D', $key) || ! is_string($input['preview_hash'] ?? null)) {
            throw new \InvalidArgumentException('Review the candidate update before submitting.');
        }
        $edited = HrEmail::cifMailApplyEdits(['target_kind' => 'candidate'], $input, true);
        if (! isset($edited['subject'], $edited['body'])) {
            throw new \InvalidArgumentException('Complete the subject and message.');
        }
        $fingerprint = hash('sha256', json_encode([$id, $stage, $input['preview_hash'], $edited['subject'], $edited['body'], $input['next_phase'] ?? null, $input['next_datetime'] ?? null], JSON_THROW_ON_ERROR));
        $q = $pdo->prepare('SELECT * FROM candidate_public_updates WHERE request_key=?');
        $q->execute([$key]);
        $existing = $q->fetch();
        if ($existing) {
            if (! hash_equals($existing['request_hash'], $fingerprint)) {
                return ['code' => 409, 'error' => 'This update request was already used. Reopen the update window.'];
            }

            return CandidateUpdateEmail::cifFinishCandidateUpdateEmail($pdo, (int) $existing['email_message_id'], $actor, $transport, $settings);
        }
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('SELECT id FROM candidates WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            if (! $q->fetchColumn()) {
                throw new \InvalidArgumentException('Candidate not found.');
            }
            $preview = CandidateUpdateEmail::cifCandidateUpdatePreview($pdo, $id, $stage, $input);
            if (! hash_equals($preview['preview_hash'], $input['preview_hash'])) {
                $pdo->rollBack();

                return ['code' => 409, 'error' => 'The stage or recipient changed. Review the update again before sending.'];
            }
            $summary = CandidateSchedule::cifScheduleEmailSummary($preview['progress']['next_schedule']);
            if ($summary !== '') {
                $edited['body'] .= "\n\n".$summary;
            }
            if (mb_strlen($edited['body']) > 12000) {
                throw new \InvalidArgumentException('Shorten the message to leave room for the next-phase schedule.');
            }
            $revision = $preview['public_revision'] + 1;
            $q = $pdo->prepare('INSERT INTO hr_email_outbox(template_id,template_name,template_revision,candidate_id,recipient_name,recipient_email,subject,body,preparation_key,preparation_hash,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
            $q->execute([$preview['template_id'], $preview['template_name'], $preview['template_revision'], $id, $preview['recipient_name'], $preview['recipient_email'], $edited['subject'], $edited['body'], $key, $fingerprint, $actor, $actor]);
            $messageId = (int) $pdo->lastInsertId();
            $q = $pdo->prepare('UPDATE candidate_public_state SET state_json=?,revision=?,published_by=?,published_at=UTC_TIMESTAMP() WHERE candidate_id=?');
            $q->execute([json_encode($preview['facts'], JSON_THROW_ON_ERROR), $revision, $actor, $id]);
            $q = $pdo->prepare('INSERT INTO candidate_public_updates(candidate_id,stage,public_revision,progress_json,email_message_id,request_key,request_hash,published_by,published_at) VALUES (?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
            $q->execute([$id, $stage, $revision, json_encode($preview['progress'], JSON_THROW_ON_ERROR), $messageId, $key, $fingerprint, $actor]);
            $q = $pdo->prepare("INSERT INTO hr_email_events(message_id,event,admin_id,created_at) VALUES (?,'Tracker updated',?,UTC_TIMESTAMP())");
            $q->execute([$messageId, $actor]);
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

        return CandidateUpdateEmail::cifFinishCandidateUpdateEmail($pdo, $messageId, $actor, $transport, $settings);
    }

    public static function cifFinishCandidateUpdateEmail(\PDO $pdo, int $messageId, int $actor, ?callable $transport = null, ?array $settings = null): array
    {
        $q = $pdo->prepare('SELECT status,revision,last_error FROM hr_email_outbox WHERE id=?');
        $q->execute([$messageId]);
        $mail = $q->fetch();
        if ($mail['status'] !== 'Prepared') {
            return ['code' => 200, 'id' => $messageId, 'tracker_updated' => true, 'email_status' => $mail['status'], 'message' => 'Tracker updated. '.($mail['status'] === 'Sent' ? 'Email was already sent; no duplicate was sent.' : 'Email was not sent. Check Mail management before resending.')];
        }
        try {
            $result = MailTransport::cifSendPreparedMail($pdo, ['id' => $messageId, 'revision' => (int) $mail['revision']], $actor, 'send', $transport, $settings);
        } catch (\Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            $result = ['error' => 'Email processing could not finish. Check Mail management and provider logs before resending.'];
        }
        if (isset($result['error'])) {
            // A concurrent/in-flight SMTP attempt must not be relabelled as a failure.
            $pdo->beginTransaction();
            try {
                $q = $pdo->prepare('SELECT status FROM hr_email_outbox WHERE id=? FOR UPDATE');
                $q->execute([$messageId]);
                $status = $q->fetchColumn();
                $q = $pdo->prepare('SELECT event FROM hr_email_events WHERE message_id=? ORDER BY id DESC LIMIT 1');
                $q->execute([$messageId]);
                $event = $q->fetchColumn();
                if ($status === 'Prepared' && $event !== 'Sending') {
                    $error = $result['error'];
                    $q = $pdo->prepare("UPDATE hr_email_outbox SET status='Failed',last_error=?,revision=revision+1,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE id=?");
                    $q->execute([$error, $actor, $messageId]);
                    $q = $pdo->prepare("INSERT INTO hr_email_attempts(message_id,status,error_message,created_at) VALUES (?,'Failed',?,UTC_TIMESTAMP())");
                    $q->execute([$messageId, $error]);
                    $q = $pdo->prepare("INSERT INTO hr_email_events(message_id,event,admin_id,created_at) VALUES (?,'Failed',?,UTC_TIMESTAMP())");
                    $q->execute([$messageId, $actor]);
                    $status = 'Failed';
                }
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

            return ['code' => 200, 'id' => $messageId, 'tracker_updated' => true, 'email_status' => $status, 'message' => 'Tracker updated. Email was not confirmed sent: '.$result['error']];
        }

        return ['code' => 200, 'id' => $messageId, 'tracker_updated' => true, 'email_status' => $result['status'], 'message' => 'Tracker updated. '.$result['message']];
    }
}
