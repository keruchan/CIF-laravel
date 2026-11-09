<?php

use App\Services\Recruitment\MrfImportStaging;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('recruitment:prune-imports', function () {
    $this->info(MrfImportStaging::prune().' expired upload reviews removed.');
})->purpose('Remove expired private temporary MRF uploads');

Schedule::command('recruitment:prune-imports')->hourly();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
