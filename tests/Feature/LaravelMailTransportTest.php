<?php

namespace Tests\Feature;

use App\Services\Recruitment\LaravelMailTransport;
use App\Services\Recruitment\MailTransport;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LaravelMailTransportTest extends TestCase
{
    public function test_local_smtp_settings_allow_only_loopback_http_and_keep_production_https_required(): void
    {
        config([
            'recruitment.mail_enabled' => true,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.username' => 'fixture@gmail.com',
            'mail.mailers.smtp.password' => 'fixture-only',
            'mail.from.address' => 'fixture@gmail.com',
            'mail.from.name' => 'APEC Homes HR',
        ]);
        $this->app->instance('env', 'local');
        foreach (['http://localhost:8000', 'http://127.0.0.1:8000', 'http://[::1]:8000'] as $origin) {
            config(['recruitment.public_origin' => $origin]);
            $this->assertTrue(MailTransport::cifSmtpAvailability()['sending_enabled']);
        }
        foreach (['http://careers.example.test', 'http://localhost.example.test', 'http://localhost:8000/pages/candidate', 'http://localhost@careers.example.test'] as $origin) {
            config(['recruitment.public_origin' => $origin]);
            $this->assertFalse(MailTransport::cifSmtpAvailability()['sending_enabled']);
        }
        config(['recruitment.public_origin' => 'http://localhost:8000']);
        $this->app->instance('env', 'production');
        $this->assertFalse(MailTransport::cifSmtpAvailability()['sending_enabled']);
        config(['recruitment.public_origin' => 'https://careers.example.test']);
        $this->assertTrue(MailTransport::cifSmtpAvailability()['sending_enabled']);
        config(['mail.mailers.smtp.password' => 'YOUR_GOOGLE_APP_PASSWORD']);
        $this->assertFalse(MailTransport::cifSmtpAvailability()['sending_enabled']);
    }

    public function test_laravel_transport_preserves_branded_html_plain_text_and_acceptance_id(): void
    {
        config([
            'recruitment.mail_enabled' => true,
            'recruitment.public_origin' => 'https://careers.example.test',
            'mail.default' => 'smtp',
            // The named mailer uses an in-memory transport: no network calls.
            'mail.mailers.smtp.transport' => 'array',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => 'hr@example.test',
            'mail.mailers.smtp.password' => 'fixture-only',
            'mail.from.address' => 'hr@example.test',
            'mail.from.name' => 'APEC Homes',
        ]);
        $transport = Mail::mailer('smtp')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $id = MailTransport::cifSmtpSend(MailTransport::cifSmtpSettings(), [
            'recipient_email' => 'candidate@example.test', 'recipient_name' => 'Fictional Candidate',
            'subject' => 'Application update', 'body' => "Hello <candidate>\nhttps://careers.example.test",
        ]);
        $this->assertNotEmpty($id);
        $email = $transport->messages()->first()->getOriginalMessage();
        $this->assertStringContainsString('Hello &lt;candidate&gt;', $email->getHtmlBody());
        $this->assertStringContainsString('cid:apec-homes-logo@recruitment', $email->getHtmlBody());
        $this->assertStringContainsString('Hello <candidate>', $email->getTextBody());
        $this->assertCount(1, $email->getAttachments());
    }

    public function test_sending_requires_explicit_environment_configuration(): void
    {
        config(['recruitment.mail_enabled' => false]);
        $this->assertFalse(MailTransport::cifSmtpAvailability()['sending_enabled']);
        $this->expectException(\RuntimeException::class);
        LaravelMailTransport::send([]);
    }
}
