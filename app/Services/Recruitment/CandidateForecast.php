<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class CandidateForecast
{
    /** Additive Holt-Winters: monthly level, trend and a 12-month seasonal cycle.
     * Zero-count months are valid. Predictions are never persisted as observations.
     */
    public static function cifHoltWinters(array $observations, int $horizon): ?array
    {
        $values = array_values($observations);
        $period = 12;
        if (count($values) < 2 * $period || $horizon < 1) {
            return null;
        }
        foreach ($values as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value) || $value < 0) {
                return null;
            }
        }
        $firstMean = array_sum(array_slice($values, 0, $period)) / $period;
        $secondMean = array_sum(array_slice($values, $period, $period)) / $period;
        $initialTrend = ($secondMean - $firstMean) / $period;
        $initialLevel = $firstMean + ($period - 1) / 2 * $initialTrend;
        $initialSeasons = [];
        for ($i = 0; $i < $period; $i++) {
            $initialSeasons[$i] = $values[$i] - ($firstMean + ($i - ($period - 1) / 2) * $initialTrend);
        }
        $best = null;
        // Deterministic bounded grid search on one-step errors. When more than two
        // years exist, score only observations after the initialization years.
        // This is parameter fitting, not a claim of independently tested accuracy.
        foreach ([0.3, 0.1, 0.5, 0.7, 0.9] as $alpha) {
            foreach ([0.1, 0.0, 0.3, 0.5, 0.7] as $beta) {
                foreach ([0.2, 0.0, 0.4, 0.6, 0.8] as $gammaStar) {
                    $level = $initialLevel;
                    $trend = $initialTrend;
                    $seasons = $initialSeasons;
                    $error = 0.0;
                    for ($i = $period; $i < count($values); $i++) {
                        $slot = $i % $period;
                        $priorLevel = $level;
                        $priorTrend = $trend;
                        $season = $seasons[$slot];
                        $prediction = $priorLevel + $priorTrend + $season;
                        if (count($values) === 24 || $i >= 24) {
                            $error += ($values[$i] - $prediction) ** 2;
                        }
                        $level = $alpha * ($values[$i] - $season) + (1 - $alpha) * ($priorLevel + $priorTrend);
                        $trend = $beta * ($level - $priorLevel) + (1 - $beta) * $priorTrend;
                        $seasons[$slot] = $gammaStar * ($values[$i] - $level) + (1 - $gammaStar) * $season;
                    }
                    if ($best === null || $error < $best['error'] - 1.0E-9) {
                        $best = compact('level', 'trend', 'seasons', 'alpha', 'beta', 'gammaStar', 'error');
                    }
                }
            }
        }
        $level = $best['level'];
        $trend = $best['trend'];
        $seasons = $best['seasons'];
        $predictions = [];
        for ($step = 0; $step < $horizon; $step++) {
            $slot = (count($values) + $step) % $period;
            // Advance the expected state recursively: October's projected level
            // feeds November's level, then December's. Feeding a model's own expected
            // observation back into its smoothing equations has zero innovation.
            // Keep full precision internally, rather than accumulating rounded or
            // clipped fake observations and mistaking them for newly collected data.
            $level += $trend;
            $predictions[] = round(max(0.0, $level + $seasons[$slot]), 2);
        }

        return ['values' => $predictions, 'parameters' => ['alpha' => $best['alpha'], 'beta' => $best['beta'], 'gamma_star' => $best['gammaStar']], 'seasonal_period' => $period, 'training_count' => count($values)];
    }
}
