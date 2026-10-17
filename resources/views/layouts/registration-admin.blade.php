<!doctype html>
<html lang="en" class="admin-background-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Recollection Registrations · CFO</title>
    <link rel="stylesheet" href="{{ asset('recollection-registrations.css') }}?v={{ filemtime(public_path('recollection-registrations.css')) }}">
    <link rel="stylesheet" href="{{ asset('admin-sidebar.css') }}?v={{ filemtime(public_path('admin-sidebar.css')) }}">
    <link rel="stylesheet" href="{{ asset('admin-background.css') }}?v={{ filemtime(public_path('admin-background.css')) }}">
    <link rel="stylesheet" media="screen" href="{{ asset('admin-typography.css') }}?v={{ filemtime(public_path('admin-typography.css')) }}">
    <link rel="stylesheet" media="screen" href="{{ asset('admin-header.css') }}?v={{ filemtime(public_path('admin-header.css')) }}">
    <link rel="stylesheet" media="screen" href="{{ asset('admin-scrollbar.css') }}?v={{ filemtime(public_path('admin-scrollbar.css')) }}">
    <link rel="stylesheet" media="screen" href="{{ asset('admin-table-scroll.css') }}?v={{ filemtime(public_path('admin-table-scroll.css')) }}">
    <script src="{{ asset('admin-table-scroll.js') }}?v={{ filemtime(public_path('admin-table-scroll.js')) }}" defer></script>
    <script src="{{ asset('admin-nav-badges.js') }}?v={{ filemtime(public_path('admin-nav-badges.js')) }}" defer></script>
    <script src="{{ asset('recollection-registrations.js') }}?v={{ filemtime(public_path('recollection-registrations.js')) }}" defer></script>
    <script id="registration-live-versions" type="application/json">@json($liveVersions)</script>
    <script src="{{ asset('registration-live.js') }}?v={{ filemtime(public_path('registration-live.js')) }}" defer></script>
    <link rel="stylesheet" media="screen" href="{{ asset('admin-theme.css') }}?v={{ filemtime(public_path('admin-theme.css')) }}">
    <link rel="stylesheet" media="screen" href="{{ asset('cfo-search-inputs.css') }}?v={{ filemtime(public_path('cfo-search-inputs.css')) }}">
<link rel="stylesheet" media="screen" href="{{ asset('admin-filter-dropdowns.css') }}?v={{ filemtime(public_path('admin-filter-dropdowns.css')) }}">
<script src="{{ asset('admin-filter-dropdowns.js') }}?v={{ filemtime(public_path('admin-filter-dropdowns.js')) }}" defer></script>
    <link rel="stylesheet" media="screen" href="{{ asset('admin-action-bars.css') }}?v={{ filemtime(public_path('admin-action-bars.css')) }}">
    <script src="{{ asset('admin-action-bars.js') }}?v={{ filemtime(public_path('admin-action-bars.js')) }}" defer></script>
</head>
<body class="registrations-page admin-background">
    <a class="skip-link" href="#main-content">Skip to content</a>
    @include('partials.admin-sidebar')
    <button class="nav-backdrop" aria-label="Close navigation" hidden></button>
    <div class="shell">
        @include('partials.admin-header', ['headerTitle' => 'Recollection Registrations'])
        <main id="main-content">
            <div id="page-message" role="status" hidden></div>
            <div id="registration-live-notice" class="message loading" role="status" hidden><span>Registration records have changed.</span> <button type="button" class="button" id="refresh-registration-records">Refresh records</button></div>
            @if(session('success'))<p class="message success" role="status">{{ session('success') }}</p>@endif
            @if($errors->any())<div class="message error" role="alert"><strong>Please check the filters.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
    </div>
    <dialog id="registration-dialog" aria-labelledby="dialog-title" aria-describedby="dialog-description">
        <header class="dialog-heading"><div><h2 id="dialog-title">View Registration</h2><p id="dialog-description">Student registration details, receipt, and schedule management.</p></div><button type="button" class="icon-button" data-close-dialog aria-label="Close details">&times;</button></header>
        <div id="dialog-content"></div>
    </dialog>
    <dialog id="receipt-image-dialog" aria-labelledby="receipt-image-title" aria-describedby="receipt-image-description">
        <header class="receipt-image-heading"><div><h2 id="receipt-image-title">Student Receipt</h2><p id="receipt-image-description">Close this preview to return to the registration.</p></div><button type="button" class="icon-button" id="close-receipt-image" aria-label="Close receipt and return to registration" autofocus>&times;</button></header>
        <div class="receipt-image-content" aria-busy="false"><p id="receipt-image-message" role="status">Loading receipt…</p><img id="full-receipt-image" alt="Student's uploaded receipt" hidden></div>
    </dialog>
</body>
</html>
