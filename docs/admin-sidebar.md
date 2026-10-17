# Admin sidebar

`config/admin_navigation.php` is the single source for the sidebar. The single-page admin (`public/admin-dashboard.js`) and the server-rendered registration pages (`resources/views/partials/admin-sidebar.blade.php`) both read it, so labels, icons, links and badges cannot drift apart.

```php
'group name' => [
    'route-key' => ['Label', 'icon', ['href' => '/optional/path', 'badge' => 'registrations', 'active_routes' => 'admin.registrations.*']],
],
```

- An empty group name renders its links without a heading (used for Dashboard).
- `route-key` must also exist in the `pages` map in `admin-dashboard.js`. Icons must exist in `partials/admin-icon.blade.php` and in `iconPaths` in `admin-dashboard.js`.
- `badge` names a count from `App\Services\AdminNavigation::badges()`: `registrations` (current-year, Pending), `change_requests` (Pending), `password_resets` (Pending), `accounts` (Pending).
- Badges are rendered by the server on page load, then refreshed by `public/admin-nav-badges.js` from `GET /api/admin/nav-badges` every 30 seconds while the tab is visible, when returning to the tab, and on navigation.
- The brand logo opens the Dashboard. The name in the footer opens Settings.

## Tests

`php artisan test --compact --filter=AdminSidebarTest` and `node tests/Browser/admin-sidebar-state.cjs`.
