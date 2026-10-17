<link rel="stylesheet" href="{{ asset('cfo-usability.css') }}?v={{ filemtime(public_path('cfo-usability.css')) }}">
<script src="{{ asset('cfo-usability.js') }}?v={{ filemtime(public_path('cfo-usability.js')) }}"></script>
@if(request()->is('admin', 'student'))
<script src="{{ asset('cfo-search.js') }}?v={{ filemtime(public_path('cfo-search.js')) }}"></script>
@endif
