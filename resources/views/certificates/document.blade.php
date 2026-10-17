<svg xmlns="http://www.w3.org/2000/svg" width="1120" height="790" viewBox="0 0 1120 790" role="img" aria-label="Certificate of participation">
    <rect width="1120" height="790" fill="{{ $s['background_color'] }}"/>
    @if($background)
        <image width="1120" height="790" href="{{ $background }}" preserveAspectRatio="xMidYMid meet"/>
    @endif
    <rect x="25" y="25" width="1070" height="740" rx="8" fill="none" stroke="#075a43" stroke-width="4"/>
    <g text-anchor="middle" font-family="{{ $font }}" fill="#073d33">
        @foreach($blocks as $block)
            <text x="560" y="{{ $block['y'] }}" font-size="{{ $block['size'] }}">{{ $block['text'] }}</text>
        @endforeach
    </g>
</svg>
