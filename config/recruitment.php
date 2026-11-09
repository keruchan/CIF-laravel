<?php

return [
    'public_origin' => env('CIF_PUBLIC_ORIGIN', env('APP_URL', 'http://localhost')),
    'mail_enabled' => env('CIF_MAIL_ENABLED', false),
    'pdftotext' => env('CIF_PDFTOTEXT'),
];
