@extends('layouts.registration-admin')
@section('content')
<section class="panel overview" aria-labelledby="registration-heading">
    <div class="overview-heading"><span class="heading-icon">@include('partials.admin-icon', ['name' => 'file'])</span><div><h1 id="registration-heading">Recollection Registrations</h1><p>View and manage all student registrations for {{ now()->year }}.</p></div></div>
    <div class="stat-grid">
        @foreach($stats as $label => $count)
            @php($kind = $loop->first ? 'total' : strtolower($label))
            <div class="stat-card {{ $kind }}">@include('partials.admin-icon', ['name' => $loop->first ? 'users' : $kind])<div><strong>{{ number_format($count) }}</strong><span>{{ $label }}</span><small>{{ ['total' => 'All students', 'verified' => 'Approved receipts', 'pending' => 'Awaiting verification', 'rejected' => 'Not approved'][$kind] }}</small></div></div>
        @endforeach
    </div>
</section>
<section class="panel listing" aria-label="Registration list">
    <form method="get" action="{{ route('admin.registrations.index') }}" id="registration-filters">
        <div id="registration-academic-filters" data-initial="{{ json_encode($filters) }}" data-courses="{{ json_encode($courses) }}" data-population="{{ json_encode($academicPopulation) }}"></div>
        <div id="registration-academic-values"></div>
        <div class="search-row"><label class="search-input"><span class="sr-only">Search registrations</span>@include('partials.admin-icon', ['name' => 'search'])<input type="search" name="search" maxlength="200" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, ID number, course, year & section..."></label><button class="button filter-toggle" type="button" data-toggle-registration-filters aria-expanded="false" aria-controls="registration-filter-panel" hidden>@include('partials.admin-icon', ['name' => 'settings'])<span data-filter-toggle-label>Show Filters</span></button><button class="button primary" type="submit">Search</button></div>
        <div class="filter-grid" id="registration-filter-panel">
            <label>Course<select name="course"><option value="">All Courses</option>@foreach($courses as $course)<option @selected(($filters['course'] ?? '') === $course)>{{ $course }}</option>@endforeach</select></label>
            <label>Year &amp; Section<select name="year_section"><option value="">All Year &amp; Sections</option>@foreach($yearSections as $section)<option @selected(($filters['year_section'] ?? '') === $section)>{{ $section }}</option>@endforeach</select></label>
            <label>Receipt Status<select name="status"><option value="">All Receipt Statuses</option>@foreach(array_keys(\App\Models\Registration::RECEIPT_LABELS) as $status)<option @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></label>
            <label>Schedule Status<select name="schedule_status"><option value="">All Schedule Statuses</option><option value="scheduled" @selected(($filters['schedule_status'] ?? '') === 'scheduled')>Scheduled</option><option value="not_yet_scheduled" @selected(($filters['schedule_status'] ?? '') === 'not_yet_scheduled')>Not Yet Scheduled</option></select></label>
            <fieldset class="date-range"><legend>Date Registered</legend><div><label><span class="sr-only">Registered from</span><input type="date" name="date_from" aria-label="Registered from" title="Registered from (inclusive)" value="{{ $filters['date_from'] ?? '' }}"></label></div></fieldset>
        </div>
    </form>
    <div class="table-toolbar"><p>Showing {{ $registrations->firstItem() ?? 0 }}–{{ $registrations->lastItem() ?? 0 }} of {{ number_format($registrations->total()) }} registrations</p><div class="pagination-tools"><label class="entries">Show <select name="per_page" form="registration-filters" id="per-page">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" @selected($registrations->perPage() === $size)>{{ $size }}</option>@endforeach</select> entries</label>{{ $registrations->onEachSide(1)->links('admin.registrations.pagination') }}</div></div>
    <div class="selection-status" id="selection-status" role="status" hidden></div>
    <div class="table-scroll"><table><thead><tr><th><input type="checkbox" id="select-all" aria-label="Select all registrations on this page" @disabled($registrations->isEmpty())></th><th>#</th><th>Student Name</th><th>ID Number</th><th>Course</th><th>Year &amp; Section</th><th>Date Registered</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        @forelse($registrations as $registration)
            @php($group = $registration->academicGroup())
            <tr><td><input class="row-select" type="checkbox" value="{{ $registration->id }}" aria-label="Select {{ $registration->student_display_name }}"></td><td>{{ $registrations->firstItem() + $loop->index }}</td><td>{{ $registration->student_display_name }}</td><td>{{ $registration->user->student_id ?: '—' }}</td><td>{{ $group['course'] ?: '—' }}</td><td>{{ ($group['year_level'] ?? '').($group['section'] ?? '') ?: '—' }}</td><td><time datetime="{{ $registration->created_at?->toIso8601String() }}">{{ $registration->created_at?->format('M d, Y') ?? '—' }}<small>{{ $registration->created_at?->format('h:i A') }}</small></time></td><td><span class="status-badge {{ strtolower($registration->receipt_label) }}">@include('partials.admin-icon', ['name' => strtolower($registration->receipt_label)]){{ $registration->receipt_label }}</span></td><td><div class="row-actions"><button class="button view-button" data-details="{{ route('admin.registrations.show', $registration) }}">@include('partials.admin-icon', ['name' => 'eye']) View</button></div></td></tr>
        @empty
            <tr><td colspan="9" class="empty-state">@include('partials.admin-icon', ['name' => 'search'])<h2>No registrations found</h2><p>Try another search or adjust your filters.</p></td></tr>
        @endforelse
    </tbody></table></div>
</section>
@endsection
