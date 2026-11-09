<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use DateTimeImmutable;
use DateTimeZone;

final class CandidateSchedule
{
    /** Choices come from released facts, never another stage's unpublished result. */
    public static function cifNextPhaseOptions(array $facts, string $releasedStage): array
    {
        $choices = [];
        foreach (CandidatePublication::cifProgressFromPublicFacts($facts)['steps'] as $step) {
            if ($step['key'] === $releasedStage || $step['key'] === 'cif' || ! in_array($step['state'], ['current', 'under-review'], true)) {
                continue;
            }
            $choices[] = ['key' => $step['key'], 'label' => $step['label']];
        }

        return $choices;
    }

    public static function cifApplyNextSchedule(array $facts, string $releasedStage, array $input): array
    {
        $choices = array_column(CandidateSchedule::cifNextPhaseOptions($facts, $releasedStage), 'key');
        if (! array_key_exists('next_phase', $input) && ! array_key_exists('next_datetime', $input)) {
            if (! in_array($facts['next_schedule']['stage'] ?? null, $choices, true)) {
                $facts['next_schedule'] = null;
            }

            return $facts;
        }
        $stage = $input['next_phase'] ?? '';
        $value = $input['next_datetime'] ?? '';
        if (! is_string($stage) || ! is_string($value)) {
            throw new \InvalidArgumentException('Choose a next phase and a valid date and time.');
        }
        if ($stage === '') {
            if ($value !== '') {
                throw new \InvalidArgumentException('Choose the next phase for this schedule.');
            }
            $facts['next_schedule'] = null;

            return $facts;
        }
        if (! in_array($stage, $choices, true)) {
            throw new \InvalidArgumentException('Choose an available next phase.');
        }
        $utc = null;
        if ($value !== '') {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $value)) {
                throw new \InvalidArgumentException('Enter a valid date and time for the next phase.');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone('Asia/Manila'));
            if (! $date || $date->format('Y-m-d\TH:i') !== $value || (int) $date->format('Y') < 2000 || (int) $date->format('Y') > 2100) {
                throw new \InvalidArgumentException('Enter a valid date and time for the next phase.');
            }
            $utc = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
        $facts['next_schedule'] = ['stage' => $stage, 'scheduled_at' => $utc, 'time_zone' => 'Asia/Manila'];

        return $facts;
    }

    public static function cifPublicNextSchedule(array $facts): ?array
    {
        $schedule = $facts['next_schedule'] ?? null;
        if (! is_array($schedule) || ! isset(HrStaff::cifHrStages()[$schedule['stage'] ?? ''])) {
            return null;
        }

        return ['stage' => $schedule['stage'], 'label' => HrStaff::cifHrStages()[$schedule['stage']], 'scheduled_at' => $schedule['scheduled_at'] ?? null, 'time_zone' => 'Asia/Manila'];
    }

    public static function cifScheduleEmailSummary(?array $schedule): string
    {
        if (! $schedule) {
            return '';
        }
        $when = $schedule['scheduled_at'] ? (new DateTimeImmutable($schedule['scheduled_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y · h:i A').' (Philippine time)' : 'Date and time to be confirmed';

        return 'Next phase: '.$schedule['label']."\nSchedule: ".$when;
    }
}
