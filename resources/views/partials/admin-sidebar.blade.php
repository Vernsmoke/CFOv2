<aside class="sidebar" id="sidebar" aria-label="Admin navigation">
    <button class="nav-close" type="button" aria-label="Close navigation" aria-controls="sidebar"><span class="sidebar-close-symbol" aria-hidden="true">&times;</span><svg class="icon sidebar-collapse-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m14 6-6 6 6 6"/></svg></button>
    <a class="brand" href="{{ url('/admin') }}#admin-dashboard">
        <span class="sidebar-logo"><img src="{{ asset('assets/ulslogo.png') }}" alt="Christian Formation Office logo" width="44" height="44"></span>
        <strong class="sidebar-school-name">Christian<br>Formation Office</strong>
    </a>
    @php
        $badges = \App\Services\AdminNavigation::badges();
    @endphp
    <nav aria-label="Main navigation" data-nav-badges="{{ url('/api/admin/nav-badges') }}" data-badges="{{ json_encode($badges) }}">
        @foreach(config('admin_navigation') as $group => $items)
            @if($group !== '')<div class="nav-group">{{ $group }}</div>@endif
            @foreach($items as $key => $item)
                @php
                    [$label, $icon] = $item;
                    $options = $item[2] ?? [];
                    $active = request()->routeIs(...(array) ($options['active_routes'] ?? []));
                    $count = (int) ($badges[$options['badge'] ?? ''] ?? 0);
                @endphp
                <a class="nav-link {{ $active ? 'active' : '' }}" href="{{ isset($options['href']) ? url($options['href']) : url('/admin').'#'.$key }}" data-route="{{ $key }}" @if($active) aria-current="page" @endif>
                    @include('partials.admin-icon', ['name' => $icon])<span>{{ $label }}</span>
                    @isset($options['badge'])<span class="nav-badge" data-nav-badge="{{ $options['badge'] }}" @if($count > 0) aria-label="{{ $count }} waiting" @else hidden @endif>{{ $count > 99 ? '99+' : ($count > 0 ? $count : '') }}</span>@endisset
                </a>
            @endforeach
        @endforeach
    </nav>
    <div class="sidebar-footer">
        <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 2)) }}</span>
        <a class="sidebar-profile-link" href="{{ url('/admin') }}#admin-settings" title="Profile and settings"><strong>{{ auth()->user()->name }}</strong><small>Administrator</small></a>
        <form method="post" action="{{ url('/api/logout') }}" id="registration-logout">@csrf<button aria-label="Log out">@include('partials.admin-icon', ['name' => 'logout'])</button></form>
    </div>
</aside>
