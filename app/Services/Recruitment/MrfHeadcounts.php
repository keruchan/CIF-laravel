<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class MrfHeadcounts
{
    public static function mrfBatchNumber(int $year, int $sequence): string
    {
        return $year.'-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
    }

    public static function mrfHeadcountNumber(string $batch, int $index): string
    {
        return $batch.'-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    }
}
