<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class DashboardCoverage
{
    public static function cifDashboardCoverage(array $input, string $prefix = ''): ?array
    {
        $fromKey = $prefix.'date_from';
        $toKey = $prefix.'date_to';
        if (! array_key_exists($fromKey, $input) && ! array_key_exists($toKey, $input)) {
            return null;
        }
        if (($input[$fromKey] ?? null) === '' && ($input[$toKey] ?? null) === '') {
            return ['all_dates' => true, 'date_from' => '', 'date_to' => '', 'label' => 'All dates', 'days' => null];
        }
        $zone = new DateTimeZone('Asia/Manila');
        $dates = [];
        foreach ([$fromKey, $toKey] as $key) {
            $value = $input[$key] ?? null;
            if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) || $value < '1900-01-01' || $value > '9998-12-31') {
                throw new InvalidArgumentException('Choose valid From and To dates.');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $zone);
            if (! $date || $date->format('Y-m-d') !== $value) {
                throw new InvalidArgumentException('Choose valid From and To dates.');
            }
            $dates[] = $date;
        }
        [$from,$to] = $dates;
        if ($from > $to) {
            throw new InvalidArgumentException('From must be on or before To.');
        }
        if ($to >= $from->modify('+5 years')) {
            throw new InvalidArgumentException('Choose coverage of up to five years.');
        }

        return ['date_from' => $from->format('Y-m-d'), 'date_to' => $to->format('Y-m-d'),
            'utc_from' => $from->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'utc_to' => $to->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'date_end' => $to->modify('+1 day')->format('Y-m-d'),
            'label' => $from->format('M j, Y').' – '.$to->format('M j, Y'), 'days' => (int) $from->diff($to)->format('%a') + 1];
    }
}
