<?php

namespace App\Services;

use App\Models\PasswordResetRequest;
use App\Models\Registration;
use App\Models\ScheduleChange;
use App\Models\User;

class AdminNavigation
{
    public static function itemsFor(?User $user = null): array
    {
        $user ??= auth()->user();

        return collect(config('admin_navigation'))->map(function (array $items) use ($user) {
            return array_filter($items, function (array $item) use ($user) {
                return ! ($item[2]['super_admin_only'] ?? false) || $user?->isSuperAdmin();
            });
        })->filter()->all();
    }

    /**
     * Counts of items waiting for an administrator, keyed by the `badge`
     * option used in config/admin_navigation.php.
     *
     * @return array<string, int>
     */
    public static function badges(): array
    {
        return [
            'registrations' => Registration::currentYear()->where('status', 'Pending')->count(),
            'change_requests' => ScheduleChange::where('status', 'Pending')->count(),
            'password_resets' => PasswordResetRequest::where('status', 'Pending')->count(),
            'accounts' => User::where('role', User::ROLE_STUDENT)->where('status', 'Pending')->count(),
        ];
    }
}
