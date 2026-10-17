# Student file separation ? Phase 2

Status: complete. All 12 approved student-only files were moved with git mv. Assets use public/assets/student/, as subsequently approved, to avoid shadowing the existing /student page with a physical public/student directory. Shared public/.htaccess is unchanged.

## Every moved file

| Previous path | Final path | Content edits |
|---|---|---|
| `app/Http/Controllers/StudentController.php` | `app/Http/Controllers/Student/StudentController.php` | Recorded namespace/handler/path edits |
| `public/images/dashboard-reference.webp` | `public/assets/student/images/dashboard-reference.webp` | None |
| `public/student-certificates.css` | `public/assets/student/student-certificates.css` | None |
| `public/student-certificates.js` | `public/assets/student/student-certificates.js` | Recorded namespace/handler/path edits |
| `public/student-dashboard.css` | `public/assets/student/student-dashboard.css` | None |
| `public/student-pages.css` | `public/assets/student/student-pages.css` | None |
| `public/student-pages.js` | `public/assets/student/student-pages.js` | Recorded namespace/handler/path edits |
| `public/student-receipts.js` | `public/assets/student/student-receipts.js` | None |
| `public/student-security.css` | `public/assets/student/student-security.css` | None |
| `public/student-security.js` | `public/assets/student/student-security.js` | None |
| `public/student-theme.css` | `public/assets/student/student-theme.css` | Recorded namespace/handler/path edits |
| `resources/views/student.blade.php` | `resources/views/student/index.blade.php` | Recorded namespace/handler/path edits |

## Every edited or new application/test file

- `app/Http/Controllers/PageController.php`
- `app/Http/Controllers/Student/StudentController.php`
- `bootstrap/app.php`
- `public/assets/student/student-certificates.js`
- `public/assets/student/student-pages.js`
- `public/assets/student/student-theme.css`
- `resources/views/student/index.blade.php`
- `routes/web.php`
- `tests/Browser/filter-dropdowns-state.cjs`
- `tests/Browser/student-certificates-state.cjs`
- `tests/Browser/student-change-request-state.cjs`
- `tests/Browser/student-image-assets-state.cjs`
- `tests/Browser/student-notification-state.cjs`
- `tests/Browser/student-pages-state.cjs`
- `tests/Browser/student-registration-options-state.cjs`
- `tests/Browser/student-registration-state.cjs`
- `tests/Browser/student-security-state.cjs`
- `tests/Browser/yearly-history-state.cjs`
- `tests/Feature/RenderedPageScriptTest.php`
- `tests/Feature/StudentPagesTest.php`
- `tests/Feature/StudentRegistrationEditTest.php`
- `routes/student.php` ? new file containing the extracted student routes and legacy redirect.

The move table also lists the seven moved files whose contents did not change. The union of the move and edit lists is the complete application/test change inventory.

### Approved shared edits

- routes/web.php: extracted only the student group, legacy student redirect, and its now-unused StudentController import; all remaining routes and imports retain their behavior.
- bootstrap/app.php: registered routes/student.php alongside routes/web.php through the existing web middleware registration.
- PageController.php: changed only the fallback view name from student to student.index.
- Existing test files: replaced only hardcoded student view/asset/image paths; no test file was moved.

The moved StudentController has its updated namespace/base-controller import and a dedicated page handler. Its existing API methods are otherwise unchanged. The page handler retains exactly user, csrf_token, live_transport, status and Cache-Control behavior for students; comparison results are linked below.

## Every asset reference and cache-busting update

All changed live asset references are in resources/views/student/index.blade.php:

| Asset | Final public-relative path | Versioning retained |
|---|---|---|
| `student-pages.js` | `assets/student/student-pages.js` | Existing ?v=filemtime(), with the moved public_path() |
| `student-security.js` | `assets/student/student-security.js` | Existing unversioned URL |
| `student-receipts.js` | `assets/student/student-receipts.js` | Existing ?v=filemtime(), with the moved public_path() |
| `student-certificates.js` | `assets/student/student-certificates.js` | Existing ?v=filemtime(), with the moved public_path() |
| `student-pages.css` | `assets/student/student-pages.css` | Existing ?v=filemtime(), with the moved public_path() |
| `student-security.css` | `assets/student/student-security.css` | Existing unversioned URL |
| `student-certificates.css` | `assets/student/student-certificates.css` | Existing ?v=filemtime(), with the moved public_path() |
| `student-dashboard.css` | `assets/student/student-dashboard.css` | Existing ?v=filemtime(), with the moved public_path() |
| `student-theme.css` | `assets/student/student-theme.css` | Existing ?v=filemtime(), with the moved public_path() |
| `dashboard-reference.webp` | `assets/student/images/dashboard-reference.webp` | Existing unversioned URL |

