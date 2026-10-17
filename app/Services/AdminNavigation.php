<?php

namespace App\Services;

use App\Models\{PasswordResetRequest, Registration, ScheduleChange, User};

class AdminNavigation
{
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
            'accounts' => User::where('status', 'Pending')->count(),
        ];
    }
}
