<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class CifEducation
{
    /** Keep the existing education_years column/JSON key, including partial drafts. */
    public static function cifEducationYearParts(mixed $value): array
    {
        $value = is_string($value) ? trim($value) : '';
        if (preg_match('/^(\d{0,4})\s*(?:[-–—\/]|to)\s*(\d{0,4})$/iuD', $value, $match)) {
            return ['from' => $match[1], 'to' => $match[2]];
        }
        if (preg_match('/^(\d{4})(?:\s*(?:[-–—\/]|to)\s*(?:present|current|ongoing))?$/iuD', $value, $match)) {
            return ['from' => $match[1], 'to' => ''];
        }

        return ['from' => '', 'to' => ''];
    }

    public static function validateCifEducationYears(string $value, bool $complete, ?string $existing = null): void
    {
        if (! preg_match('/^(\d{0,4})\s*(?:[-–—\/]|to)\s*(\d{0,4})$/iuD', $value, $match)) {
            if (! $complete && $existing !== null && mb_strtolower($value) === mb_strtolower(trim($existing))) {
                return;
            }
            throw new \InvalidArgumentException('Enter Inclusive years using separate four-digit From year and To year values.');
        }
        foreach (['From year' => $match[1], 'To year' => $match[2]] as $label => $year) {
            if ($year === '' && ! $complete) {
                continue;
            }
            if (! preg_match('/^\d{4}$/D', $year) || (int) $year < 1900 || (int) $year > (int) date('Y')) {
                throw new \InvalidArgumentException('Inclusive years: '.$label.' must be a four-digit year from 1900 to '.date('Y').'.');
            }
        }
        if ($match[1] !== '' && $match[2] !== '' && (int) $match[2] < (int) $match[1]) {
            throw new \InvalidArgumentException('Inclusive years: To year cannot be earlier than From year.');
        }
    }

    public static function renderCifEducationYears(string $id, mixed $value, bool $required, bool $admin = false, bool $readonly = false): void
    {
        $escape = static fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        $parts = CifEducation::cifEducationYearParts($value);
        echo '<div class="education-years-group" data-education-years role="group" aria-labelledby="'.$escape($id).'-label"><div class="education-years-title" id="'.$escape($id).'-label">Inclusive years</div><input type="hidden" name="education_years" id="'.$escape($id).'" data-education-years-value value="'.$escape($value ?? '').'"><div class="education-years-inputs">';
        foreach (['from' => 'From year', 'to' => 'To year'] as $part => $label) {
            $inputId = $id.'-'.$part;
            echo '<div'.($admin ? ' class="editor-field"' : '').'><label for="'.$escape($inputId).'">'.$label.'</label><input type="text" id="'.$escape($inputId).'" name="education_year_'.$part.'" value="'.$escape($parts[$part]).'" maxlength="4" inputmode="numeric" pattern="[0-9]{4}" placeholder="YYYY" data-year data-year-max="'.date('Y').'" data-education-year="'.$part.'"'.($readonly ? ' readonly' : '').($required && ! $admin ? ' required' : '').($admin ? ' data-candidate-label="Inclusive years — '.$label.'" data-candidate-required="'.($required ? 'true' : 'false').'"' : '').'></div>';
        }
        echo '</div></div>';
    }
}
