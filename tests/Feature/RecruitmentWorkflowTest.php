<?php

namespace Tests\Feature;

use App\Services\Recruitment\AuditSetup;
use App\Services\Recruitment\ApplicantPrivacy;
use App\Services\Recruitment\CandidateInterviews;
use App\Services\Recruitment\CandidatePortal;
use App\Services\Recruitment\CandidatePublication;
use App\Services\Recruitment\CandidateStorage;
use App\Services\Recruitment\CandidateUpdateEmail;
use App\Services\Recruitment\CandidateView;
use App\Services\Recruitment\CifFields;
use App\Services\Recruitment\HrFields;
use App\Services\Recruitment\MailTransport;
use App\Services\Recruitment\RecruitmentDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RecruitmentWorkflowTest extends TestCase
{
    private string $fixtureDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDatabase = 'cif_mrf_test_'.bin2hex(random_bytes(8));
        $this->fixtureCommand('create');
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => $this->fixtureDatabase, 'database.connections.mysql.url' => null]);
        DB::purge('mysql');
        DB::table('department_users')->where('id', 1)->update(['username' => 'dept@example.test']);
        DB::table('department_users')->where('id', 2)->update(['username' => 'other@example.test']);
    }

    protected function tearDown(): void
    {
        try {
            DB::disconnect('mysql');
            $this->fixtureCommand('drop');
        } finally {
            parent::tearDown();
        }
    }

    private function fixtureCommand(string $operation): void
    {
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/database.php'), $operation, $this->fixtureDatabase]);
        $process->setTimeout(30)->mustRun();
    }

    private function hr(): void
    {
        $this->postJson('/ajax/access.php?action=admin_login', ['email' => 'admin', 'password' => '123'])->assertOk();
    }

    private function requestForm(): array
    {
        return ['position_title' => 'Engineering Support Staff', 'site_location' => 'Fictional office', 'headcount' => '2',
            'reason' => 'Additional manpower', 'reason_details' => 'Fictional test only', 'employment_status' => 'Regular',
            'education_level' => 'Bachelor’s degree', 'years_experience' => '2', 'job_roles' => 'Fictional duties',
            'computer' => 'Laptop', 'table_chair' => 'Required', 'company_sim' => 'Not required', 'staff_house' => 'Not required', 'vehicle' => 'Not required'];
    }

    private function requestOpening(): array
    {
        $this->postJson('/ajax/department.php?action=login', ['email' => 'dept@example.test', 'password' => '123'])->assertOk();
        $key = bin2hex(random_bytes(32));
        $input = ['form' => $this->requestForm(), 'submission_key' => $key];
        $record = $this->postJson('/ajax/mrf.php?portal=department&action=submit', $input)->assertOk()->json('record');
        $this->postJson('/ajax/mrf.php?portal=department&action=submit', $input)->assertOk()->assertJsonPath('record.id', $record['id']);
        $this->assertSame(2, DB::table('manpower_requests')->count());

        return $record;
    }

    public function test_requests_approval_headcounts_ownership_and_account_scope(): void
    {
        $record = $this->requestOpening();
        $this->hr();
        $this->postJson('/ajax/mrf.php?portal=admin&action=candidate_options', [])->assertOk()->assertJsonCount(0, 'requests');
        $approval = ['id' => (int) $record['id'], 'approval_revision' => 0, 'decision' => 'Approved',
            'confirm_document' => true, 'date_approved' => '2026-01-01', 'position_category' => 'A', 'notes' => 'Verified fictional approval'];
        $this->postJson('/ajax/mrf.php?portal=department&action=approval_update', $approval)->assertForbidden();
        $this->postJson('/ajax/mrf.php?portal=admin&action=approval_update', $approval)->assertOk();
        $this->postJson('/ajax/mrf.php?portal=admin&action=approval_update', $approval)->assertConflict();
        $this->assertSame('2026-01-31', DB::table('manpower_request_approvals')->value('hiring_due_date'));
        $this->assertSame(1, DB::table('manpower_request_approval_history')->count());
        $this->postJson('/ajax/mrf.php?portal=admin&action=candidate_options', [])->assertOk()->assertJsonCount(2, 'requests');
        $detail = $this->postJson('/ajax/mrf.php?portal=admin&action=detail', ['id' => (int) $record['id']])->assertOk()->json('record');
        $assignment = ['id' => (int) $record['id'], 'assignment_revision' => $detail['hr_assignment']['revision'],
            'hr_admin_id' => empty($detail['hr_assignment']['hr_admin_id']) ? 1 : null, 'reason' => 'Fictional reassignment'];
        $this->postJson('/ajax/mrf.php?portal=admin&action=assignment_update', $assignment)->assertOk();
        $this->postJson('/ajax/mrf.php?portal=admin&action=assignment_update', $assignment)->assertConflict();
        foreach (['utf8mb4_general_ci', 'utf8mb4_unicode_ci'] as $collation) {
            RecruitmentDatabase::pdo()->exec('SET NAMES utf8mb4 COLLATE '.$collation);
            $this->postJson('/ajax/mrf.php?portal=admin&action=list', ['approval' => 'Approved', 'search' => $record['batch_number']])->assertOk()->assertJsonPath('total', 2);
        }
        $this->postJson('/ajax/department.php?action=login', ['email' => 'other@example.test', 'password' => '123'])->assertOk();
        $this->postJson('/ajax/mrf.php?portal=department&action=detail', ['id' => (int) $record['id']])->assertNotFound();
        $this->get('/pages/mrf-requestor/mrf.php?id='.$record['id'])->assertNotFound();
    }

    private function candidate(): array
    {
        $form = [];
        foreach (CifFields::cifScalarFields() as $key => $field) {
            $form[$key] = ! $field['required'] ? '' : match ($field['type']) {
                'select' => $field['options'][0], 'tel' => '+63-900-000-0000', 'email' => 'fictional@example.test',
                'date' => '1995-06-15', 'measurement' => '65', 'year_range' => '2016–2020', default => 'Fictional response',
            };
        }
        $form = array_replace($form, ['fn' => 'Fictional', 'ln' => 'Candidate', 'desired_position' => 'Engineering Support Staff', 'height_cm' => '170', 'weight_kg' => '65']);
        foreach (CifFields::cifDefinition()['groups'] as $group => $definition) {
            $form[$group] = [];
        }
        $form = CandidateStorage::validateCif($form + ['consent' => true, 'background_consent' => true]);
        $invitation = DB::table('invitations')->insertGetId(['email' => 'fictional@example.test', 'created_by' => 1, 'expires_at' => '2027-01-01', 'submitted_at' => '2026-10-01']);
        $id = DB::table('candidates')->insertGetId(['candidate_number' => 'FIXTURE2601', 'invitation_id' => $invitation, 'email' => 'fictional@example.test',
            'first_name' => $form['fn'], 'last_name' => $form['ln'], 'position' => $form['desired_position'], 'form_data' => json_encode($form)]);
        CandidateStorage::saveCifDetails(RecruitmentDatabase::pdo(), $id, $form);
        $hr = [];
        foreach (HrFields::cifHrFields() as $fields) {
            foreach ($fields as $name => $label) {
                $choices = HrFields::cifHrChoices($name);
                $hr[$name] = ! HrFields::cifHrRequired($name) ? '' : ($choices ? $choices[0] : ($name === 'recertification_date' ? date('Y-m-d') : 'Fictional assessment'));
            }
        }

        return [$id, $form, $hr];
    }

    public function test_parallel_assessments_deployment_locks_and_unique_kpi_credit(): void
    {
        $record = $this->requestOpening();
        $this->hr();
        $this->postJson('/ajax/mrf.php?portal=admin&action=approval_update', ['id' => (int) $record['id'], 'approval_revision' => 0, 'decision' => 'Approved',
            'confirm_document' => true, 'date_approved' => '2026-01-01', 'position_category' => 'A'])->assertOk();
        $record = $this->postJson('/ajax/mrf.php?portal=admin&action=detail', ['id' => (int) $record['id']])->assertOk()->json('record');
        [$id, $form, $hr] = $this->candidate();
        $input = ['id' => $id, 'scope' => 'cif', 'revision' => 0, 'form' => $form, 'mrf_id' => (int) $record['id'], 'mrf_text' => $record['mrf_number'], 'complete' => true, 'screening_decision' => 'Passed'];
        $screened = $this->postJson('/ajax/access.php?action=candidate_save', $input)->assertOk()->json();
        $this->postJson('/ajax/access.php?action=candidate_save', $input)->assertConflict();
        $initial = array_replace($input, ['scope' => 'initial_interview', 'revision' => $screened['revision'], 'hr' => $hr, 'decision' => 'Passed',
            'assessment_requirements' => ['department_required' => true, 'ldt_required' => true]]);
        $this->postJson('/ajax/access.php?action=candidate_save', $initial)->assertOk();
        $stage = ['id' => $id, 'revision' => 0, 'decision' => 'Passed', 'remarks' => 'Fictional assessment', 'complete' => true];
        $this->postJson('/ajax/access.php?action=candidate_stage_save', $stage + ['stage' => 'job_offer'])->assertUnprocessable();
        $this->postJson('/ajax/access.php?action=candidate_stage_save', $stage + ['stage' => 'lie_detection'])->assertOk();
        $this->assertSame('Department Interview', DB::table('candidates')->where('id', $id)->value('status'));
        $this->postJson('/ajax/access.php?action=candidate_stage_save', $stage + ['stage' => 'department_interview'])->assertOk();
        $this->postJson('/ajax/access.php?action=candidate_stage_save', array_replace($stage, ['stage' => 'job_offer', 'decision' => 'Accepted']))->assertOk();
        $candidate = CandidateView::loadCifCandidate(RecruitmentDatabase::pdo(), $id);
        $requirements = CandidateInterviews::cifAssessmentRequirements($candidate);
        $deployment = ['id' => $id, 'revision' => 0, 'requirements_revision' => $requirements['revision'], 'source_mrf_id' => (int) $record['id'],
            'mrf_id' => (int) $record['id'], 'mrf_text' => $record['mrf_number'], 'complete' => true, 'date_hired' => '2026-10-27', 'employee_id' => '', 'position_title' => 'Software Developer'];
        $this->postJson('/ajax/access.php?action=candidate_deployment_save', $deployment)->assertOk();
        $this->assertSame('Deployed', DB::table('candidates')->where('id', $id)->value('status'));
        $credits = DB::table('candidate_hr_activity')->count();
        $this->postJson('/ajax/access.php?action=candidate_deployment_save', $deployment)->assertConflict();
        $this->postJson('/ajax/access.php?action=candidate_save', $initial)->assertConflict();
        $this->assertSame($credits, DB::table('candidate_hr_activity')->count());
        $this->get('/pages/admin/record.php?id='.$id)->assertOk()->assertSee('view-only');
    }

    public function test_candidate_drafts_submission_original_snapshot_and_private_tracker(): void
    {
        $this->hr();
        $invitation = $this->postJson('/ajax/access.php?action=generate', ['email' => 'new@example.test', 'position' => 'Engineering Support Staff'])->assertOk()->json();
        foreach (['utf8mb4_general_ci', 'utf8mb4_unicode_ci'] as $collation) {
            RecruitmentDatabase::pdo()->exec('SET NAMES utf8mb4 COLLATE '.$collation);
            $this->postJson('/ajax/access.php?action=invitations', ['status' => 'Pending', 'search' => 'new@example.test'])->assertOk()->assertJsonPath('total', 1);
        }
        $this->postJson('/ajax/access.php?action=verify', ['email' => 'new@example.test', 'code' => $invitation['code']])->assertOk()->assertJsonPath('candidate.mode', 'form');
        $this->postJson('/ajax/access.php?action=candidate_draft', ['revision' => 0, 'step' => 0, 'form' => ['fn' => 'Blocked']])->assertForbidden();
        $this->postJson('/ajax/access.php?action=submit', ['draft_revision' => 0])->assertForbidden();
        $this->postJson('/ajax/access.php?action=candidate_privacy_accept', ['revision' => 0, 'background_authorized' => true, 'accepted' => 'false', 'version' => ApplicantPrivacy::version()])->assertUnprocessable();
        $this->postJson('/ajax/access.php?action=candidate_privacy_accept', ['revision' => 0, 'background_authorized' => true, 'accepted' => true, 'version' => 'old-notice'])->assertUnprocessable();
        $this->postJson('/ajax/access.php?action=candidate_privacy_accept', ['revision' => 0, 'background_authorized' => true, 'accepted' => true, 'version' => ApplicantPrivacy::version()])->assertOk()->assertJsonPath('revision', 1);
        $audit = DB::table('audit_events')->where('action', 'Applicant privacy notice accepted')->first();
        $this->assertSame('Candidate', $audit->actor_role);
        $this->assertSame((string) $invitation['id'], (string) $audit->actor_id);
        $privacy = json_decode(DB::table('invitations')->where('id', $invitation['id'])->value('draft_form'), true)['privacy_notice'];
        $this->assertSame(ApplicantPrivacy::version(), $privacy['version']);
        $this->assertStringContainsString('two (2) years', $privacy['notice_html']);
        $this->postJson('/ajax/access.php?action=verify', ['email' => 'new@example.test', 'code' => $invitation['code']])->assertOk()->assertJsonPath('candidate.privacy_accepted', true);
        $draft = ['revision' => 1, 'step' => 0, 'form' => ['fn' => 'Draft', 'ln' => 'Candidate', 'privacy_notice' => ['accepted_at' => 'forged']]];
        $saved = $this->postJson('/ajax/access.php?action=candidate_draft', $draft)->assertOk()->json();
        $this->postJson('/ajax/access.php?action=candidate_draft', $draft)->assertConflict();
        [, $form] = $this->candidate();
        $form += ['draft_revision' => $saved['revision'], 'consent' => true, 'background_consent' => true, 'data_privacy_consent' => true, 'same_as_present_address' => true];
        foreach (['present', 'emergency'] as $group) {
            foreach (['barangay', 'municipality', 'province'] as $part) {
                $form[$group.'_'.$part] = 'Fictional place';
            }
        }
        // The saved opening notice is authoritative, regardless of final-form fields.
        $form['data_privacy_consent'] = false;
        $form['background_consent'] = false;
        $form['future_opportunities_consent'] = true;
        $submitted = $this->postJson('/ajax/access.php?action=submit', $form)->assertOk()->assertJsonPath('candidate.mode', 'tracking')->json();
        $number = $submitted['candidate_number'];
        $candidate = DB::table('candidates')->where('candidate_number', $number)->first();
        $original = $candidate->form_data;
        $this->assertSame($privacy, json_decode($original, true)['privacy_notice']);
        $this->assertTrue(json_decode($original, true)['data_privacy_consent']);
        $this->assertArrayNotHasKey('future_opportunities_consent', json_decode($original, true));
        $this->assertTrue(json_decode($original, true)['background_consent']);
        $this->assertSame(\App\Services\Recruitment\ApplicantPrivacy::declarations(), json_decode($original, true)['applicant_declarations']);
        $this->get('/pages/admin/record.php?id='.$candidate->id)->assertOk()->assertSee('Data Privacy Consent')->assertDontSee('Future Career Opportunities');
        $this->postJson('/ajax/access.php?action=submit', $form)->assertConflict();
        $this->postJson('/ajax/access.php?action=candidate_draft', ['revision' => 1, 'step' => 0, 'form' => ['fn' => 'Changed']])->assertConflict();
        $this->postJson('/ajax/access.php?action=candidate_progress')->assertOk()->assertDontSee('hr_notes')->assertDontSee('abstract_reasoning_score');
        $this->assertSame($original, DB::table('candidates')->where('id', $candidate->id)->value('form_data'));
        $this->assertSame($number, DB::table('invitations')->where('id', $candidate->invitation_id)->value('applicant_id'));
        $printCandidate = CandidateView::loadCifCandidate(RecruitmentDatabase::pdo(), (int) $candidate->id);
        ob_start();
        \App\Services\Recruitment\CifPrint::renderCifPrint($printCandidate);
        $withWebConsent = ob_get_clean();
        unset($printCandidate['form']['privacy_notice'], $printCandidate['form']['applicant_declarations'], $printCandidate['form']['data_privacy_consent']);
        ob_start();
        \App\Services\Recruitment\CifPrint::renderCifPrint($printCandidate);
        $this->assertSame($withWebConsent, ob_get_clean(), 'The web privacy additions must not change the printable CIF.');

    }

    public function test_privacy_acceptance_can_be_unchecked_and_saved_before_starting(): void
    {
        $this->hr();
        $invite = $this->postJson('/ajax/access.php?action=generate', ['email' => 'change-consent@example.test', 'position' => 'Engineering Support Staff'])->assertOk()->json();
        $this->postJson('/ajax/access.php?action=verify', ['email' => 'change-consent@example.test', 'code' => $invite['code']])->assertOk();
        $input = ['revision' => 0, 'background_authorized' => true, 'accepted' => true, 'version' => ApplicantPrivacy::version()];
        $this->postJson('/ajax/access.php?action=candidate_privacy_accept', $input)->assertOk();
        $this->getJson('/ajax/access.php?action=state')->assertOk()->assertJsonPath('candidate.privacy_accepted', true)->assertJsonPath('candidate.draft.saved_at', null);
        $this->postJson('/ajax/access.php?action=candidate_privacy_accept', array_replace($input, ['revision' => 1, 'background_authorized' => true, 'accepted' => false]))->assertOk();
        $this->getJson('/ajax/access.php?action=state')->assertOk()->assertJsonPath('candidate.privacy_accepted', false);
        $this->postJson('/ajax/access.php?action=candidate_draft', ['revision' => 2, 'step' => 0, 'form' => []])->assertForbidden();
        $this->postJson('/ajax/access.php?action=submit', ['draft_revision' => 2])->assertForbidden();
        $this->postJson('/ajax/access.php?action=candidate_privacy_accept', array_replace($input, ['revision' => 2]))->assertOk();
        $this->postJson('/ajax/access.php?action=candidate_draft', ['revision' => 3, 'step' => 0, 'form' => []])->assertOk();
    }

    public function test_privacy_notice_changes_require_reacceptance_and_preserve_existing_drafts(): void
    {
        $this->hr();
        $invite = $this->postJson('/ajax/access.php?action=generate', ['email' => 'privacy@example.test', 'position' => 'Engineering Support Staff'])->assertOk()->json();
        DB::table('invitations')->where('id', $invite['id'])->update(['draft_form' => json_encode(['fn' => 'Existing answer']), 'draft_step' => 2]);
        $this->postJson('/ajax/access.php?action=verify', ['email' => 'privacy@example.test', 'code' => $invite['code']])->assertOk()->assertJsonPath('candidate.privacy_accepted', false);
        $accept = ['revision' => 0, 'background_authorized' => true, 'accepted' => true, 'version' => ApplicantPrivacy::version()];
        $this->postJson('/ajax/access.php?action=candidate_privacy_accept', $accept)->assertOk();
        $this->postJson('/ajax/access.php?action=candidate_privacy_accept', $accept)->assertConflict();
        $row = DB::table('invitations')->where('id', $invite['id'])->first();
        $this->assertSame('Existing answer', json_decode($row->draft_form, true)['fn']);
        $this->assertSame(2, $row->draft_step);
        config(['recruitment.privacy_dpo_email' => 'privacy@example.test']);
        $this->getJson('/ajax/access.php?action=state')->assertOk()->assertJsonPath('candidate.privacy_accepted', false);
        $this->postJson('/ajax/access.php?action=candidate_draft', ['revision' => 1, 'step' => 2, 'form' => ['fn' => 'Overwrite']])->assertForbidden();
        $this->postJson('/ajax/access.php?action=submit', ['draft_revision' => 1])->assertForbidden();
        $this->postJson('/ajax/access.php?action=candidate_privacy_accept', ['revision' => 1, 'background_authorized' => true, 'accepted' => true, 'version' => ApplicantPrivacy::version()])->assertOk();
        $this->assertSame('Existing answer', json_decode(DB::table('invitations')->where('id', $invite['id'])->value('draft_form'), true)['fn']);
    }

    public function test_both_opening_authorizations_are_required_and_partial_choices_are_preserved(): void
    {
        $this->hr();
        $invite = $this->postJson('/ajax/access.php?action=generate', ['email' => 'partial-consent@example.test', 'position' => 'Engineering Support Staff'])->assertOk()->json();
        $this->postJson('/ajax/access.php?action=verify', ['email' => 'partial-consent@example.test', 'code' => $invite['code']])->assertOk();
        foreach ([[true, false], [false, true]] as $revision => [$privacy, $background]) {
            $this->postJson('/ajax/access.php?action=candidate_privacy_accept', ['revision' => $revision, 'accepted' => $privacy, 'background_authorized' => $background, 'version' => ApplicantPrivacy::version()])->assertOk();
            $this->getJson('/ajax/access.php?action=state')->assertOk()->assertJsonPath('candidate.privacy_accepted', false)
                ->assertJsonPath('candidate.privacy_choices.data_privacy', $privacy)->assertJsonPath('candidate.privacy_choices.background', $background);
            $this->postJson('/ajax/access.php?action=candidate_draft', ['revision' => $revision + 1, 'step' => 0, 'form' => []])->assertForbidden();
            $this->postJson('/ajax/access.php?action=submit', ['draft_revision' => $revision + 1, 'data_privacy_consent' => true, 'background_consent' => true])->assertForbidden();
        }
        $this->postJson('/ajax/access.php?action=candidate_privacy_accept', ['revision' => 2, 'accepted' => true, 'background_authorized' => true, 'version' => ApplicantPrivacy::version()])->assertOk();
        $this->getJson('/ajax/access.php?action=state')->assertOk()->assertJsonPath('candidate.privacy_accepted', true);
        $this->assertStringContainsString('prior approval or go-signal', ApplicantPrivacy::declarations()['background']);
        $this->assertArrayNotHasKey('future_opportunities', ApplicantPrivacy::declarations());
    }

    public function test_mail_snapshots_templates_cancellation_and_duplicate_preparation(): void
    {
        $this->hr();
        $invitation = $this->postJson('/ajax/access.php?action=generate', ['email' => 'mail@example.test', 'position' => 'Engineering Support Staff'])->assertOk()->json();
        $id = (int) DB::table('invitations')->where('email', 'mail@example.test')->value('id');
        $template = DB::table('hr_email_templates')->where('slug', 'invitation')->first();
        $input = ['template_id' => (int) $template->id, 'target_kind' => 'invitation', 'target_id' => $id];
        $preview = $this->postJson('/ajax/email.php?action=preview', $input)->assertOk()->json('preview');
        $input += ['preview_hash' => $preview['preview_hash'], 'preparation_key' => bin2hex(random_bytes(32))];
        $prepared = $this->postJson('/ajax/email.php?action=prepare', $input)->assertOk()->json();
        $this->postJson('/ajax/email.php?action=prepare', $input)->assertOk()->assertJsonPath('id', $prepared['id']);
        $this->assertSame(1, DB::table('hr_email_outbox')->count());
        $this->postJson('/ajax/email.php?action=submit', $input)->assertConflict();
        $this->assertSame(0, DB::table('hr_email_attempts')->count());
        $snapshot = DB::table('hr_email_outbox')->first();
        $this->postJson('/ajax/email.php?action=template_save', ['id' => (int) $template->id, 'revision' => (int) $template->revision,
            'name' => $template->name, 'category' => 'invitation', 'subject' => 'Edited future subject', 'body' => $template->body])->assertOk();
        $this->assertSame($snapshot->subject, DB::table('hr_email_outbox')->value('subject'));
        $this->postJson('/ajax/email.php?action=template_archive', ['id' => (int) $template->id, 'revision' => 1, 'archived' => true])->assertOk();
        $this->postJson('/ajax/email.php?action=template_archive', ['id' => (int) $template->id, 'revision' => 1, 'archived' => false])->assertConflict();
        $this->postJson('/ajax/email.php?action=template_archive', ['id' => (int) $template->id, 'revision' => 2, 'archived' => false])->assertOk();
        $this->postJson('/ajax/email.php?action=template_save', ['id' => 0])->assertUnprocessable();
        $this->postJson('/ajax/email.php?action=cancel', ['id' => (int) $snapshot->id, 'revision' => (int) $snapshot->revision])->assertOk();
        $this->assertSame('Cancelled', DB::table('hr_email_outbox')->value('status'));
    }

    public function test_explicit_email_retry_does_not_duplicate_accepted_mail(): void
    {
        $this->hr();
        config(['recruitment.public_origin' => 'https://careers.example.test']);
        $invite = $this->postJson('/ajax/access.php?action=generate', ['email' => 'retry@example.test', 'position' => 'Engineering Support Staff'])->assertOk()->json();
        $template = DB::table('hr_email_templates')->where('slug', 'invitation')->first();
        $input = ['template_id' => (int) $template->id, 'target_kind' => 'invitation', 'target_id' => $invite['id']];
        $preview = $this->postJson('/ajax/email.php?action=preview', $input)->assertOk()->json('preview');
        $input += ['preview_hash' => $preview['preview_hash'], 'preparation_key' => bin2hex(random_bytes(32))];
        $prepared = $this->postJson('/ajax/email.php?action=prepare', $input)->assertOk()->json();
        $pdo = RecruitmentDatabase::pdo();
        $calls = 0;
        $failure = static function () use (&$calls): string {
            $calls++;
            throw new \RuntimeException('Synthetic authentication failure');
        };
        $success = static function () use (&$calls): string {
            $calls++;

            return '<fixture-accepted@example.test>';
        };
        $message = ['id' => (int) $prepared['id'], 'revision' => 0];
        $failed = MailTransport::cifSendPreparedMail($pdo, $message, 1, 'send', $failure, []);
        $this->assertSame('Failed', $failed['status']);
        $message['revision'] = (int) DB::table('hr_email_outbox')->where('id', $message['id'])->value('revision');
        $sent = MailTransport::cifSendPreparedMail($pdo, $message, 1, 'retry', $success, []);
        $this->assertSame('Sent', $sent['status']);
        $this->assertSame(409, MailTransport::cifSendPreparedMail($pdo, $message, 1, 'retry', $success, [])['code']);
        $this->assertSame(2, $calls);
        $this->assertSame(2, DB::table('hr_email_attempts')->count());
    }

    public function test_tracker_release_remains_private_and_independent_of_email_acceptance(): void
    {
        config(['recruitment.public_origin' => 'https://careers.example.test']);
        $record = $this->requestOpening();
        $this->hr();
        $this->postJson('/ajax/mrf.php?portal=admin&action=approval_update', ['id' => (int) $record['id'], 'approval_revision' => 0, 'decision' => 'Approved',
            'confirm_document' => true, 'date_approved' => '2026-01-01', 'position_category' => 'A'])->assertOk();
        $record = $this->postJson('/ajax/mrf.php?portal=admin&action=detail', ['id' => (int) $record['id']])->assertOk()->json('record');
        [$id, $form] = $this->candidate();
        $this->postJson('/ajax/access.php?action=candidate_save', ['id' => $id, 'scope' => 'cif', 'revision' => 0, 'form' => $form,
            'mrf_id' => (int) $record['id'], 'mrf_text' => $record['mrf_number'], 'complete' => true, 'screening_decision' => 'Passed'])->assertOk();
        $pdo = RecruitmentDatabase::pdo();
        $this->assertSame('Screening', CandidatePublication::cifReleasedProgress($pdo, $id)['status']);
        $preview = CandidateUpdateEmail::cifCandidateUpdatePreview($pdo, $id, 'cif');
        $input = ['candidate_id' => $id, 'stage' => 'cif', 'preview_hash' => $preview['preview_hash'], 'request_key' => bin2hex(random_bytes(32)),
            'subject' => $preview['subject'], 'body' => $preview['body']];
        $calls = 0;
        $failure = static function () use (&$calls): string {
            $calls++;
            throw new \RuntimeException('Synthetic email failure');
        };
        $result = CandidateUpdateEmail::cifSendCandidateUpdate($pdo, $input, 1, $failure, []);
        $this->assertTrue($result['tracker_updated']);
        $this->assertSame('Failed', $result['email_status']);
        $progress = CandidatePortal::cifPublicApplicationProgress($pdo, $id);
        $this->assertSame('Interview', $progress['status']);
        $this->assertStringNotContainsString('hr_notes', json_encode($progress));
        $this->assertStringNotContainsString('abstract_reasoning_score', json_encode($progress));
        CandidateUpdateEmail::cifSendCandidateUpdate($pdo, $input, 1, $failure, []);
        $this->assertSame(1, $calls, 'Replaying a release must not automatically resend a failed email.');
        $this->assertSame(1, DB::table('candidate_public_updates')->count());
    }

    public function test_account_changes_revoke_old_credentials_and_preserve_request_details(): void
    {
        $record = $this->requestOpening();
        $original = DB::table('manpower_requests')->where('id', $record['id'])->first();
        $this->postJson('/ajax/department.php?action=settings_save', ['group_name' => 'New group', 'current_password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/ajax/department.php?action=settings_save', ['group_name' => 'New group', 'current_password' => '123'])->assertOk();
        $this->assertEquals($original, DB::table('manpower_requests')->where('id', $record['id'])->first());
        $this->hr();
        $oldHash = DB::table('admins')->where('id', 1)->value('password_hash');
        $this->postJson('/ajax/access.php?action=admin_settings_save', ['name' => 'Fictional HR', 'current_password' => '123',
            'new_password' => 'NewFixturePassword123', 'confirm_password' => 'NewFixturePassword123'])->assertOk();
        $this->getJson('/ajax/access.php?action=state')->assertJsonPath('admin.name', 'FICTIONAL HR');
        $this->withSession(['admin_password_signature' => hash('sha256', $oldHash)]);
        $this->postJson('/ajax/access.php?action=candidates')->assertUnauthorized();
        $this->assertTrue(password_verify('NewFixturePassword123', DB::table('admins')->where('id', 1)->value('password_hash')));
    }

    public function test_kpi_calculations_match_the_current_reference_database_regression(): void
    {
        $this->requestOpening();
        $this->candidate();
        $process = new Process([PHP_BINARY, base_path('tests/ReferenceDatabase/mrf-hr-kpi.php'), $this->fixtureDatabase]);
        $process->setTimeout(30)->run();
        $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        $this->assertStringContainsString('all four KPI formulas', $process->getOutput());
    }

    public function test_shared_candidates_archive_restore_and_keep_original_submissions(): void
    {
        $this->hr();
        [$id, $form] = $this->candidate();
        $original = DB::table('candidates')->where('id', $id)->first();
        $this->postJson('/ajax/access.php?action=candidates', ['search' => 'FIXTURE2601', 'status' => 'Screening'])->assertOk()->assertJsonPath('total', 1);
        $edited = array_replace($form, ['nickname' => 'Later HR edit']);
        $this->postJson('/ajax/access.php?action=candidate_save', ['id' => $id, 'revision' => 0, 'scope' => 'cif', 'complete' => false, 'form' => $edited])->assertOk();
        $this->assertSame($original->form_data, DB::table('candidates')->where('id', $id)->value('form_data'));
        $this->assertSame($original->candidate_number, DB::table('candidates')->where('id', $id)->value('candidate_number'));
        $this->assertSame(1, DB::table('candidate_admin_revisions')->where('candidate_id', $id)->count());
        $this->postJson('/ajax/access.php?action=candidate_archive', ['id' => $id, 'archived' => true])->assertOk();
        $this->postJson('/ajax/access.php?action=candidates', [])->assertOk()->assertJsonPath('total', 0);
        $this->postJson('/ajax/access.php?action=candidates', ['archived' => true])->assertOk()->assertJsonPath('total', 1);
        $this->postJson('/ajax/access.php?action=candidate_archive', ['id' => $id, 'archived' => false])->assertOk();
        $this->postJson('/ajax/access.php?action=candidates', [])->assertOk()->assertJsonPath('total', 1);
    }

    public function test_superadmin_roles_account_controls_and_transactional_audits(): void
    {
        $superId = DB::table('admins')->insertGetId(['email' => 'superfixture', 'name' => 'Super Fixture',
            'password_hash' => password_hash('FixtureSuper123', PASSWORD_DEFAULT), 'role' => 'superadmin']);
        $this->postJson('/ajax/access.php?action=admin_login', ['email' => 'superfixture', 'password' => 'FixtureSuper123'])->assertUnauthorized();
        $this->postJson('/ajax/superadmin.php?action=login', ['login' => 'superfixture', 'password' => 'FixtureSuper123'])->assertOk();
        $this->postJson('/ajax/access.php?action=candidates', [])->assertUnauthorized();
        $this->hr();
        $this->getJson('/ajax/superadmin.php?action=state')->assertJsonPath('superadmin.id', $superId);
        $save = ['kind' => 'admin', 'operation' => 'create', 'role' => 'hr', 'name' => 'Additional HR',
            'login' => 'additionalhr', 'new_password' => 'Additional123', 'confirm_password' => 'Additional123',
            'current_password' => 'FixtureSuper123', 'reason' => 'Fictional account control test'];
        $this->postJson('/ajax/superadmin.php?action=save_user', array_replace($save, ['current_password' => 'wrong']))->assertUnprocessable();
        $this->assertFalse(DB::table('admins')->where('email', 'additionalhr')->exists());
        $this->postJson('/ajax/superadmin.php?action=save_user', $save)->assertOk();
        $this->postJson('/ajax/superadmin.php?action=save_user', ['kind' => 'admin', 'operation' => 'deactivate',
            'id' => 1, 'revision' => 0, 'current_password' => 'FixtureSuper123', 'reason' => 'Test revocation'])->assertOk();
        $this->postJson('/ajax/access.php?action=candidates', [])->assertUnauthorized();
        $this->getJson('/ajax/superadmin.php?action=state')->assertJsonPath('superadmin.id', $superId);
        $this->postJson('/ajax/superadmin.php?action=save_user', ['kind' => 'admin', 'operation' => 'deactivate',
            'id' => $superId, 'revision' => 0, 'current_password' => 'FixtureSuper123', 'reason' => 'Test self protection'])->assertUnprocessable();
        $pdo = RecruitmentDatabase::pdo();
        $before = (int) $pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();
        $pdo->beginTransaction();
        $pdo->exec("UPDATE department_users SET group_name='Rolled back change' WHERE id=1");
        $this->assertSame($before + 1, (int) $pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        $pdo->rollBack();
        $this->assertSame($before, (int) $pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        $this->assertSame(0, AuditSetup::cifInstallAuditTriggers($pdo)['installed']);
        $counter = $pdo->query("SHOW TABLE STATUS LIKE 'audit_events'")->fetch()['Auto_increment'];
        $this->assertSame(0, AuditSetup::cifImportAuditHistory($pdo));
        $this->assertSame($counter, $pdo->query("SHOW TABLE STATUS LIKE 'audit_events'")->fetch()['Auto_increment']);
        $this->postJson('/ajax/superadmin.php?action=audit', [])->assertOk()->assertDontSee('password_hash')->assertDontSee('FixtureSuper123');
    }

    public function test_rejected_csrf_requests_keep_the_friendly_message_and_validated_audit_actor(): void
    {
        $this->hr();
        // Exercise the actual forgery middleware, which Laravel bypasses in unit tests.
        $this->app->instance('env', 'local');
        $this->postJson('/ajax/access.php?action=candidates', [], ['X-CSRF-Token' => 'invalid'])
            ->assertStatus(419)->assertJsonPath('error', 'Please refresh the page and try again.');
        $events = DB::table('audit_events')->where('action', 'Request declined')->where('entity_id', 'candidates')->get();
        $this->assertCount(1, $events);
        $this->assertSame('HR', $events[0]->actor_role);
        $this->assertSame(1, (int) $events[0]->actor_id);
        $this->assertSame('Failed', $events[0]->outcome);
        $this->assertSame(['http_status' => 419], json_decode($events[0]->after_json, true));
        $this->assertSame(0, DB::table('candidates')->count());
    }
}
