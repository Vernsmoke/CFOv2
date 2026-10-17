# CFO design transfer

The reference is the local `CFO` project. Its Cabinet and Inter fonts, CFO seal,
chapel photograph, navy/blue/gold palette, and rounded floating navigation are
adapted to this application's existing screens. The reference backend was not copied.

## Implementation

- `public/cfo-design.css` owns the shared palette, font faces, components and legacy
  variable adapters. `partials/design-system.blade.php` loads it last, for screen only.
- Public login, account creation, recovery, temporary-password, student, admin and
  registration-admin layouts use the shared theme.
- Older dark-theme stylesheets remain in the repository but are no longer loaded by
  these layouts. Page-specific layout and behavior modules remain connected.
- Cabinet is used for headings and navigation; Inter is used for forms and data.
- Navigation keeps its existing collapse/drawer behavior, keyboard handling and
  permissions. Tables retain horizontal scrolling when their data cannot fit.
- Printable document templates, certificate rendering, routes and database schema
  are unchanged. No account credentials were changed for visual testing.
- Source assets are copied under `public/fonts/cfo` and `public/images/cfo` to avoid
  replacing existing application assets. Inter's supplied license is included.

## Verification

- Full Laravel suite: 398 tests, 4,932 assertions passed.
- Production Vite build passed; `git diff --check` passed.
- Browser review: public login, account creation, recovery; admin dashboard,
  schedule list, schedule creation, registration list and details dialog; student
  dashboard and registration details.
- Phone-sized (390px) login and dashboards, both mobile navigation drawers, and
  tablet-sized (768px) account creation were inspected in same-origin fixed-width
  preview frames. The browser viewport override did not change the actual viewport.
- Authenticated previews rejected writes and rolled back request transactions.
  Their temporary router and servers are removed after review.

## Design review notes

The design-taste skill informed the public entry's hierarchy and screenshot review;
the existing-app redesign skill informed readable work surfaces and preservation of
functionality. A new art direction was not drawn because the user explicitly asked
to copy the CFO reference. No marketing sections, fabricated claims, extra photos or
animation were added just to satisfy marketing-page heuristics.

The shared CSS taste lint reports zero failures. Four warnings are intentional:
the real dialog backdrop blur and borders around workspace headers/data panels.
Its standalone browser inspector was not run; browser interaction was performed
through the supported in-app browser tools instead.

Review fixes included dark-on-dark schedule controls, transparent registration
dialogs caused by missing legacy variables, and the student mobile drawer selector.
Remaining tradeoff: the phone login form follows the introductory copy and chapel
photo, so reaching the submit button requires scrolling. This keeps the requested
reference composition without hiding required login fields.
