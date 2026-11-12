<?php

namespace App\Services\Recruitment;

final class ApplicantPrivacy
{
    public static function declarations(): array
    {
        $company = 'The New Apec Development Corporation and its affiliates';

        return [
            'data_privacy' => 'I agree to the collection, processing, and retention of my personal and sensitive personal information by '.$company.' for recruitment and evaluation purposes, in compliance with the Philippine Data Privacy Act of 2012 (R.A. 10173). I understand that my information will be handled confidentially and used solely to assess my suitability for employment.',
            'background' => 'I hereby authorize '.$company.' and their authorized representatives to verify the information I provided, contact my character references, and conduct necessary background and reference checks to validate my academic, employment, and legal records. The timing of these checks, including any contact with my current employer, is subject to my prior approval or go-signal, as I may not yet have formally submitted my resignation. This authorization does not permit checks or contact with my current employer before I have approved the timing.',
        ];
    }

    public static function notice(): string
    {
        return view('recruitment.candidate.privacy-notice', [
            'dpoEmail' => config('recruitment.privacy_dpo_email', ''),
            'privacyDeclarations' => self::declarations(),
        ])->render();
    }

    public static function version(): string
    {
        return hash('sha256', self::notice());
    }

    public static function accepted(?array $consent): bool
    {
        return is_string($consent['version'] ?? null)
            && hash_equals(self::version(), $consent['version'])
            && ($consent['data_privacy_accepted'] ?? false) === true
            && ($consent['background_authorized'] ?? false) === true
            && is_string($consent['accepted_at'] ?? null)
            && $consent['accepted_at'] !== '';
    }

    public static function choices(?array $consent): array
    {
        $current = is_string($consent['version'] ?? null) && hash_equals(self::version(), $consent['version']);

        return ['data_privacy' => $current && ($consent['data_privacy_accepted'] ?? false) === true,
            'background' => $current && ($consent['background_authorized'] ?? false) === true];
    }

    public static function fromDraft(?string $json): ?array
    {
        $form = $json === null ? [] : json_decode($json, true, 32, JSON_THROW_ON_ERROR);

        return is_array($form['privacy_notice'] ?? null) ? $form['privacy_notice'] : null;
    }
}
