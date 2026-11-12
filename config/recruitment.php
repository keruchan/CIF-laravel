<?php

return [
    'public_origin' => env('CIF_PUBLIC_ORIGIN', env('APP_URL', 'http://localhost')),
    'mail_enabled' => env('CIF_MAIL_ENABLED', false),
    'pdftotext' => env('CIF_PDFTOTEXT'),
    'privacy_dpo_email' => env('CIF_PRIVACY_DPO_EMAIL', 'careers@apechomes.com'),
];
