<!doctype html>
<html lang="en" class="admin-background-root cfo-design" data-cfo-surface="admin">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#152c40">
  <meta name="description" content="Christian Formation Office registration and attendance management.">
  <title>CFO · Admin Panel</title>
  <link rel="stylesheet" href="{{ asset('admin-base.css') }}?v={{ filemtime(public_path('admin-base.css')) }}">
@include('partials.certificate-editor')
@include('partials.cfo-usability')
@if(request()->is('admin/schedules/*/edit'))
<script src="{{ asset('cfo-search.js') }}?v={{ filemtime(public_path('cfo-search.js')) }}" defer></script>
@endif
<link rel="stylesheet" href="{{ asset('admin-sidebar.css') }}?v={{ filemtime(public_path('admin-sidebar.css')) }}">
<link rel="stylesheet" href="{{ asset('admin-attendance.css') }}?v={{ filemtime(public_path('admin-attendance.css')) }}">
<link rel="stylesheet" href="{{ asset('admin-certificates.css') }}?v={{ filemtime(public_path('admin-certificates.css')) }}">
<link rel="stylesheet" media="screen" href="{{ asset('admin-typography.css') }}?v={{ filemtime(public_path('admin-typography.css')) }}">
  <link rel="stylesheet" media="screen" href="{{ asset('admin-header.css') }}?v={{ filemtime(public_path('admin-header.css')) }}">
  <link rel="stylesheet" media="screen" href="{{ asset('admin-scrollbar.css') }}?v={{ filemtime(public_path('admin-scrollbar.css')) }}">
  <link rel="stylesheet" media="screen" href="{{ asset('admin-table-scroll.css') }}?v={{ filemtime(public_path('admin-table-scroll.css')) }}">
  <script src="{{ asset('admin-table-scroll.js') }}?v={{ filemtime(public_path('admin-table-scroll.js')) }}" defer></script>
  <link rel="stylesheet" media="screen" href="{{ asset('admin-schedule-create.css') }}?v={{ filemtime(public_path('admin-schedule-create.css')) }}">
  <script src="{{ asset('admin-course-sections.js') }}?v={{ filemtime(public_path('admin-course-sections.js')) }}" defer></script>
  <script src="{{ asset('admin-course-manage.js') }}?v={{ filemtime(public_path('admin-course-manage.js')) }}" defer></script>
  <script src="{{ asset('admin-schedule-create.js') }}?v={{ filemtime(public_path('admin-schedule-create.js')) }}" defer></script>
  <link rel="stylesheet" media="screen" href="{{ asset('my-schedules.css') }}?v={{ filemtime(public_path('my-schedules.css')) }}">
  <script src="{{ asset('my-schedules.js') }}?v={{ filemtime(public_path('my-schedules.js')) }}" defer></script>
  <link rel="stylesheet" media="screen" href="{{ asset('admin-change-requests.css') }}?v={{ filemtime(public_path('admin-change-requests.css')) }}">
  <script src="{{ asset('admin-change-requests.js') }}?v={{ filemtime(public_path('admin-change-requests.js')) }}" defer></script>
  <script src="{{ asset('admin-announcements.js') }}?v={{ filemtime(public_path('admin-announcements.js')) }}" defer></script>
    <link rel="stylesheet" media="screen" href="{{ asset('cfo-search-inputs.css') }}?v={{ filemtime(public_path('cfo-search-inputs.css')) }}">
