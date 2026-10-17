# Web application optimization audit — 2026-10-17

## Executive summary

The Laravel application had a reliable test baseline, but every authenticated
request—including the 2–2.5 second live-version polls—ran year-archive and
certificate-repair queries. Those operations are now guarded by a per-session,
per-day marker plus a cross-session cache lock. Normal writes still update
certificates immediately; the daily job remains a legacy-data safety net.

Live-version polling now rejects overlapping requests, which prevents request
backlogs on slow connections. Dynamic responses also receive conservative
security headers, with HSTS emitted only when Laravel recognizes an HTTPS
request (including a configured trusted reverse proxy).

## Architecture observations

- Laravel 12 / PHP 8.2, Blade views, Eloquent, session authentication.
- Vite 7 builds Tailwind 4 and the application JavaScript/CSS entry points.
- MySQL is the production default; SQLite in-memory databases back feature tests.
- Database-backed sessions, cache, queues, and live resource-version tracking are
  the production defaults.
- Admin live updates prefer server-sent events and fall back to polling. Student
  pages poll resource versions and request only changed state groups.
- Uploaded receipts and certificate assets use a private local or S3 disk.

## Issue inventory and result

| Priority | Area | Finding | Resolution |
|---|---|---|---|
| P1 | Database / polling | Archive and certificate catch-up scans ran on every protected request. | Run once per session/day and deduplicate across sessions with a cache lock. |
| P2 | Reliability | Slow live-version requests could overlap on both portals. | Added in-flight guards to admin and student polling. |
| P2 | Security | Responses lacked a consistent baseline of browser security headers. | Added MIME sniffing, framing, referrer, permissions, and HTTPS-only HSTS headers. |
| P3 | Maintainability | The performance regression had no automated query guard. | Added a feature test that caps warm polling at two database reads and rejects maintenance scans. |

## Verification and evidence

- Backend: 400 tests, 4,951 assertions — passing.
- Focused rollover, proxy/HTTPS, and optimization tests: 12 tests, 130
  assertions — passing after the final HSTS adjustment.
- Production frontend build — passing. Output remains 56.57 kB CSS, 51.52 kB
  application JavaScript, and 25.61 kB QR JavaScript before gzip.
- New PHP files pass Laravel Pint.
- Composer audit: no known advisories.
- npm production audit: 0 vulnerabilities.
- Warm `/api/live/versions` requests are regression-tested at no more than two
  database reads and cannot execute archive updates or missing-certificate scans.

## Existing issues not changed

- The repository-wide Pint check reports formatting debt across existing PHP
  files. A whole-project formatting rewrite was intentionally avoided.
- Two browser harnesses fail against pre-existing, uncommitted design work:
  `certificate-editor-state.cjs` cannot find `mountCertificateEditor`, and
  `student-image-assets-state.cjs` cannot find the student image resources.
  The other browser harnesses pass, including the year-rollover/live-refresh
  harness touched by this optimization.
- The initial admin-state payload remains intentionally broad. Pagination or
  route-specific state endpoints would be the next scaling project once real
  payload and latency measurements justify the larger API/UI change.

## Deployment notes

- No schema migration or new environment variable is required.
- Database cache deployments already include the `cache_locks` table needed by
  the maintenance lock.
- Rollback is limited to removing the maintenance service call, polling guards,
  and security middleware registration; no stored data format changed.