- student-theme.css now uses ../../images/admin-campus-bg.png, resolving to the same shared public/images/admin-campus-bg.png file.
- Student template resource IDs, CSS classes, API URLs and JavaScript globals are unchanged.
- Documentation comments in student-pages.js, student-certificates.js and the student view reference their relocated files.
- LiveController.php is unchanged: its resource version counters contain no filesystem references to the moved assets.
- Shared authentication, layouts/partials, assets, fonts, models and helpers stay in place. StudentRegistrationOptions.php was retained.
- [reference-updates.json](reference-updates.json) records every exact replacement, including asset(), public_path()/filemtime(), namespaces, handler insertion and test references.

## Verification results

- PHP final suite: 393 passed, 0 failed; 4896 assertions. [Full output](php-tests.txt).
- Direct Node checks: 9 passed, 0 failed. [Full output](node-checks.txt). The rendered image/resource check additionally passed through StudentPagesTest in the PHP suite.
- php artisan view:clear: Compiled views cleared successfully. [Output](view-clear.txt).
- php artisan route:list: 65 routes before and after; all 10 protected student routes retain their URL, HTTP method, route name and middleware order. [Before](routes-before.txt), [after](routes-after.txt).
- [Route-contract diff](route-contract-diff.txt): no route contract changes across all 65 routes; student middleware remains web -> auth -> cfo -> cfo:Student. Legacy /student-panel.html retains its original HTTP methods and web-only middleware.
- [Full route:list text diff](routes-full.diff): the expected student controller namespace/action changes only.
- [Response parity](page-response-parity.txt): metadata, functional headers, status and rendered HTML match for sse and polling values; Cache-Control=no-store, private. The comparison uses an unsaved user and array-backed session/cache without database calls.
- [Source integrity](source-integrity.txt): all edits match the exact replacement ledger, original PHP/Blade line-ending styles are preserved, asset references and versioning resolve, and shared LiveController/public .htaccess remain unchanged.
- Active source scan: no old student view path, old controller namespace, or previous public/student asset paths remain.
- No application database, migration or .env file was changed. PHP tests use the configured in-memory SQLite test database.

## Route diff shown directly

```text
Before: 65 total routes; 10 protected student routes
After:  65 total routes; 10 protected student routes
URL / method / name / middleware diff: empty
Middleware order: web, auth, cfo, cfo:Student
Legacy student-panel.html middleware: web
```

## Manual test checklist

- [ ] Student login reaches /student; logout and login work. Check /student-panel.html while signed out and signed in, with the same redirects as before.
- [ ] Student account signup works on the shared login screen. Complete/save the annual recollection registration, then confirm saved details after refresh.
- [ ] Choose an eligible schedule, refresh and confirm it remains selected. Confirm unavailable schedules retain their existing restrictions.
- [ ] Upload a receipt image, open its preview and reopen it after refresh. Check replacement only where existing rules permit it.
- [ ] Open notifications, follow their destinations and mark them viewed; verify new notifications still appear.
- [ ] Dashboard backgrounds, fonts, dropdowns, profile, announcements, history and certificates load without missing assets or browser-console errors.
- [ ] Admin login, dashboard, schedule pages and receipt viewing still work.

## Every new audit file

- `docs/audits/student-file-separation/node-checks.txt`
- `docs/audits/student-file-separation/page-response-parity.txt`
- `docs/audits/student-file-separation/php-tests.txt`
- `docs/audits/student-file-separation/reference-updates.json`
- `docs/audits/student-file-separation/report.md`
- `docs/audits/student-file-separation/route-contract-diff.txt`
- `docs/audits/student-file-separation/routes-after.json`
- `docs/audits/student-file-separation/routes-after.txt`
- `docs/audits/student-file-separation/routes-before.json`
- `docs/audits/student-file-separation/routes-before.txt`
- `docs/audits/student-file-separation/routes-full.diff`
- `docs/audits/student-file-separation/source-integrity.txt`
- `docs/audits/student-file-separation/student-routes-after.jsonl`
- `docs/audits/student-file-separation/student-routes-before.jsonl`
- `docs/audits/student-file-separation/verify-page-response.php`
- `docs/audits/student-file-separation/view-clear.txt`
