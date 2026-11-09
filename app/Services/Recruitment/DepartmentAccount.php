<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class DepartmentAccount
{
    public static function departmentProfile(array $account): array
    {
        return ['id' => (int) $account['id'], 'username' => $account['username'], 'name' => $account['name'],
            'position' => $account['position'], 'department_name' => $account['department_name'], 'group_name' => $account['group_name'] ?? ''];
    }

    public static function cifRequestorCanSignIn(array $account): bool
    {
        return ($account['account_status'] ?? 'Active') === 'Active' && empty($account['archived_at']);
    }
}
