<svg xmlns="http://www.w3.org/2000/svg" width="1120" height="790" viewBox="0 0 1120 790" role="img" aria-label="Certificate of participation">
    <defs>
        <linearGradient id="certificate-background" x1="0%" y1="0%" x2="{{ $s['gradient_direction'] === 'vertical' ? '0%' : '100%' }}" y2="{{ $s['gradient_direction'] === 'horizontal' ? '0%' : '100%' }}">
            <stop offset="0%" stop-color="{{ $s['background_color'] }}"/>
            <stop offset="100%" stop-color="{{ $s['gradient_color'] }}"/>
        </linearGradient>
        @foreach($blocks as $block)
            <clipPath id="box-{{ $block['id'] }}"><rect x="{{ $block['x'] }}" y="{{ $block['y'] }}" width="{{ $block['width'] }}" height="{{ $block['height'] }}"/></clipPath>
        @endforeach
    </defs>
    <rect width="1120" height="790" fill="{{ $s['background_mode'] === 'gradient' ? 'url(#certificate-background)' : $s['background_color'] }}"/>
    @if($background)
        <image width="1120" height="790" href="{{ $background }}" preserveAspectRatio="xMidYMid meet"/>
    @endif
    @if($s['border_style'] !== 'none')
        <rect x="25" y="25" width="1070" height="740" fill="none" stroke="{{ $s['border_color'] }}" stroke-width="3"/>
        @if($s['border_style'] === 'double')
            <rect x="35" y="35" width="1050" height="720" fill="none" stroke="{{ $s['border_color'] }}" stroke-width="1"/>
        @endif
    @endif
    @if($logoImage)
        <image x="{{ $s['logo']['x'] }}" y="{{ $s['logo']['y'] }}" width="{{ $s['logo']['width'] }}" height="{{ $s['logo']['height'] }}" href="{{ $logoImage }}" preserveAspectRatio="xMidYMid meet"/>
    @endif
    @foreach($blocks as $block)
        <g data-element="{{ $block['id'] }}" clip-path="url(#box-{{ $block['id'] }})" text-anchor="{{ $block['anchor'] }}" font-family="{{ $block['font'] }}" font-size="{{ $block['size'] }}" font-weight="{{ $block['weight'] }}" fill="{{ $block['color'] }}">
            @foreach($block['lines'] as $i => $line)
                <text x="{{ $block['text_x'] }}" y="{{ $block['y'] + $block['size'] + $i * $block['size'] * 1.2 }}">{{ $line }}</text>
            @endforeach
        </g>
    @endforeach
</svg>
