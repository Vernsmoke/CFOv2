@php
    $headerTitle = $headerTitle ?? 'Dashboard';
    $headerDashboard = $headerDashboard ?? false;
    $headerSpa = $headerSpa ?? false;
@endphp
<header class="topbar admin-header" data-admin-header data-mode="{{ $headerSpa ? 'spa' : 'registrations' }}">
    <button type="button" class="icon-btn admin-header-control" id="toggle-nav" aria-label="Toggle navigation" aria-controls="sidebar" aria-expanded="true">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div class="admin-header-heading">
        <strong id="admin-header-title">{{ $headerTitle }}</strong>
        <nav id="admin-header-breadcrumb" aria-label="Breadcrumb">
            @if($headerDashboard)
                <span>CFO / Admin workspace</span>
            @else
                <a href="{{ url('/admin') }}#admin-dashboard">Dashboard</a><span aria-hidden="true">›</span><span aria-current="page">{{ $headerTitle }}</span>
            @endif
        </nav>
    </div>
    <div class="topbar-actions admin-header-actions">
        <span class="preview-tag live" id="live-status" role="status">Live</span>
        <button type="button" class="icon-btn admin-header-control" aria-label="Notifications" @if($headerSpa) data-action="notifications" @else data-header-notifications @endif>
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
        </button>
        @if($headerSpa)
            <button type="button" class="account admin-header-account" data-action="profile" aria-label="Open admin profile">
        @else
            <a class="account admin-header-account" href="{{ url('/admin') }}#admin-settings" aria-label="Open admin profile">
        @endif
            <span class="avatar" id="admin-header-avatar">AD</span>
            <span class="admin-header-account-copy"><strong id="admin-header-name">Admin User</strong><small id="admin-header-role">Administrator</small></span>
        @if($headerSpa)</button>@else</a>@endif
    </div>
</header>
<script src="{{ asset('admin-header.js') }}?v={{ filemtime(public_path('admin-header.js')) }}"></script>
