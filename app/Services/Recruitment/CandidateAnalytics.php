<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;

final class CandidateAnalytics
{
    public static function cifAnalyticsPeriodStart(DateTimeImmutable $date, string $interval): DateTimeImmutable
    {
        return match ($interval) {
            'daily' => $date->setTime(0, 0),
            'weekly' => $date->modify('monday this week')->setTime(0, 0),
            'yearly' => $date->setDate((int) $date->format('Y'), 1, 1)->setTime(0, 0),
            default => $date->modify('first day of this month')->setTime(0, 0),
        };
    }

    public static function cifAnalyticsPeriodKey(DateTimeImmutable $date, string $interval): string
    {
        return CandidateAnalytics::cifAnalyticsPeriodStart($date, $interval)->format(match ($interval) {
            'yearly' => 'Y', 'monthly' => 'Y-m', default => 'Y-m-d',
        });
    }

    public static function cifAnalyticsRows(PDO $pdo): array
    {
        $analyticsFields = array_filter(CifFields::cifScalarFields(), static fn ($field) => $field['type'] !== 'photo');
        $fields = implode(', ', array_map(static fn ($name) => 'd.`'.$name.'`', array_keys($analyticsFields)));
        $query = $pdo->query("SELECT c.id, c.first_name, c.last_name, c.position, c.status, c.submitted_at, JSON_REMOVE(c.form_data, '$.id_photo') AS form_data, d.certification_accepted, d.background_consent_accepted, $fields, JSON_REMOVE(r.form_data, '$.id_photo') AS revised_form, r.hr_data FROM candidates c LEFT JOIN candidate_details d ON d.candidate_id=c.id LEFT JOIN candidate_admin_revisions r ON r.id=(SELECT MAX(r2.id) FROM candidate_admin_revisions r2 WHERE r2.candidate_id=c.id) WHERE c.archived_at IS NULL");
        $rows = [];
        $today = new DateTimeImmutable('today');
        foreach ($query as $row) {
            $form = json_decode($row['form_data'], true) ?: [];
            if ($row['certification_accepted'] !== null) {
                foreach ($analyticsFields as $name => $definition) {
                    $form[$name] = $row[$name];
                }
                $form['consent'] = (bool) $row['certification_accepted'];
                $form['background_consent'] = (bool) $row['background_consent_accepted'];
            }
            if ($row['revised_form'] !== null) {
                $form = array_replace($form, json_decode($row['revised_form'], true) ?: []);
            }
            $value = static fn ($name) => trim((string) ($form[$name] ?? '')) ?: 'Not provided';
            $birth = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($form['dob'] ?? ''));
            $validBirth = $birth && $birth->format('Y-m-d') === ($form['dob'] ?? '') && $birth <= $today;
            $age = $validBirth ? $birth->diff($today)->y : null;
            $ageGroup = $age === null ? 'Not provided' : ($age < 21 ? 'Under 21' : ($age < 26 ? '21–25' : ($age < 31 ? '26–30' : ($age < 36 ? '31–35' : '36+'))));
            $salary = $form['expected_salary'] ?? '';
            $salaryGroup = $salary === '' || $salary === null || ! is_numeric($salary) || (float) $salary < 0 ? 'Not provided' : ((float) $salary < 15000 ? 'Below ₱15,000' : ((float) $salary < 25000 ? '₱15,000–24,999' : ((float) $salary < 40000 ? '₱25,000–39,999' : ((float) $salary < 60000 ? '₱40,000–59,999' : '₱60,000+'))));
            $requiredMissing = false;
            foreach (CifFields::cifScalarFields() as $name => $definition) {
                if ($definition['required'] && trim((string) ($form[$name] ?? '')) === '') {
                    $requiredMissing = true;
                }
            }
            if (empty($form['consent']) || empty($form['background_consent'])) {
                $requiredMissing = true;
            }
            $hr = json_decode($row['hr_data'] ?? '{}', true) ?: [];
            $answered = 0;
            $required = 0;
            foreach (HrFields::cifHrFields() as $fields) {
                foreach ($fields as $name => $label) {
                    if (HrFields::cifHrRequired($name)) {
                        $required++;
                        if (trim((string) ($hr[$name] ?? '')) !== '') {
                            $answered++;
                        }
                    }
                }
            }
            $submitted = new DateTimeImmutable($row['submitted_at'], new DateTimeZone('UTC'));
            $submitted = $submitted->setTimezone(new DateTimeZone('Asia/Shanghai'));
            $dimensions = [
                'status' => $row['status'], 'position' => trim((string) $row['position']) ?: 'Not provided',
                'sex' => in_array($value('sex'), ['Female', 'Male', 'Prefer not to say'], true) ? $value('sex') : 'Not provided',
                'age' => $ageGroup, 'civil_status' => $value('cs'), 'nationality' => $value('nat'),
                'education' => $value('edu'), 'course' => $value('crs'), 'employment' => $value('emp'),
                'province' => $value('prov'), 'city' => $value('city'), 'source' => $value('applied_via'),
                'salary' => $salaryGroup,
                'licensure' => $value('licensure_exam') === 'Not provided' ? 'Not provided' : 'Exam information provided',
                'cif_readiness' => $requiredMissing ? 'Missing required fields' : 'Required fields filled',
                'hr_readiness' => $answered === 0 ? 'Not started' : ($answered === $required ? 'Required answers filled' : 'In progress'),
                'month' => $submitted->format('Y-m'),
                'day' => $submitted->format('Y-m-d'), 'week' => CandidateAnalytics::cifAnalyticsPeriodKey($submitted, 'weekly'), 'year' => $submitted->format('Y'),
            ];
            $rows[] = ['id' => (int) $row['id'], 'name' => $row['last_name'].', '.$row['first_name'],
                'submitted_at' => $row['submitted_at'], 'submitted_date' => $submitted->format('Y-m-d'), 'dimensions' => $dimensions];
        }

