<?php

/*
 * Single source of truth for the administrator sidebar. Both the single-page
 * admin (public/admin-dashboard.js) and the server-rendered registration pages
 * (resources/views/partials/admin-sidebar.blade.php) read this file.
 *
 * group => [ route key => [label, icon, options?] ]
 *
 * options (all optional):
 *   href           link target; defaults to /admin#<route key>
 *   badge          key from App\Services\AdminNavigation::badges()
 *   active_routes  Laravel route name pattern(s) that mark the link active on
 *                  server-rendered pages
 *
 * An empty group name renders its links without a heading.
 */
return [
    '' => ['admin-dashboard' => ['Dashboard', 'home']],
    'Daily work' => [
        'admin-register-this-year' => ['Recollection Registrations', 'file', [
            'href' => '/admin/recollection-registrations',
            'badge' => 'registrations',
            'active_routes' => 'admin.registrations.*',
        ]],
        'admin-student-attendance' => ['Attendance', 'user-check'],
        'admin-schedule-change-requests' => ['Change requests', 'change', ['badge' => 'change_requests']],
        'admin-password-resets' => ['Password reset requests', 'lock', ['badge' => 'password_resets']],
    ],
    'Schedules & students' => [
        'admin-recollection-schedule' => ['Schedules', 'calendar'],
        'admin-all-student' => ['Student accounts', 'users', ['badge' => 'accounts']],
    ],
    'Records' => [
        'admin-history' => ['Recollection History', 'history'],
        'admin-certificate' => ['Certificates', 'award'],
    ],
    'Office' => [
        'admin-announcements' => ['Announcements', 'info'],
        'admin-activity' => ['Activity log', 'list', ['super_admin_only' => true]],
        'admin-settings' => ['Settings', 'settings'],
    ],
];
