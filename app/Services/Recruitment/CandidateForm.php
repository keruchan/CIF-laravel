<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class CandidateForm
{
    public static function escaped(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public static function renderField(string $name, array $field): void
    {
        if ($name === 'yr') {
            return;
        }
        // Retired input; preserve legacy storage/printed records.
        $id = 'field-'.preg_replace('/[^a-zA-Z0-9_-]/', '-', $name);
        $type = $field['type'];
        if ($type === 'year_range') {
            CifEducation::renderCifEducationYears($id, '', $field['required']);

            return;
        }
        $required = $field['required'] ? ' required' : '';
        $label = preg_replace('/\s*\(optional\)$/i', '', $field['label']);
        echo '<div'.(in_array($type, ['textarea', 'photo'], true) ? ' class="full"' : '').'>'.($type === 'photo' ? '<div class="photo-field-label">' : '<label for="'.CandidateForm::escaped($id).'">').CandidateForm::escaped($label);
        if (! $field['required']) {
            echo ' <span class="optional-label" data-optional-label>(optional)</span>';
        }
        echo $type === 'photo' ? '</div>' : '</label>';
        if ($name === 'emp') {
            echo '<p class="field-instruction">Select your current employment status.</p>';
        }
        $attributes = ' id="'.CandidateForm::escaped($id).'" name="'.CandidateForm::escaped($name).'" maxlength="'.($type === 'year' ? 4 : $field['max']).'"'.$required;
        if ($name === 'desired_position') {
            HiringPositions::renderCifPositionPicker($id, $attributes);
        } elseif ($type === 'photo') {
            CifPhoto::renderCifPhoto($attributes);
        } elseif ($type === 'select') {
            echo '<select'.$attributes.'><option value="">'.($field['required'] ? 'Select' : ($name === 'sex' ? 'Select (optional)' : 'Select / not applicable')).'</option>';
            foreach ($field['options'] as $option) {
                echo '<option>'.CandidateForm::escaped($option).'</option>';
            }
            echo '</select>';
        } elseif ($type === 'textarea') {
            echo '<textarea'.$attributes.' rows="3"></textarea>';
        } elseif ($type === 'tel') {
            echo '<div class="contact-number"><span aria-hidden="true">+63-</span><input type="tel"'.$attributes.' data-contact-number inputmode="numeric" pattern="[1-9][0-9]{2}-[0-9]{3}-[0-9]{4}" placeholder="9XX-XXX-XXXX" aria-describedby="'.CandidateForm::escaped($id).'-hint"></div>';
            echo '<small class="contact-hint" id="'.CandidateForm::escaped($id).'-hint">+63 is fixed. Enter the remaining 10 digits.</small>';
        } elseif ($type === 'year') {
            echo '<input type="text"'.$attributes.' inputmode="numeric" pattern="[0-9]{4}" placeholder="YYYY" data-year data-year-max="'.date('Y').'">';
        } else {
            $inputType = in_array($type, ['money', 'measurement', 'rating', 'year'], true) ? 'number' : $type;
            $range = match ($type) {
                'money' => ' min="0" max="9999999999.99" step="0.01"',
                'measurement' => ' min="0.01" max="1000" step="0.01"',
                'rating' => ' min="0" max="100" step="0.01"',
                'year' => ' min="1900" max="'.date('Y').'" step="1"',
                'date' => ' max="'.date('Y-m-d').'"',
                'month' => ' max="'.date('Y-m').'"',
                default => '',
            };
            echo '<input type="'.$inputType.'"'.$attributes.$range.'>';
        }
        echo '</div>';
    }
}