        return $rows;
    }

    public static function cifAnalyticsScope(array $rows, string $range, string $position, ?array $coverage = null): array
    {
        if ($coverage['all_dates'] ?? false) {
            $coverage = null;
            $range = 'all';
        }
        $since = $range === 'all' ? null : (new DateTimeImmutable('today'))->modify('-'.((int) $range - 1).' days')->format('Y-m-d');

        return array_values(array_filter($rows, static fn ($row) => ($coverage !== null ? ($row['submitted_date'] >= $coverage['date_from'] && $row['submitted_date'] <= $coverage['date_to']) : ($since === null || $row['submitted_date'] >= $since))
            && ($position === '' || $row['dimensions']['position'] === $position)));
    }

    public static function cifAnalyticsSummary(array $rows, string $range, string $position, string $trendStatus = '', string $trendInterval = 'monthly', ?array $coverage = null): array
    {
        if ($coverage['all_dates'] ?? false) {
            $coverage = null;
            $range = 'all';
        }
        if (! in_array($trendInterval, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
            throw new InvalidArgumentException('Invalid timeline interval.');
        }
        $scope = CandidateAnalytics::cifAnalyticsScope($rows, $range, $position, $coverage);
        $charts = [];
        foreach (['status', 'position', 'source', 'sex', 'age', 'education', 'employment', 'province', 'salary', 'month'] as $field) {
            $charts[$field] = [];
        }
        foreach ($scope as $row) {
            foreach ($row['dimensions'] as $field => $value) {
                if (isset($charts[$field])) {
                    $charts[$field][$value] = ($charts[$field][$value] ?? 0) + 1;
                }
            }
        }
        foreach ($charts as &$counts) {
            arsort($counts);
        } unset($counts);
        foreach (['sex' => ['Female', 'Male', 'Prefer not to say', 'Not provided'], 'age' => ['Under 21', '21–25', '26–30', '31–35', '36+', 'Not provided'], 'salary' => ['Below ₱15,000', '₱15,000–24,999', '₱25,000–39,999', '₱40,000–59,999', '₱60,000+', 'Not provided'], 'status' => CandidateStatuses::cifCandidateStatuses()] as $field => $order) {
            $ordered = [];
            foreach ($order as $label) {
                $ordered[$label] = $charts[$field][$label] ?? 0;
            } $charts[$field] = $ordered;
        }
        $charts['month'] = [];
        $today = new DateTimeImmutable('today');
        $currentMonth = $today->format('Y-m');
        $startDate = $coverage['date_from'] ?? ($range === 'all' ? ($scope ? min(array_column($scope, 'submitted_date')) : $today->format('Y-m-d')) : $today->modify('-'.((int) $range - 1).' days')->format('Y-m-d'));
        $endDate = $coverage !== null ? min($coverage['date_to'], $today->format('Y-m-d')) : $today->format('Y-m-d');
        $end = new DateTimeImmutable($endDate);
        $endExclusive = $end->modify('+1 day');
        $start = new DateTimeImmutable(substr($startDate, 0, 7).'-01');
        for ($month = $start; $month->format('Y-m') <= $end->format('Y-m') && $startDate <= $endDate; $month = $month->modify('+1 month')) {
            $charts['month'][$month->format('Y-m')] = 0;
        }
        $periodStep = match ($trendInterval) {
            'daily' => '+1 day', 'weekly' => '+1 week', 'yearly' => '+1 year', default => '+1 month'
        };
        $series = [];
        $partialPeriods = [];
        for ($period = CandidateAnalytics::cifAnalyticsPeriodStart(new DateTimeImmutable($startDate), $trendInterval); $period <= $end && $startDate <= $endDate; $period = $period->modify($periodStep)) {
            $key = CandidateAnalytics::cifAnalyticsPeriodKey($period, $trendInterval);
            $series[$key] = 0;
            if ((($coverage !== null || $range !== 'all') && $period->format('Y-m-d') < $startDate) || $period->modify($periodStep) > $endExclusive || $period->modify($periodStep) > $today) {
                $partialPeriods[] = $key;
            }
        }
        $trendTotal = 0;
        foreach ($scope as $row) {
            if ($trendStatus !== '' && $row['dimensions']['status'] !== $trendStatus) {
                continue;
            }
            $month = $row['dimensions']['month'];
            if (isset($charts['month'][$month])) {
                $charts['month'][$month]++;
                $trendTotal++;
            }
            $key = CandidateAnalytics::cifAnalyticsPeriodKey(new DateTimeImmutable($row['submitted_date']), $trendInterval);
            if (array_key_exists($key, $series)) {
                $series[$key]++;
            }
        }
        $movingAverage = [];
        $window = [];
        $partialMonths = [];
        foreach ($charts['month'] as $month => $count) {
            $partial = $month === $currentMonth || (($coverage !== null || $range !== 'all') && $month === substr($startDate, 0, 7) && substr($startDate, 8) !== '01') || ($coverage !== null && (new DateTimeImmutable($month.'-01'))->modify('+1 month') > $endExclusive);
            if ($partial) {
                $partialMonths[] = $month;
                $window = [];
                $movingAverage[$month] = null;

                continue;
            }
            $window[] = $count;
            if (count($window) > 3) {
                array_shift($window);
            }
            $movingAverage[$month] = count($window) === 3 ? round(array_sum($window) / 3, 2) : null;
        }
        $training = array_filter($charts['month'], static fn ($month) => ! in_array($month, $partialMonths, true), ARRAY_FILTER_USE_KEY);
        $trainingMonths = array_keys($training);
        // The incomplete current month is projected as a bridge, not fitted as a
        // completed month. Preserve the chart's next-three-calendar-month horizon.
        $lastTraining = $trainingMonths ? new DateTimeImmutable($trainingMonths[count($trainingMonths) - 1].'-01') : null;
        $bridge = 1;
        if ($lastTraining) {
            $gap = $lastTraining->diff(new DateTimeImmutable($currentMonth.'-01'));
            $bridge = (int) $gap->format('%y') * 12 + (int) $gap->format('%m');
        }
        $model = $trendTotal > 0 ? CandidateForecast::cifHoltWinters($training, $bridge + 3) : null;
        $forecastAvailable = $model !== null;
        $forecast = [];
        for ($i = 1; $i <= 3; $i++) {
            $forecast[(new DateTimeImmutable('first day of this month'))->modify("+$i months")->format('Y-m')] = $model['values'][$bridge + $i - 1] ?? null;
        }
        usort($scope, static fn ($a, $b) => strcmp($b['submitted_at'], $a['submitted_at']) ?: $b['id'] <=> $a['id']);
        // Dashboard filters follow the workbook's applied-position choices even
        // when a valid position has no current candidates.
        $positions = HiringPositions::cifAppliedPositions();

        return ['total' => count($scope), 'charts' => $charts, 'positions' => $positions, 'recent' => array_slice($scope, 0, 5), 'range' => $range, 'position' => $position,
            'trend' => ['status' => $trendStatus, 'interval' => $trendInterval, 'series' => $series, 'partial_periods' => $partialPeriods,
                'period_field' => match ($trendInterval) {
                    'daily' => 'day', 'weekly' => 'week', 'yearly' => 'year', default => 'month'
                },
                'total' => $trendTotal, 'moving_average' => $movingAverage, 'window' => 3, 'partial_months' => $partialMonths,
                'forecast' => $forecast, 'forecast_training_months' => $trainingMonths, 'forecast_method' => 'Additive Holt-Winters', 'forecast_available' => $forecastAvailable,
                'forecast_seasonal_period' => 12, 'forecast_minimum_months' => 24, 'forecast_parameters' => $model['parameters'] ?? null]];
    }
}
