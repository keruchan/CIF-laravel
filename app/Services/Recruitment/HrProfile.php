<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class HrProfile
{
    public static function cifHrProfilePhoto(mixed $value): string
    {
        // Reuse the CIF image validator: verify decoded bytes, MIME, size and dimensions.
        return CandidateStorage::cifValue($value, ['label' => 'Profile picture', 'type' => 'photo', 'max' => 2800000, 'required' => false]);
    }

    public static function loadCifHrProfilePhoto(\PDO $pdo, int $adminId): string
    {
        $query = $pdo->prepare('SELECT photo FROM hr_staff_profiles WHERE admin_id = ?');
        $query->execute([$adminId]);

        return (string) ($query->fetchColumn() ?: '');
    }

    public static function saveCifHrProfilePhoto(\PDO $pdo, int $adminId, mixed $value): string
    {
        $photo = HrProfile::cifHrProfilePhoto($value);
        $query = $pdo->prepare('INSERT INTO hr_staff_profiles (admin_id, photo, updated_at) VALUES (?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE photo = VALUES(photo), updated_at = VALUES(updated_at)');
        $query->execute([$adminId, $photo]);

        return $photo;
    }
}
