<?php

namespace App\Services\Recruitment;

final class MailConfiguration
{
    public static function cifMailConfiguration(): array
    {
        return [
            'enabled' => config('recruitment.mail_enabled') && config('mail.default') === 'smtp',
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'encryption' => config('mail.mailers.smtp.scheme') === 'smtps' ? 'ssl' : 'tls',
            'username' => config('mail.mailers.smtp.username'),
            'password' => config('mail.mailers.smtp.password'),
            'from_email' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];
    }

    public static function cifMailPortalUrl(): string
    {
        $origin = rtrim((string) config('recruitment.public_origin'), '/');

        return $origin !== '' && filter_var($origin, FILTER_VALIDATE_URL) && preg_match('~^https?://[^/]+$~D', $origin) ? $origin : 'http://localhost';
    }
}
