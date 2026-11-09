<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class HiringPositions
{
    // Two independent inventories, transcribed from the supplied workbook columns.
    public static function cifPositionInventory(): array
    {
        static $inventory;
        if ($inventory === null) {
            $inventory = json_decode(file_get_contents(config_path('position-inventory.json')), true, 512, JSON_THROW_ON_ERROR);
        }

        return $inventory;
    }

    public static function cifAppliedPositions(): array
    {
        return HiringPositions::cifPositionInventory()['applied'];
    }

    public static function cifHiringPositions(): array
    {
        return HiringPositions::cifPositionInventory()['official'];
    }

    public static function validateCifAppliedPosition(mixed $value, bool $allowEmpty = false): string
    {
        if (! is_string($value)) {
            throw new \InvalidArgumentException('Choose an applied position from the official inventory.');
        }
        $value = trim($value);
        if ($value === '' && $allowEmpty) {
            return '';
        }
        if (! in_array($value, HiringPositions::cifAppliedPositions(), true)) {
            throw new \InvalidArgumentException('Choose an available applied position from the search results.');
        }

        return $value;
    }

    public static function renderCifPositionPicker(string $id, string $attributes, string $value = ''): void
    {
        if (! str_contains($attributes, 'maxlength=')) {
            $attributes .= ' maxlength="150"';
        }
        $escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<div class="position-picker"><input type="search"'.$attributes.' value="'.$escape($value).'" data-position-picker data-position-options="'.$escape(json_encode(HiringPositions::cifAppliedPositions(), JSON_THROW_ON_ERROR)).'" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="'.$escape($id).'-options" placeholder="Search applied positions"><ul id="'.$escape($id).'-options" role="listbox" aria-label="Available applied positions" hidden></ul></div>';
        if ($value !== '' && ! in_array($value, HiringPositions::cifAppliedPositions(), true)) {
            echo '<small class="position-picker-legacy">Saved title is not in the current inventory. Select an available applied position before saving.</small>';
        }
    }
}
