<?php

namespace App\Services\Recruitment;

final class RecruitmentSession
{
    public static function shift(string $key): void
    {
        $items = session()->get($key, []);
        array_shift($items);
        session()->put($key, $items);
    }
}
