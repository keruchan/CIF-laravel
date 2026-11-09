<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use PDO;

final class HrKpi
{
    public static function cifKpiBlank(): array
    {
        return ['needed' => 0, 'filled' => 0, 'hires' => 0, 'days_total' => 0, 'timing_count' => 0, 'ratio_total' => 0.0, 'excluded' => 0, 'overdue' => 0, 'invalid_dates' => 0, 'overfilled' => 0];
    }

    public static function cifKpiFinish(array $metric): array
    {
        $metric['fill_rate'] = $metric['needed'] ? round(100 * $metric['filled'] / $metric['needed'], 2) : null;
        $metric['average_days'] = $metric['timing_count'] ? round($metric['days_total'] / $metric['timing_count'], 2) : null;
        $metric['timeliness_rate'] = $metric['timing_count'] ? round($metric['ratio_total'] / $metric['timing_count'], 2) : null;
        unset($metric['days_total'],$metric['ratio_total']);

        return $metric;
    }

    public static function cifKpiCoverage(array $input, DateTimeImmutable $today, ?PDO $pdo = null): array
    {
        $period = $input['period'] ?? 'monthly';
        $year = $input['year'] ?? (int) $today->format('Y');
        $month = $input['month'] ?? (int) $today->format('n');
        if (! in_array($period, ['weekly', 'monthly', 'yearly'], true) || ! is_int($year) || $year < 1900 || $year > 9998 || ! is_int($month) || $month < 1 || $month > 12) {
            throw new InvalidArgumentException('Choose a valid weekly, monthly or yearly view.');
        }
        $explicit = array_key_exists('date_from', $input) || array_key_exists('date_to', $input);
        $allDates = ($input['date_from'] ?? null) === '' && ($input['date_to'] ?? null) === '';
        if ($allDates) {
            if ($pdo === null) {
                throw new LogicException('All-date KPI coverage requires a database connection.');
            }
            // Bound chart buckets to actual history and future targets, not thousands of empty years.
            $q = $pdo->prepare("SELECT MIN(day) AS first_day,MAX(day) AS last_day FROM (
            SELECT MIN(hiring_due_date) AS day FROM manpower_request_approvals WHERE decision='Approved'
            UNION ALL SELECT MAX(hiring_due_date) FROM manpower_request_approvals WHERE decision='Approved'
            UNION ALL SELECT MIN(DATE(DATE_ADD(submitted_at,INTERVAL 8 HOUR))) FROM candidates
            UNION ALL SELECT MAX(DATE(DATE_ADD(submitted_at,INTERVAL 8 HOUR))) FROM candidates
            UNION ALL SELECT CAST(? AS DATE)
        ) dates WHERE day BETWEEN CAST('1900-01-01' AS DATE) AND CAST('9998-12-31' AS DATE)");
            $q->execute([$today->format('Y-m-d')]);
            $bounds = $q->fetch(PDO::FETCH_ASSOC);
            $from = new DateTimeImmutable($bounds['first_day'], $today->getTimezone());
            $end = new DateTimeImmutable($bounds['last_day'], $today->getTimezone());
            $to = $end->modify('+1 day');
        } elseif ($explicit) {
            $dates = [];
            foreach (['date_from', 'date_to'] as $field) {
                $value = $input[$field] ?? null;
                if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) || $value < '1900-01-01' || $value > '9998-12-31') {
                    throw new InvalidArgumentException('Enter valid From and To dates.');
                }
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $today->getTimezone());
                if (! $date || $date->format('Y-m-d') !== $value) {
                    throw new InvalidArgumentException('Enter real calendar dates for the coverage.');
                }
                $dates[$field] = $date;
            }
            $from = $dates['date_from'];
            $end = $dates['date_to'];
            if ($from > $end) {
                throw new InvalidArgumentException('From must be on or before To.');
            }
            $to = $end->modify('+1 day');
            if ($to > $from->modify('+5 years')) {
                throw new InvalidArgumentException('Choose coverage of up to five years.');
            }
        } elseif ($period === 'weekly') {
            $from = $today->modify('-'.((int) $today->format('N') - 1).' days');
            $to = $from->modify('+7 days');
            $end = $to->modify('-1 day');
        } else {
            $from = $today->setDate($year, $period === 'monthly' ? $month : 1, 1);
            $to = $from->modify($period === 'monthly' ? '+1 month' : '+1 year');
            $end = $to->modify('-1 day');
        }
        $label = $allDates ? 'All dates' : $from->format('M j, Y').($from == $end ? '' : ' – '.$end->format('M j, Y'));

        return ['from' => $from, 'to' => $to, 'explicit' => $explicit, 'all_dates' => $allDates, 'period' => $period, 'year' => $year, 'month' => $month, 'label' => $label, 'end' => $end];
    }

    public static function cifKpiBucketStart(DateTimeImmutable $date, string $period): DateTimeImmutable
    {
        return match ($period) {
            'weekly' => $date->modify('-'.((int) $date->format('N') - 1).' days'),
            'monthly' => $date->setDate((int) $date->format('Y'), (int) $date->format('n'), 1),
            default => $date->setDate((int) $date->format('Y'), 1, 1),
        };
    }

    public static function cifKpiBucketKey(DateTimeImmutable $date, string $period): string
    {
        return $date->format(match ($period) {
            'weekly' => 'o-\WW','monthly' => 'Y-m',default => 'Y'
        });
    }

    public static function cifKpiFileMetric(array $row, array $hires, DateTimeImmutable $today): array
    {
        $piece = HrKpi::cifKpiBlank();
        if (in_array($row['status'], ['On Hold', 'Cancelled', 'Rejected'], true)) {
            $piece['excluded'] = 1;

            return $piece;
        }
        $piece['needed'] = 1;
        $piece['filled'] = (int) (count($hires) > 0);
        $piece['hires'] = count($hires);
        $piece['overfilled'] = (int) (count($hires) > 1);
        $piece['overdue'] = (int) (! $hires && $row['hiring_due_date'] < $today->format('Y-m-d'));
        foreach ($hires as $hire) {
            if (! $hire['date_hired'] || ! $row['date_approved'] || (int) $row['lead_time_days'] < 1 || $hire['date_hired'] < $row['date_approved']) {
                $piece['invalid_dates']++;

                continue;
            }
            $days = (int) (new DateTimeImmutable($row['date_approved']))->diff(new DateTimeImmutable($hire['date_hired']))->format('%a');
            $piece['days_total'] += $days;
            $piece['timing_count']++;
            $piece['ratio_total'] += 100 * $days / (2 * (int) $row['lead_time_days']);
        }

        return $piece;
    }

    public static function cifKpiTableOptions(array $input): array
    {
        $options = ['search' => $input['table_search'] ?? '', 'status' => $input['table_status'] ?? '', 'filled' => $input['table_filled'] ?? '',
            'category' => $input['table_category'] ?? '', 'sort' => $input['table_sort'] ?? 'due', 'direction' => $input['table_direction'] ?? 'asc', 'per_page' => 15];
        foreach (['search', 'status', 'filled', 'category', 'sort', 'direction'] as $key) {
            if (! is_string($options[$key])) {
                throw new InvalidArgumentException('Choose valid MRF table filters.');
            }
        }
        $options['search'] = trim($options['search']);
        if (mb_strlen($options['search']) > 254 || ! in_array($options['status'], ['', 'Pending', 'In Progress', 'Completed', 'Rejected', 'On Hold', 'Cancelled'], true)
            || ! in_array($options['filled'], ['', 'filled', 'unfilled'], true) || ! in_array($options['category'], ['', 'A', 'B', 'C-1', 'C-2', 'D'], true)
            || ! in_array($options['sort'], ['mrf', 'due', 'days'], true) || ! in_array($options['direction'], ['asc', 'desc'], true)
        ) {
            throw new InvalidArgumentException('Choose valid MRF table filters and sorting.');
        }

        return $options;
    }

    public static function cifKpiTableRows(array $rows, array $options): array
    {
        $rows = array_values(array_filter($rows, static function (array $row) use ($options): bool {
            if ($options['status'] !== '' && $row['status'] !== $options['status']) {
                return false;
            }
            if ($options['category'] !== '' && $row['position_category'] !== $options['category']) {
                return false;
            }
            if ($options['filled'] !== '' && $row['filled'] !== ($options['filled'] === 'filled')) {
                return false;
            }

            return $options['search'] === '' || mb_stripos(implode(' ', [$row['mrf_number'], $row['position_title'], $row['department_name'], $row['site_location'] ?? '', $row['requestor_name'] ?? '', $row['hr_name'] ?? 'Unassigned']), $options['search']) !== false;
        }));
        usort($rows, static function (array $a, array $b) use ($options): int {
            $key = $options['sort'];
            $value = static fn (array $row) => match ($key) {
                'mrf' => $row['mrf_number'],'due' => $row['hiring_due_date'],default => $row['filled'] ? $row['average_days'] : $row['days_open']
            };
            $left = $value($a);
            $right = $value($b);
            // Unknown timings always sort last, including descending order.
            if ($left === null && $right !== null) {
                return 1;
            }if ($right === null && $left !== null) {
                return -1;
            }
            $comparison = $left === null ? 0 : ($key === 'days' ? ($left <=> $right) : strnatcasecmp((string) $left, (string) $right));
            if ($comparison !== 0) {
                return $options['direction'] === 'asc' ? $comparison : -$comparison;
            }

            return strnatcasecmp($a['mrf_number'], $b['mrf_number']) ?: ($a['id'] <=> $b['id']);
        });

        return $rows;
    }

    public static function cifHrKpi(PDO $pdo, array $input, ?DateTimeImmutable $today = null, bool $paginate = true): array
    {
        $today = ($today ?? new DateTimeImmutable('today', new DateTimeZone('Asia/Shanghai')))->setTime(0, 0);
        $range = HrKpi::cifKpiCoverage($input, $today, $pdo);
        $period = $range['period'];
        $year = $range['year'];
        $month = $range['month'];
        $staffId = $input['staff_id'] ?? 0;
        $page = $input['page'] ?? 1;
        if (! is_int($staffId) || $staffId < 0 || ! is_int($page) || $page < 1) {
            throw new InvalidArgumentException('Choose a valid HR member and page.');
        }
        $tableOptions = HrKpi::cifKpiTableOptions($input);
        $staff = $pdo->query("SELECT a.id,a.name,a.email AS username,COALESCE(p.photo,'') AS photo FROM admins a LEFT JOIN hr_staff_profiles p ON p.admin_id=a.id WHERE a.role='hr' ORDER BY a.name,a.id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($staff as &$person) {
            $person['id'] = (int) $person['id'];
        } unset($person);
        if ($staffId && ! in_array($staffId, array_column($staff, 'id'), true)) {
            throw new InvalidArgumentException('Choose an existing HR member.');
        }
        $from = $range['from'];
        $to = $range['to'];
        $where = "ap.decision='Approved' AND ap.hiring_due_date>=? AND ap.hiring_due_date<?";
        $params = [$from->format('Y-m-d'), $to->format('Y-m-d')];
        if ($staffId) {
            $where .= ' AND o.hr_admin_id=?';
            $params[] = $staffId;
        }
        $q = $pdo->prepare("SELECT m.id,m.mrf_number,m.position_title,m.department_name,m.site_location,m.requestor_name,m.status,ap.date_approved,ap.position_category,ap.lead_time_days,ap.hiring_due_date,o.hr_admin_id,a.name AS hr_name FROM manpower_requests m JOIN manpower_request_approvals ap ON ap.batch_number=m.batch_number LEFT JOIN mrf_hr_assignments o ON o.request_id=m.id LEFT JOIN admins a ON a.id=o.hr_admin_id WHERE $where ORDER BY ap.hiring_due_date,m.mrf_number,m.id");
        $q->execute($params);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        // Join completed deployments only. Candidate archives do not erase historical hires.
        $q = $pdo->prepare("SELECT d.request_id,d.candidate_id,d.date_hired FROM candidate_deployments d JOIN manpower_requests m ON m.id=d.request_id JOIN manpower_request_approvals ap ON ap.batch_number=m.batch_number LEFT JOIN mrf_hr_assignments o ON o.request_id=m.id WHERE d.completed_at IS NOT NULL AND $where ORDER BY d.date_hired IS NULL,d.date_hired,d.candidate_id");
        $q->execute($params);
        $hires = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $hire) {
            $hires[$hire['request_id']][] = $hire;
        }
        $metrics = [];
        foreach ($staff as $person) {
            if (! $staffId || $staffId === $person['id']) {
                unset($person['photo']);
                $metrics[$person['id']] = $person + HrKpi::cifKpiBlank();
            }
        }
        $totals = HrKpi::cifKpiBlank();
        $unassigned = HrKpi::cifKpiBlank();
        $unassignedFiles = 0;
        foreach ($rows as &$row) {
            $owner = $row['hr_admin_id'] === null ? null : (int) $row['hr_admin_id'];
            $row['hr_admin_id'] = $owner;
            $row['id'] = (int) $row['id'];
            $row['hires'] = count($hires[$row['id']] ?? []);
            $row['eligible'] = ! in_array($row['status'], ['On Hold', 'Cancelled', 'Rejected'], true);
            $row['filled'] = $row['hires'] > 0;
            $row['first_hire_date'] = $hires[$row['id']][0]['date_hired'] ?? null;
            $row['average_days'] = null;
            $row['days_open'] = $row['date_approved'] && $row['date_approved'] <= $today->format('Y-m-d') ? (int) (new DateTimeImmutable($row['date_approved']))->diff($today)->format('%a') : null;
            $piece = HrKpi::cifKpiFileMetric($row, $hires[$row['id']] ?? [], $today);
            $row['average_days'] = $piece['timing_count'] ? round($piece['days_total'] / $piece['timing_count'], 2) : null;
            if ($owner === null) {
                $unassignedFiles++;
            }
            foreach ($piece as $key => $value) {
                $totals[$key] += $value;
                if ($owner === null) {
                    $unassigned[$key] += $value;
                } elseif (isset($metrics[$owner])) {
                    $metrics[$owner][$key] += $value;
                }
            }
        } unset($row);
        $missingWhere = "ap.decision='Approved' AND (ap.hiring_due_date IS NULL OR ap.date_approved IS NULL OR ap.lead_time_days IS NULL)";
        $missingParams = [];
        if ($staffId) {
            $missingWhere .= ' AND o.hr_admin_id=?';
            $missingParams[] = $staffId;
        }
        $q = $pdo->prepare("SELECT COUNT(*) FROM manpower_requests m JOIN manpower_request_approvals ap ON ap.batch_number=m.batch_number LEFT JOIN mrf_hr_assignments o ON o.request_id=m.id WHERE $missingWhere");
        $q->execute($missingParams);
        $missing = (int) $q->fetchColumn();
        $years = array_map('intval', $pdo->query('SELECT DISTINCT YEAR(hiring_due_date) FROM manpower_request_approvals WHERE hiring_due_date IS NOT NULL ORDER BY YEAR(hiring_due_date) DESC')->fetchAll(PDO::FETCH_COLUMN));
        $years = array_values(array_unique(array_merge([(int) $today->format('Y'), $year], $years)));
        rsort($years);
        $scopeTotal = count($rows);
        $rows = HrKpi::cifKpiTableRows($rows, $tableOptions);
        $total = count($rows);
        $perPage = $tableOptions['per_page'];
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $coverage = ['date_from' => $range['all_dates'] ? '' : $from->format('Y-m-d'), 'date_to' => $range['all_dates'] ? '' : $range['end']->format('Y-m-d'), 'all_dates' => $range['all_dates'], 'label' => $range['label'], 'days' => (int) $from->diff($to)->format('%a'), 'timezone' => $today->getTimezone()->getName()];

        return ['staff' => $staff, 'metrics' => array_map([HrKpi::class, 'cifKpiFinish'], array_values($metrics)), 'totals' => HrKpi::cifKpiFinish($totals), 'unassigned' => HrKpi::cifKpiFinish($unassigned), 'unassigned_files' => $unassignedFiles, 'missing_timing' => $missing, 'rows' => $paginate ? array_slice($rows, ($page - 1) * $perPage, $perPage) : $rows, 'total' => $total, 'scope_total' => $scopeTotal, 'per_page' => $perPage, 'table_filters' => $tableOptions, 'page' => $page, 'pages' => $pages, 'years' => $years, 'filters' => ['period' => $period, 'year' => $year, 'month' => $month, 'staff_id' => $staffId, 'date_from' => $coverage['date_from'], 'date_to' => $coverage['date_to']], 'coverage' => $coverage, 'period_label' => $range['explicit'] || $period === 'weekly' ? $range['label'] : ($period === 'monthly' ? $from->format('F Y') : $from->format('Y')), 'today' => $today->format('Y-m-d')];
    }

    public static function cifHrKpiCharts(PDO $pdo, array $input, ?DateTimeImmutable $today = null): array
    {
        $today = ($today ?? new DateTimeImmutable('today', new DateTimeZone('Asia/Shanghai')))->setTime(0, 0);
        $summary = HrKpi::cifHrKpi($pdo, $input, $today); // Reuse filter validation and authenticated HR ownership rules.
        $range = HrKpi::cifKpiCoverage($input, $today, $pdo);
        $filters = $summary['filters'];
        $year = $filters['year'];
        $period = $filters['period'];
        $monthly = $period === 'monthly';
        $owner = $filters['staff_id'];
        $custom = $range['explicit'] || $period === 'weekly';
        $firstYear = $monthly ? $year : max(1900, $year - 4);
        $from = $custom ? $range['from'] : $today->setDate($firstYear, 1, 1);
        $to = $custom ? $range['to'] : $today->setDate($year + 1, 1, 1);
        $buckets = [];
        $interval = match ($period) {
            'weekly' => '+7 days','monthly' => '+1 month',default => '+1 year'
        };
        for ($date = HrKpi::cifKpiBucketStart($from, $period); $date < $to; $date = $date->modify($interval)) {
            $key = HrKpi::cifKpiBucketKey($date, $period);
            $next = $date->modify($interval);
            $bucketFrom = max($date, $from);
            $bucketTo = min($next, $to)->modify('-1 day');
            $label = $period === 'weekly' ? 'W'.$date->format('W') : $date->format($monthly ? 'M' : 'Y');
            $selected = $custom ? ($date <= $today && $next > $today) : ($monthly ? (int) $date->format('n') === $filters['month'] : (int) $date->format('Y') === $year);
            $buckets[$key] = ['key' => $key, 'label' => $label, 'selected' => $selected, 'date_from' => $bucketFrom->format('Y-m-d'), 'date_to' => $bucketTo->format('Y-m-d'), 'coverage_label' => $bucketFrom->format('M j, Y').' – '.$bucketTo->format('M j, Y')] + HrKpi::cifKpiBlank();
        }
        $where = "ap.decision='Approved' AND ap.hiring_due_date>=? AND ap.hiring_due_date<?";
        $params = [$from->format('Y-m-d'), $to->format('Y-m-d')];
        if ($owner) {
            $where .= ' AND o.hr_admin_id=?';
            $params[] = $owner;
        }
        $q = $pdo->prepare("SELECT m.id,m.status,ap.date_approved,ap.hiring_due_date,ap.lead_time_days FROM manpower_requests m JOIN manpower_request_approvals ap ON ap.batch_number=m.batch_number LEFT JOIN mrf_hr_assignments o ON o.request_id=m.id WHERE $where");
        $q->execute($params);
        $files = $q->fetchAll(PDO::FETCH_ASSOC);
        $q = $pdo->prepare("SELECT d.request_id,d.date_hired FROM candidate_deployments d JOIN manpower_requests m ON m.id=d.request_id JOIN manpower_request_approvals ap ON ap.batch_number=m.batch_number LEFT JOIN mrf_hr_assignments o ON o.request_id=m.id WHERE d.completed_at IS NOT NULL AND $where");
        $q->execute($params);
        $hires = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $hire) {
            $hires[$hire['request_id']][] = $hire;
        }
        foreach ($files as $file) {
            $key = HrKpi::cifKpiBucketKey(new DateTimeImmutable($file['hiring_due_date'], $today->getTimezone()), $period);
            foreach (HrKpi::cifKpiFileMetric($file, $hires[$file['id']] ?? [], $today) as $metric => $value) {
                $buckets[$key][$metric] += $value;
            }
        }
        $trend = ['period' => $period, 'label' => $custom ? $range['label'] : ($monthly ? (string) $year : $firstYear.'–'.$year), 'points' => array_map([self::class, 'cifKpiFinish'], array_values($buckets))];

        // Funnel is a submission cohort, not a due-date target cohort. Boundaries are local
        // calendar dates converted to UTC, matching how candidate submission timestamps are stored.
        $appliedFrom = $range['from'];
        $appliedTo = $range['to'];
        $params = [$appliedFrom->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), $appliedTo->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')];
        $ownerWhere = '';
        if ($owner) {
            $ownerWhere = ' AND o.hr_admin_id=?';
            $params[] = $owner;
        }
        $q = $pdo->prepare("SELECT c.id,iv.completed_at AS interviewed_at,iv.decision AS interview_decision,
        offer.completed_at AS offered_at,offer.decision AS offer_decision,d.completed_at AS hired_at
        FROM candidates c
        LEFT JOIN candidate_interview_stages iv ON iv.candidate_id=c.id AND iv.stage='initial_interview'
        LEFT JOIN candidate_interview_stages offer ON offer.candidate_id=c.id AND offer.stage='job_offer'
        LEFT JOIN candidate_deployments d ON d.candidate_id=c.id
        LEFT JOIN candidate_admin_revisions r ON r.id=(SELECT MAX(latest.id) FROM candidate_admin_revisions latest WHERE latest.candidate_id=c.id)
        LEFT JOIN candidate_mrf_assignments link ON link.revision_id=r.id AND link.candidate_id=c.id
        LEFT JOIN mrf_hr_assignments o ON o.request_id=COALESCE(d.request_id,link.request_id)
        WHERE c.submitted_at>=? AND c.submitted_at<? $ownerWhere");
        $q->execute($params);
        $counts = ['applied' => 0, 'interviewed' => 0, 'offers' => 0, 'accepted' => 0, 'hires' => 0];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
            $counts['applied']++;
            // A saved draft, no response, or pre-interview backout is not an interview.
            if (! $candidate['interviewed_at'] || in_array($candidate['interview_decision'], ['No Response', 'Backout'], true)) {
                continue;
            }
            $counts['interviewed']++;
            if (! $candidate['offered_at']) {
                continue;
            } $counts['offers']++;
            if (! in_array($candidate['offer_decision'], ['Accepted', 'Passed'], true)) {
                continue;
            } $counts['accepted']++;
            if ($candidate['hired_at']) {
                $counts['hires']++;
            }
        }
        $stages = [];
        $previous = null;
        foreach (['applied' => 'Applied', 'interviewed' => 'Interviewed', 'offers' => 'Got offers', 'accepted' => 'Job offer accepted', 'hires' => 'Hired'] as $key => $label) {
            $count = $counts[$key];
            $stages[] = ['key' => $key, 'label' => $label, 'count' => $count, 'percent_applied' => $counts['applied'] ? round(100 * $count / $counts['applied'], 2) : null, 'percent_previous' => $previous ? round(100 * $count / $previous, 2) : null];
            $previous = $count;
        }

        return ['filters' => $filters, 'coverage' => $summary['coverage'], 'trend' => $trend, 'funnel' => ['period_label' => $summary['period_label'], 'stages' => $stages, 'total' => $counts['applied']]];
    }
}
