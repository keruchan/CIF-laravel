<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use DateTimeImmutable;
use DateTimeZone;

final class MrfApproval
{
    public static function cifMrfPositionCategories(): array
    {
        return ['A' => 30, 'B' => 35, 'C-1' => 40, 'C-2' => 45, 'D' => 60];
    }

    /** Management's signature date is distinct from HR's acknowledgement timestamp. */
    public static function cifMrfApprovalTiming(array $input, ?DateTimeImmutable $today = null): array
    {
        $today = ($today ?? new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->setTime(0, 0);
        $value = $input['date_approved'] ?? null;
        $category = $input['position_category'] ?? null;
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            throw new \InvalidArgumentException('Enter the date management signed the MRF approval.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $today->getTimezone());
        if (! $date || $date->format('Y-m-d') !== $value || $value < '1900-01-01' || $date > $today) {
            throw new \InvalidArgumentException('Date Approved must be a real date, today or earlier.');
        }
        $categories = MrfApproval::cifMrfPositionCategories();
        if (! is_string($category) || ! isset($categories[$category])) {
            throw new \InvalidArgumentException('Select position category A, B, C-1, C-2 or D.');
        }

        return ['date_approved' => $value, 'position_category' => $category, 'lead_time_days' => $categories[$category], 'hiring_due_date' => $date->modify('+'.$categories[$category].' days')->format('Y-m-d')];
    }
}
