<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Services\Recruitment\AccessLimiter;
use App\Services\Recruitment\ApplicantAccess;
use App\Services\Recruitment\CandidateUpdateEmail;
use App\Services\Recruitment\HrEmail;
use App\Services\Recruitment\MailManagement;
use App\Services\Recruitment\MailTransport;
use App\Services\Recruitment\RecruitmentDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;

class EmailController extends Controller
{
    public function __invoke()
    {
        try {
            if (empty(session()->get('admin'))) {
                return response()->json(['error' => 'Sign in as HR to manage email.'], 401);
            }
            if (request()->method() !== 'POST') {
                return response()->json(['error' => 'Method not allowed.'], 405);
            }
            $in = json_decode(request()->getContent(), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($in)) {
                throw new \InvalidArgumentException('Invalid request.');
            }
            $pdo = RecruitmentDatabase::pdo();
            // Match the source's data-only default refresh while preserving HR edits.
            HrEmail::cifMailSeed($pdo);
            $action = request()->query()['action'] ?? '';
            $actor = (int) session()->get('admin'.'.'.'id');
            if (in_array($action, ['candidate_update_preview', 'candidate_update_send'], true)) {
                if ($action === 'candidate_update_preview') {
                    if (! is_int($in['candidate_id'] ?? null) || $in['candidate_id'] < 1 || ! is_string($in['stage'] ?? null)) {
                        throw new \InvalidArgumentException('Choose a candidate and stage.');
                    }
                    $pdo->beginTransaction();
                    $q = $pdo->prepare('SELECT id FROM candidates WHERE id=? FOR UPDATE');
                    $q->execute([$in['candidate_id']]);
                    $preview = CandidateUpdateEmail::cifCandidateUpdatePreview($pdo, $in['candidate_id'], $in['stage']);
                    $pdo->commit();
                    unset($preview['facts']);

                    return response()->json(['preview' => $preview]);
                }
                AccessLimiter::check('email-send-'.$actor);
                $result = CandidateUpdateEmail::cifSendCandidateUpdate($pdo, $in, $actor);
                $code = $result['code'];
                unset($result['code']);

                return response()->json($result, $code);
            }
            if ($action === 'archive') {
                $result = MailManagement::cifArchiveMail($pdo, $in, $actor);
                $code = $result['code'];
                unset($result['code']);

                return response()->json($result, $code);
            }
            if ($action === 'resend') {
                AccessLimiter::check('email-send-'.$actor);
                $result = MailManagement::cifResendMail($pdo, $in, $actor);
                $code = $result['code'];
                unset($result['code']);

                return response()->json($result, $code);
            }
            if ($action === 'catalogue') {
                return response()->json(array_merge(['categories' => HrEmail::cifMailCategories(), 'variables' => HrEmail::cifMailVariables()], MailTransport::cifSmtpAvailability()));
            }
            if ($action === 'templates') {
                $q = $pdo->query('SELECT t.*,a.name AS editor FROM hr_email_templates t LEFT JOIN admins a ON a.id=t.updated_by ORDER BY t.archived,t.name,t.id');

                return response()->json(['templates' => $q->fetchAll()]);
            }
            if ($action === 'template_save') {
                $id = $in['id'] ?? 0;
                $revision = $in['revision'] ?? 0;
                if (! is_int($id) || $id < 1) {
                    throw new \InvalidArgumentException('Choose an existing template to edit. Adding templates is not available.');
                }
                if (! is_int($revision) || $revision < 0) {
                    throw new \InvalidArgumentException('Invalid template revision.');
                }
                $name = HrEmail::cifMailText($in['name'] ?? '', 150, true);
                $subject = HrEmail::cifMailText($in['subject'] ?? '', 200, true);
                $body = HrEmail::cifMailText($in['body'] ?? '', 10000);
                $category = $in['category'] ?? '';
                if (! is_string($category) || ! isset(HrEmail::cifMailCategories()[$category])) {
                    throw new \InvalidArgumentException('Select a template category.');
                }
                if ($category !== 'invitation' && preg_match('/\{\{(?:invitation_code|cif_deadline)\}\}/', $subject.$body)) {
                    throw new \InvalidArgumentException('Code and deadline placeholders belong to CIF invitation templates only.');
                }
                $q = $pdo->prepare('UPDATE hr_email_templates SET name=?,category=?,subject=?,body=?,revision=revision+1,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND revision=? AND archived=0');
                $q->execute([$name, $category, $subject, $body, $actor, $id, $revision]);
                if (! $q->rowCount()) {
                    return response()->json(['error' => 'Template changed or was archived. Reopen it before saving.'], 409);
                }

                return response()->json(['id' => $id, 'message' => 'Email template saved.']);
            }
            if ($action === 'template_archive') {
                if (! is_int($in['id'] ?? null) || ! is_int($in['revision'] ?? null) || ! is_bool($in['archived'] ?? null)) {
                    throw new \InvalidArgumentException('Invalid archive request.');
                }
                $q = $pdo->prepare('UPDATE hr_email_templates SET archived=?,revision=revision+1,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND revision=?');
                $q->execute([(int) $in['archived'], $actor, $in['id'], $in['revision']]);
                if (! $q->rowCount()) {
                    return response()->json(['error' => 'Template changed. Refresh and try again.'], 409);
                }

                return response()->json(['message' => $in['archived'] ? 'Template archived. Existing prepared messages are unchanged.' : 'Template restored.']);
            }
            if ($action === 'recipients') {
                $kind = $in['kind'] ?? 'candidate';
                $search = $in['search'] ?? '';
                if (! in_array($kind, ['candidate', 'invitation'], true) || ! is_string($search) || mb_strlen($search) > 150) {
                    throw new \InvalidArgumentException('Invalid recipient search.');
                }
                if ($kind === 'candidate') {
                    $email = "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(r.form_data,'\$.contact_email')),''),NULLIF(d.contact_email,''),c.email)";
                    $sql = "SELECT c.id,CONCAT_WS(' ',c.first_name,c.middle_name,c.last_name) AS name,{$email} AS email,c.candidate_number AS reference FROM candidates c LEFT JOIN candidate_details d ON d.candidate_id=c.id LEFT JOIN candidate_admin_revisions r ON r.id=(SELECT MAX(latest.id) FROM candidate_admin_revisions latest WHERE latest.candidate_id=c.id) WHERE c.archived_at IS NULL AND LOCATE(CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci,CONCAT_WS(' ',c.first_name,c.middle_name,c.last_name,c.email,{$email},c.candidate_number))>0 ORDER BY c.id DESC LIMIT 20";
                } else {
                    $sql = "SELECT id,'Candidate' AS name,email,position AS reference FROM invitations WHERE archived_at IS NULL AND submitted_at IS NULL AND access_code IS NOT NULL AND ".ApplicantAccess::cifInvitationCodeValidSql()." AND LOCATE(?,CONCAT_WS(' ',email,position))>0 ORDER BY id DESC LIMIT 20";
                }
                $q = $pdo->prepare($sql);
                $q->execute([trim($search)]);

                return response()->json(['recipients' => $q->fetchAll()]);
            }
            if ($action === 'preview') {
                return response()->json(['preview' => HrEmail::cifMailPreview($pdo, $in)]);
            }
            if ($action === 'submit') {
                AccessLimiter::check('email-send-'.$actor);
                $result = MailTransport::cifSubmitMail($pdo, $in, $actor);
                $code = $result['code'];
                unset($result['code']);

                return response()->json($result, $code);
            }
            if ($action === 'prepare') {
                $complete = function (int $id) {
                    return response()->json(['id' => $id, 'message' => 'Message saved.']);
                };
                foreach (['status', 'sent_at', 'last_error', 'is_preview'] as $key) {
                    if (array_key_exists($key, $in)) {
                        throw new \InvalidArgumentException('Delivery status is controlled by the email service, not manually.');
                    }
                }
                if (! is_string($in['preparation_key'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/D', $in['preparation_key']) || ! is_string($in['preview_hash'] ?? null)) {
                    throw new \InvalidArgumentException('Preview the message before preparing it.');
                }
                $fingerprint = hash('sha256', json_encode([$in['template_id'] ?? null, $in['target_kind'] ?? null, $in['target_id'] ?? null, $in['preview_hash']], JSON_THROW_ON_ERROR));
                $pdo->beginTransaction();
                $q = $pdo->prepare('SELECT id,preparation_hash FROM hr_email_outbox WHERE preparation_key=?');
                $q->execute([$in['preparation_key']]);
                $existing = $q->fetch();
                if ($existing) {
                    $pdo->rollBack();
                    if (! hash_equals($existing['preparation_hash'], $fingerprint)) {
                        return response()->json(['error' => 'This submission key was already used for a different message.'], 409);
                    }

                    return $complete((int) $existing['id']);
                }
                $p = HrEmail::cifMailPreview($pdo, $in, true);
                if (! hash_equals($p['preview_hash'], $in['preview_hash'])) {
                    $pdo->rollBack();

                    return response()->json(['error' => 'Template or recipient details changed. Preview again before preparing.'], 409);
                }
                $q = $pdo->prepare('INSERT INTO hr_email_outbox (template_id,template_name,template_revision,candidate_id,invitation_id,recipient_name,recipient_email,subject,body,preparation_key,preparation_hash,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
                $q->execute([$p['template_id'], $p['template_name'], $p['template_revision'], $p['target_kind'] === 'candidate' ? $p['target_id'] : null, $p['target_kind'] === 'invitation' ? $p['target_id'] : null, $p['recipient_name'], $p['recipient_email'], $p['subject'], $p['body'], $in['preparation_key'], $fingerprint, $actor, $actor]);
                $id = (int) $pdo->lastInsertId();
                $q = $pdo->prepare('INSERT INTO hr_email_events (message_id,event,admin_id,created_at) VALUES (?,?,?,UTC_TIMESTAMP())');
                $q->execute([$id, $action === 'submit' ? 'Submitted' : 'Prepared', $actor]);
                $pdo->commit();

                return $complete($id);
            }
            if ($action === 'messages') {
                $search = $in['search'] ?? '';
                $status = $in['status'] ?? '';
                $page = $in['page'] ?? 1;
                if (! is_string($search) || mb_strlen($search) > 150 || ! in_array($status, ['', 'Prepared', 'Sent', 'Failed', 'Cancelled'], true) || ! is_int($page) || $page < 1) {
                    throw new \InvalidArgumentException('Invalid mail filters.');
                }
                $archived = $in['archived'] ?? false;
                if (! is_bool($archived)) {
                    throw new \InvalidArgumentException('Invalid mail view.');
                }
                $where = ' WHERE '.($archived ? 'EXISTS' : 'NOT EXISTS')." (SELECT 1 FROM hr_email_archives ar WHERE ar.message_id=m.id) AND LOCATE(?,CONCAT_WS(' ',m.recipient_email,m.recipient_name,m.subject,m.template_name))>0";
                $params = [trim($search)];
                if ($status !== '') {
                    $where .= ' AND m.status=?';
                    $params[] = $status;
                }
                $q = $pdo->prepare('SELECT COUNT(*) FROM hr_email_outbox m'.$where);
                $q->execute($params);
                $total = (int) $q->fetchColumn();
                $pages = max(1, (int) ceil($total / 8));
                $page = min($page, $pages);
                $q = $pdo->prepare('SELECT m.id,m.recipient_email,m.recipient_name,m.subject,m.template_name,m.status,m.is_preview,m.created_at,m.sent_at,m.last_error,m.revision,a.name AS creator FROM hr_email_outbox m JOIN admins a ON a.id=m.created_by'.$where.' ORDER BY m.created_at DESC,m.id DESC LIMIT 8 OFFSET '.($page - 1) * 8);
                $q->execute($params);
                $counts = array_fill_keys(['Prepared', 'Sent', 'Failed', 'Cancelled'], 0);
                foreach ($pdo->query('SELECT m.status,COUNT(*) AS n FROM hr_email_outbox m WHERE '.($archived ? 'EXISTS' : 'NOT EXISTS').' (SELECT 1 FROM hr_email_archives ar WHERE ar.message_id=m.id) GROUP BY m.status')->fetchAll() as $row) {
                    $counts[$row['status']] = (int) $row['n'];
                }

                return response()->json(['messages' => $q->fetchAll(), 'counts' => $counts, 'total' => $total, 'page' => $page, 'pages' => $pages, 'archived' => $archived]);
            }
            if ($action === 'detail' || $action === 'cancel') {
                if (! is_int($in['id'] ?? null) || $in['id'] < 1) {
                    throw new \InvalidArgumentException('Invalid message.');
                }
                if ($action === 'cancel') {
                    if (! is_int($in['revision'] ?? null)) {
                        throw new \InvalidArgumentException('Invalid message revision.');
                    }
                    $pdo->beginTransaction();
                    $q = $pdo->prepare("UPDATE hr_email_outbox SET status='Cancelled',revision=revision+1,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND revision=? AND status='Prepared' AND NOT EXISTS (SELECT 1 FROM hr_email_archives ar WHERE ar.message_id=hr_email_outbox.id) AND COALESCE((SELECT event FROM hr_email_events WHERE message_id=hr_email_outbox.id ORDER BY id DESC LIMIT 1),'') <> 'Sending'");
                    $q->execute([$actor, $in['id'], $in['revision']]);
                    if (! $q->rowCount()) {
                        $pdo->rollBack();

                        return response()->json(['error' => 'Only an unchanged Prepared message can be cancelled.'], 409);
                    }
                    $q = $pdo->prepare("INSERT INTO hr_email_events (message_id,event,admin_id,created_at) VALUES (?,'Cancelled',?,UTC_TIMESTAMP())");
                    $q->execute([$in['id'], $actor]);
                    $pdo->commit();

                    return response()->json(['message' => 'Prepared message cancelled. No email was sent.']);
                }
                $q = $pdo->prepare('SELECT m.*,a.name AS creator,ar.archived_at FROM hr_email_outbox m JOIN admins a ON a.id=m.created_by LEFT JOIN hr_email_archives ar ON ar.message_id=m.id WHERE m.id=?');
                $q->execute([$in['id']]);
                $message = $q->fetch();
                if (! $message) {
                    return response()->json(['error' => 'Message not found.'], 404);
                }
                unset($message['preparation_key'], $message['preparation_hash']);
                $q = $pdo->prepare('SELECT e.event,e.created_at,a.name AS actor FROM hr_email_events e JOIN admins a ON a.id=e.admin_id WHERE message_id=? ORDER BY e.id');
                $q->execute([$in['id']]);
                $events = $q->fetchAll();
                $q = $pdo->prepare('SELECT status,error_message,provider_message_id,created_at FROM hr_email_attempts WHERE message_id=? ORDER BY id DESC');
                $q->execute([$in['id']]);

                return response()->json(['message' => $message, 'events' => $events, 'attempts' => $q->fetchAll()]);
            }
            if ($action === 'send' || $action === 'retry') {
                AccessLimiter::check('email-send-'.$actor);
                $result = MailTransport::cifSendPreparedMail($pdo, $in, $actor, $action);
                $code = $result['code'];
                unset($result['code']);

                return response()->json($result, $code);
            }

            return response()->json(['error' => 'Unknown email action.'], 404);
        } catch (\InvalidArgumentException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\JsonException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return response()->json(['error' => 'Invalid JSON request.'], 400);
        } catch (\Throwable $e) {
            if ($e instanceof HttpResponseException) {
                throw $e;
            }
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof \PDOException && $e->getCode() === '23000') {
                return response()->json(['error' => 'Message was already prepared. Refresh Mail management before trying again.'], 409);
            }
            error_log('HR email: '.$e->getMessage());

            return response()->json(['error' => 'Unable to manage email. Please refresh or contact your administrator.'], 500);
        }
    }
}
