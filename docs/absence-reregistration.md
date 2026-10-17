# Registration after absence

Marking the latest current-year registration Absent automatically opens the student's registration form again. A passed date alone does not set attendance. Present, Late, unmarked, prior-year and superseded records cannot be resubmitted. An archived current-year Absent attempt can be resubmitted.

The student reviews prefilled details and confirms both consent checkboxes again. Submitting creates a new Pending registration with an incremented attempt number, no schedule, and no recorded attendance. The missed attempt remains in History, with its original details, attendance, schedule and receipt. If it was already archived, its original archive metadata remains unchanged.

The student can upload a new receipt or reuse the old receipt. Reuse copies the private file so later replacement cannot remove the historical receipt. The new receipt goes through verification again. This does not automatically require a new payment.

The Admin reviews the new active registration and assigns a published schedule with available slots. Cross-course/year-level/section Admin assignments still work. Student state returns the newest attempt first. Admin and Student live refresh uses the existing resource versions. Duplicate retries and stale requests after attendance corrections are rejected under database locks.

## Migration

`database/migrations/2026_10_05_000001_allow_registration_attempts_after_absence.php` adds `attempt_no` (default 1) and replaces the student/year unique key with student/year/attempt. Existing records remain attempt 1. No new table is added. Rollback stops if repeated attempts exist rather than deleting historical records.

Run `php artisan migrate` on other installations. The database audit checks the new unique key and rejects multiple active registrations for the same student/year.

## Changed files

- `routes/web.php`: Student-only retry endpoint.
- `app/Http/Controllers/StudentController.php`: eligibility, ordering, retry validation and transaction.
- `app/Http/Controllers/BatchWorkflowController.php` and `AdminController.php`: absence notification guidance.
- `app/Console/Commands/AuditDatabase.php`: attempt uniqueness and active-record audit.
- `resources/views/student.blade.php`: reopened form, renewed consent, dashboard link, retry submission.
- `resources/views/admin/registrations/details.blade.php`: repeat-attempt notice.
- `resources/views/partials/history-details.blade.php`: attempt number in History.
- `tests/Feature/AbsentRegistrationRetryTest.php`: complete retry, history, receipt, scheduling and authorization checks.
- `tests/Browser/student-registration-state.cjs`: prefill and consent-reset checks.

## Verification

Run `php artisan test --compact`. Manually mark an assigned student Absent, open their Recollection Registration page, review and resubmit the form, then verify that Admin sees the new unscheduled attempt and History retains the missed one. Assign another published schedule and verify that the student sees it. Confirm that refreshing/repeating the old submission cannot create a duplicate.
