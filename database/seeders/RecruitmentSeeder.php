<?php

namespace Database\Seeders;

use App\Services\Recruitment\HrEmail;
use App\Services\Recruitment\RecruitmentDatabase;
use Illuminate\Database\Seeder;

class RecruitmentSeeder extends Seeder
{
    public function run(): void
    {
        // Add built-ins and refresh only the untouched legacy invitation body.
        // Preserve HR customizations, accounts and recruitment records.
        HrEmail::cifMailSeed(RecruitmentDatabase::pdo());
    }
}
