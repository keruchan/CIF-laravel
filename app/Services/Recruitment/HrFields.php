<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use DateTimeImmutable;

final class HrFields
{
    public static function cifHrFields(): array
    {
        return ['I. COGNITIVE SKILLS & ASSESSMENT' => ['abstract_reasoning_score' => '1. Abstract Reasoning', 'verbal_numerical_score' => '2. Verbal & Numerical Reasoning'], 'II. FLANAGAN INDUSTRIAL TEST' => ['fit_planning_score' => '1. Planning', 'fit_judgement_score' => '2. Judgement & Comprehension'], 'Disciplinary records' => ['habitual_tardiness' => 'Habitual tardiness', 'grave_misconduct' => 'Grave misconduct', 'negligence_of_duty' => 'Negligence of duty', 'bribery' => 'Bribery', 'pilferage_theft' => 'Pilferage / theft', 'connivance' => 'Connivance', 'work_abandonment' => 'Work abandonment / AWOL', 'dishonesty' => 'Dishonesty', 'breach_confidentiality' => 'Breach of confidentiality', 'disciplinary_others' => 'Others — disciplinary records'], 'Health & lifestyle information' => ['last_medical_examination' => 'Last medical examination', 'tattoo' => 'Tattoo', 'medical_condition' => 'Existing medical condition', 'alcohol_intake' => 'Alcohol intake', 'maintenance_medication' => 'Medication (maintenance)', 'drug_use_history' => 'History of drug use', 'allergies' => 'Allergies', 'gambling' => 'Gambling', 'transportation' => 'Mode of transportation', 'smoking' => 'Smoking', 'drivers_license_code' => 'Driver’s license restriction code', 'health_others' => 'Others — health & lifestyle'], 'Re-certification & HR notes' => ['recertification_name' => 'Re-certification — candidate’s typed name', 'recertification_date' => 'Re-certification date', 'hr_notes' => 'HR notes']];
    }

    public static function cifHrRequired(string $name): bool
    {
        return ! isset(HrFields::cifHrScoreFields()[$name]) && ! in_array($name, ['drivers_license_code', 'disciplinary_others', 'health_others', 'hr_notes'], true);
    }

    public static function cifHrScoreFields(): array
    {
        $fields = HrFields::cifHrFields();

        return array_merge($fields['I. COGNITIVE SKILLS & ASSESSMENT'], $fields['II. FLANAGAN INDUSTRIAL TEST']);
    }

    public static function cifHrScoreScale(): array
    {
        return [['min' => 90, 'rating' => 5, 'label' => 'Outstanding'], ['min' => 80, 'rating' => 4, 'label' => 'Very satisfactory'], ['min' => 70, 'rating' => 3, 'label' => 'Satisfactory'], ['min' => 60, 'rating' => 2, 'label' => 'Needs improvement'], ['min' => 0, 'rating' => 1, 'label' => 'Unsatisfactory']];
    }

    public static function cifHrScoreRating(mixed $value): string
    {
        if ($value === null || trim((string) $value) === '' || ! is_numeric($value) || (float) $value < 0 || (float) $value > 100) {
            return '';
        }
        foreach (HrFields::cifHrScoreScale() as $band) {
            if ((float) $value >= $band['min']) {
                return $band['rating'].'/5 · '.$band['label'];
            }
        }

        return '';
    }

    public static function cifHrChoices(string $name): array
    {
        if (isset(HrFields::cifHrFields()['Disciplinary records'][$name]) && $name !== 'disciplinary_others') {
            return ['None reported', 'Reported'];
        }

        return match ($name) {
            'tattoo', 'smoking', 'alcohol_intake', 'gambling', 'drug_use_history' => ['None reported', 'Reported'],
            default => [],
        };
    }

    public static function validateHrReview(mixed $input, ?array $previous = null, mixed $details = []): array
    {
        if (! is_array($input)) {
            throw new \InvalidArgumentException('Invalid HR review.');
        }
        if (! is_array($details)) {
            throw new \InvalidArgumentException('Invalid HR answer details.');
        }
        $result = [];
        foreach (HrFields::cifHrFields() as $fields) {
            foreach ($fields as $name => $label) {
                $value = $input[$name] ?? '';
                if (! is_string($value) || mb_strlen($value) > 1000) {
                    throw new \InvalidArgumentException($label.' must be 1000 characters or fewer.');
                }
                $result[$name] = trim($value);
                if (isset(HrFields::cifHrScoreFields()[$name])) {
                    if ($result[$name] !== '' && (! preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D', $result[$name]) || (float) $result[$name] > 100)) {
                        throw new \InvalidArgumentException($label.' must be a number from 0 to 100, with up to two decimal places.');
                    }

                    continue;
                }
                $choices = HrFields::cifHrChoices($name);
                $detail = $details[$name] ?? '';
                if (! is_string($detail) || mb_strlen($detail) > 1000) {
                    throw new \InvalidArgumentException($label.' details must be 1000 characters or fewer.');
                }
                if ($choices && $previous !== null && $result[$name] !== '' && ! in_array($result[$name], $choices, true) && $result[$name] !== ($previous[$name] ?? '')) {
                    throw new \InvalidArgumentException('Select a valid answer for '.$label.'.');
                }
                if ($choices && $result[$name] === 'Reported' && trim($detail) !== '') {
                    $result[$name] .= ': '.TextFormat::cifProperText(trim($detail));
                    if (mb_strlen($result[$name]) > 1000) {
                        throw new \InvalidArgumentException($label.' and details must be 1000 characters or fewer.');
                    }
                }
                if (! $choices) {
                    $result[$name] = TextFormat::cifSavedText($result[$name], $name, $name === 'recertification_date' ? 'date' : 'text');
                } elseif (! in_array(trim($value), $choices, true)) {
                    // Legacy descriptive answers are not catalogue selections.
                    $result[$name] = TextFormat::cifProperText($result[$name]);
                    foreach ($choices as $choice) {
                        if (str_starts_with(mb_strtolower($result[$name]), mb_strtolower($choice).': ')) {
                            $result[$name] = $choice.substr($result[$name], strlen($choice));
                            break;
                        }
                    }
                }
                if (mb_strlen($result[$name]) > 1000) {
                    throw new \InvalidArgumentException($label.' exceeds 1000 characters after formatting.');
                }
            }
        }
        // Retain historical assessment answers without treating them as new scores.
        foreach (['md5_score', 'planning_judgement'] as $key) {
            if (isset($previous[$key])) {
                $result[$key] = $previous[$key];
            }
        }
        if ($result['recertification_date'] !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $result['recertification_date']);
            if (! $date || $date->format('Y-m-d') !== $result['recertification_date'] || $date > new DateTimeImmutable('today')) {
                throw new \InvalidArgumentException('Enter a valid re-certification date.');
            }
        }

        return $result;
    }
}
