<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class CandidateAddress
{
    // Candidate-only address inputs. Keep the shared CIF/print field layout intact.
    public static function cifCandidateAddressFields(): array
    {
        $field = static fn (string $label, bool $required, int $max): array => ['label' => $label, 'required' => $required, 'max' => $max, 'type' => 'text', 'options' => []];

        return ['house_number' => $field('House no.', false, 30), 'street' => $field('Street', false, 100), 'purok' => $field('Purok', false, 60), 'barangay' => $field('Barangay', true, 80), 'municipality' => $field('City / municipality', true, 80), 'province' => $field('Province', true, 80)];
    }

    public static function cifCandidateAddresses(array $input): array
    {
        $addresses = [];
        foreach (['present' => 'Present address', 'permanent' => 'Permanent address', 'emergency' => 'Emergency contact address'] as $group => $label) {
            if ($group === 'permanent' && ($input['same_as_present_address'] ?? false) === true) {
                $addresses[$group] = $addresses['present'];

                continue;
            }
            foreach (CandidateAddress::cifCandidateAddressFields() as $key => $field) {
                $value = $input[$group.'_'.$key] ?? '';
                if (! is_string($value) || mb_strlen(trim($value)) > $field['max'] || $field['required'] && trim($value) === '') {
                    throw new \InvalidArgumentException($label.': '.$field['label'].' '.($field['required'] ? 'is required and ' : '').'must be no more than '.$field['max'].' characters.');
                }
                $addresses[$group][$key] = TextFormat::cifSavedText(trim($value), $key);
                if (mb_strlen($addresses[$group][$key]) > $field['max']) {
                    throw new \InvalidArgumentException($label.': '.$field['label'].' exceeds its maximum length after formatting.');
                }
            }
        }
        $addresses['same_as_present'] = ($input['same_as_present_address'] ?? false) === true;

        return $addresses;
    }

    public static function cifCandidateAddressLine(array $address): string
    {
        return implode(', ', array_values(array_filter($address, static fn (string $value): bool => $value !== '')));
    }
}