<link rel="stylesheet" media="screen" href="{{ asset('admin-filter-dropdowns.css') }}?v={{ filemtime(public_path('admin-filter-dropdowns.css')) }}">
<script src="{{ asset('admin-filter-dropdowns.js') }}?v={{ filemtime(public_path('admin-filter-dropdowns.js')) }}" defer></script>
    <link rel="stylesheet" media="screen" href="{{ asset('admin-action-bars.css') }}?v={{ filemtime(public_path('admin-action-bars.css')) }}">
    <script src="{{ asset('admin-action-bars.js') }}?v={{ filemtime(public_path('admin-action-bars.js')) }}" defer></script>
  <script src="{{ asset('admin-nav-badges.js') }}?v={{ filemtime(public_path('admin-nav-badges.js')) }}" defer></script>
  <link rel="stylesheet" media="screen" href="{{ asset('admin-profile.css') }}?v={{ filemtime(public_path('admin-profile.css')) }}">
  <script src="{{ asset('cfo-account-ids.js') }}?v={{ filemtime(public_path('cfo-account-ids.js')) }}" defer></script>
  <script src="{{ asset('admin-profile.js') }}?v={{ filemtime(public_path('admin-profile.js')) }}" defer></script>
  <script src="{{ asset('admin-settings-navigation.js') }}?v={{ filemtime(public_path('admin-settings-navigation.js')) }}" defer></script>
  <script src="{{ asset('admin-history-documents.js') }}?v={{ filemtime(public_path('admin-history-documents.js')) }}" defer></script>
  <script src="{{ asset('admin-dashboard.js') }}?v={{ filemtime(public_path('admin-dashboard.js')) }}" defer></script>
@include('partials.design-system')
</head>
<body class="admin-background">
  <!-- Authenticated administrator workspace. -->
  <a class="skip-link" href="#main-content">Skip to content</a>
  <aside class="sidebar" id="sidebar" aria-label="Admin navigation">
    <button class="icon-btn nav-close" id="close-nav" type="button" aria-label="Close navigation" aria-controls="sidebar"><span class="sidebar-close-symbol" aria-hidden="true">&times;</span><svg class="icon sidebar-collapse-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m14 6-6 6 6 6"/></svg></button>
    <a class="brand" href="#admin-dashboard">
      <span class="sidebar-logo"><img src="{{ asset('images/cfo/cfo-logo.png') }}" alt="Christian Formation Office logo" width="44" height="44"></span>
      <strong class="sidebar-school-name">Christian<br>Formation Office</strong>
    </a>
    <nav id="navigation" aria-label="Main navigation" data-nav-badges="{{ url('/api/admin/nav-badges') }}" data-badges="{{ json_encode(\App\Services\AdminNavigation::badges()) }}"></nav>
    <div class="sidebar-footer">
      <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 2)) }}</span>
      <a class="sidebar-profile-link" href="#admin-settings" title="Profile and settings"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->roleLabel() }}</small></a>
      <button data-action="logout" aria-label="Log out" title="Logout"><span data-icon="logout"></span></button>
    </div>
  </aside>
  <button class="nav-backdrop" id="nav-backdrop" aria-label="Close navigation" hidden></button>
  <div class="shell" id="shell">
    @include('partials.admin-header', ['headerTitle' => 'Dashboard', 'headerDashboard' => true, 'headerSpa' => true])
    <main id="main-content" tabindex="-1">
      <div class="page-heading"><div><div class="eyebrow" id="page-category">Overview</div><h1 id="page-title" tabindex="-1">Dashboard</h1><p id="page-description"></p></div><div class="page-actions" id="page-actions"></div></div>
      <div id="cfo-context-search" hidden></div>
      <div id="page"></div>
    </main>
  </div>
  <dialog id="dialog" aria-labelledby="dialog-title"></dialog>
  <div id="toast" class="toast" role="status" aria-live="polite" hidden></div>
  <div class="print-area" id="print-area"></div>

  <script>
    window.ADMIN_CONFIG = {
      CFO_BACKEND: {{ Illuminate\Support\Js::from($cfoBackend) }},
      navigation: @json(\App\Services\AdminNavigation::itemsFor(auth()->user())),
      accessUrl: {{ Illuminate\Support\Js::from(config('access.url')) }}
    };
  </script>
@vite('resources/js/qr.js')
@include('partials.history-details')
</body>
</html>
