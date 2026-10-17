{{-- The screen-only theme deliberately leaves printable documents unchanged. --}}
<link rel="icon" type="image/png" href="{{ asset('images/cfo/cfo-logo.png') }}">
<link rel="preload" href="{{ asset('fonts/cfo/cabinet-bold.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" media="screen" href="{{ asset('cfo-design.css') }}?v={{ filemtime(public_path('cfo-design.css')) }}">
