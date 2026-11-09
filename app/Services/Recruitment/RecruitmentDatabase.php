<?php

namespace App\Services\Recruitment;

use Illuminate\Support\Facades\DB;
use PDO;

final class RecruitmentDatabase
{
    public static function pdo(): PDO
    {
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        return $pdo;
    }
}
