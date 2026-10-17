<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CertificateTemplate
{
    public const FONTS = [
        'Georgia' => 'Georgia,serif',
        'Times New Roman' => 'Times New Roman,Times,serif',
        'Arial' => 'Arial,Helvetica,sans-serif',
        'Verdana' => 'Verdana,Geneva,sans-serif',
        'Courier New' => 'Courier New,Courier,monospace',
    ];

    public static function defaults(): array
    {
        return ['title' => 'Certificate of Participation', 'description' => 'has participated in the recollection.', 'signatory' => 'Christian Formation Office', 'font_family' => 'Georgia', 'font_size' => 19, 'background_color' => '#fffdf4', 'image_path' => null];
    }

    public static function current(): array
    {
        return [...self::defaults(), ...CfoService::settings('certificate')];
    }

    public static function elementLabels(): array
    {
        return ['school' => 'School name', 'office' => 'Office', 'title' => 'Certificate title', 'intro' => 'Introduction', 'name' => 'Student name', 'description' => 'Body text', 'event' => 'Recollection', 'details' => 'Date and venue', 'year' => 'Year', 'signatory' => 'Signatory', 'number' => 'Certificate number'];
    }

    // Only the editor opts into this layout. Old issuance snapshots keep their renderer.
    public static function editorDefaults(array $saved = []): array
    {
        $s = [...self::defaults(), ...$saved];
        $elements = [];
        foreach ([
            ['school', 80, 40, 27, 'Christian Formation Office', 'bold'],
            ['office', 126, 32, 20, 'Christian Formation Office', 'normal'],
            ['title', 205, 70, 42, $s['title'], 'bold'],
            ['intro', 286, 32, 21, 'This is to certify that', 'normal'],
            ['name', 335, 70, 46, '{{student_name}}', 'bold'],
            ['description', 417, 52, 21, $s['description'], 'normal'],
            ['event', 479, 34, 24, '{{event}}', 'bold'],
            ['details', 524, 50, 20, '{{recollection_date}} | {{venue}}', 'normal'],
            ['year', 584, 26, 16, 'Recollection Year {{year}}', 'normal'],
            ['signatory', 647, 42, 24, $s['signatory'], 'bold'],
            ['number', 721, 24, 14, '{{certificate_number}}', 'normal'],
        ] as [$id, $y, $height, $size, $text, $weight]) {
            $elements[$id] = ['text' => $text, 'x' => 180, 'y' => $y, 'width' => 760, 'height' => $height,
                'font_family' => $s['font_family'], 'font_size' => $size, 'color' => '#132b43', 'align' => 'center', 'weight' => $weight];
        }
        return [...$s, 'layout_version' => 2, 'elements' => $elements, 'background_mode' => 'solid',
            'gradient_color' => '#f0e4c8', 'gradient_direction' => 'diagonal', 'border_style' => 'double', 'border_color' => '#bc9746',
            'logo_path' => null, 'logo' => ['x' => 70, 'y' => 68, 'width' => 84, 'height' => 84]];
    }

    public static function rules(): array
    {
        $rules = [
            'title' => 'required|string|max:190', 'description' => 'required|string|max:500', 'signatory' => 'required|string|max:190',
            'font_family' => ['sometimes', 'required', Rule::in(array_keys(self::FONTS))],
            'font_size' => 'sometimes|required|integer|between:14,26',
            'background_color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:5120|dimensions:max_width=6000,max_height=6000',
            'remove_image' => 'sometimes|boolean',
            'logo_image' => 'nullable|image|mimes:jpg,jpeg,png|max:5120|dimensions:max_width=6000,max_height=6000',
            'remove_logo' => 'sometimes|boolean',
            'layout_version' => 'sometimes|required|integer|in:2',
            'background_mode' => 'required_if:layout_version,2|in:solid,gradient',
            'gradient_color' => ['required_if:layout_version,2', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'gradient_direction' => 'required_if:layout_version,2|in:horizontal,vertical,diagonal',
            'border_style' => 'required_if:layout_version,2|in:none,simple,double',
            'border_color' => ['required_if:layout_version,2', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'elements' => ['required_if:layout_version,2', 'array:'.implode(',', array_keys(self::elementLabels()))],
            'logo' => ['required_if:layout_version,2', 'array:x,y,width,height', self::withinCanvas()],
        ];
        foreach (['x' => '0,1080', 'y' => '0,770', 'width' => '40,1120', 'height' => '20,790'] as $key => $range) {
            $rules['logo.'.$key] = 'required_with:logo|numeric|between:'.$range;
        }
        foreach (array_keys(self::elementLabels()) as $id) {
            $prefix = 'elements.'.$id;
            $rules[$prefix] = ['required_with:elements', 'array:text,x,y,width,height,font_family,font_size,color,align,weight', self::withinCanvas()];
            $rules[$prefix.'.text'] = 'required_with:elements|string|max:500';
            $rules[$prefix.'.font_family'] = ['required_with:elements', Rule::in(array_keys(self::FONTS))];
            $rules[$prefix.'.font_size'] = 'required_with:elements|numeric|between:10,80';
            $rules[$prefix.'.color'] = ['required_with:elements', 'regex:/^#[0-9a-fA-F]{6}$/'];
            $rules[$prefix.'.align'] = 'required_with:elements|in:left,center,right';
            $rules[$prefix.'.weight'] = 'required_with:elements|in:normal,bold';
            foreach (['x' => '0,1080', 'y' => '0,770', 'width' => '40,1120', 'height' => '20,790'] as $key => $range) {
                $rules[$prefix.'.'.$key] = 'required_with:elements|numeric|between:'.$range;
            }
        }
        return $rules;
    }

    private static function withinCanvas(): \Closure
    {
        return static function ($attribute, $value, $fail) {
            if (! is_array($value)) return;
            foreach (['x', 'y', 'width', 'height'] as $key) if (! isset($value[$key]) || ! is_numeric($value[$key])) return;
            if ($value['x'] + $value['width'] > 1120 || $value['y'] + $value['height'] > 790) {
                $fail('The '.$attribute.' block must fit inside the 1120 by 790 certificate.');
            }
        };
    }

    public static function normalize(array $validated): array
    {
        // Multipart fields arrive as strings; keep coordinates numeric across reloads.
        foreach (['layout_version', 'font_size'] as $key) {
            if (isset($validated[$key])) $validated[$key] = (int) $validated[$key];
        }
        foreach ($validated['elements'] ?? [] as $id => $element) {
            foreach (['x', 'y', 'width', 'height', 'font_size'] as $key) $validated['elements'][$id][$key] = (float) $element[$key];
        }
        foreach ($validated['logo'] ?? [] as $key => $value) $validated['logo'][$key] = (float) $value;
        return $validated;
    }

    public static function render(array $snapshot, string $number, ?string $previewImage = null, ?string $previewLogo = null): string
    {
        if (($snapshot['layout_version'] ?? null) == 2) return self::renderLayout($snapshot, $number, $previewImage, $previewLogo);
        $s = [...self::defaults(), ...$snapshot];
        $background = $previewImage;
        if ($background === null && $s['image_path'] && Storage::disk(config('filesystems.upload_disk'))->exists($s['image_path'])) {
            $background = 'data:'.Storage::disk(config('filesystems.upload_disk'))->mimeType($s['image_path']).';base64,'.base64_encode(Storage::disk(config('filesystems.upload_disk'))->get($s['image_path']));
        }
        $font = self::FONTS[$s['font_family']] ?? self::FONTS['Georgia'];
        $scale = max(14, min(26, (int) $s['font_size'])) / 19;
        $legacy = ! array_key_exists('font_family', $snapshot) && ! array_key_exists('font_size', $snapshot);
        // Fit long names and descriptions within their own reserved space.
        $blocks = [];
        foreach ([
            [110, 26, 35, 'Christian Formation Office'], [150, 20, 30, 'Christian Formation Office'],
            [245, 38, 65, $s['title']], [310, 22, 35, 'This is to certify that'],
            [380, 38, 60, $s['name'] ?? 'Juan Dela Cruz'], [435, 19, 65, $s['description']],
            [$legacy ? 480 : 520, 20, 42, $s['event'] ?? 'Annual Recollection'],
            [$legacy ? 525 : 565, 19, 42, ($s['date'] ?? now()->toDateString()).' · '.($s['venue'] ?? 'University Chapel')],
            [$legacy ? 625 : 650, 24, 60, $s['signatory']], [$legacy ? 715 : 730, 16, 28, $number],
        ] as [$y, $size, $height, $text]) {
            $size *= $scale;
            $lines = $legacy ? [(string) $text] : self::wrap((string) $text, max(20, (int) (920 / ($size * .65))));
            if (! $legacy) $size = min($size, $height / (count($lines) * 1.2));
            foreach ($lines as $i => $line) $blocks[] = ['y' => $y + $i * $size * 1.2, 'size' => round($size, 2), 'text' => $line];
        }

        return view('certificates.document', compact('s', 'font', 'background', 'blocks'))->render();
    }

    private static function embeddedImage(?string $path): ?string
    {
        return $path && Storage::disk(config('filesystems.upload_disk'))->exists($path)
            ? 'data:'.Storage::disk(config('filesystems.upload_disk'))->mimeType($path).';base64,'.base64_encode(Storage::disk(config('filesystems.upload_disk'))->get($path)) : null;
    }

    private static function renderLayout(array $snapshot, string $number, ?string $previewImage, ?string $previewLogo): string
    {
        $s = [...self::editorDefaults(), ...$snapshot];
        $background = $previewImage ?? self::embeddedImage($s['image_path']);
        $logoImage = $previewLogo ?? self::embeddedImage($s['logo_path']);
        $tokens = ['{{student_name}}' => $s['name'] ?? 'Juan Dela Cruz', '{{recollection_date}}' => $s['date'] ?? now()->toDateString(),
            '{{event}}' => $s['event'] ?? 'Annual Recollection', '{{venue}}' => $s['venue'] ?? 'University Chapel',
            '{{year}}' => (string) ($s['year'] ?? now()->year), '{{certificate_number}}' => $number];
        $blocks = [];
        foreach ($s['elements'] as $id => $element) {
            $text = strtr($element['text'], $tokens);
            $size = (float) $element['font_size'];
            // Reflow first, then shrink if necessary; never spill beyond the selected box.
            do {
                $lines = [];
                foreach (explode("\n", str_replace("\r", '', $text)) as $line) {
                    array_push($lines, ...self::wrap($line, max(1, (int) ($element['width'] / ($size * .68)))));
                }
                if (count($lines) * $size * 1.2 <= $element['height'] || $size <= 6) break;
                $size -= .5;
            } while (true);
            $size = min($size, $element['height'] / (count($lines) * 1.2));
            $anchor = ['left' => 'start', 'center' => 'middle', 'right' => 'end'][$element['align']];
            $x = $element['x'] + ['left' => 0, 'center' => $element['width'] / 2, 'right' => $element['width']][$element['align']];
            $blocks[] = [...$element, 'id' => $id, 'lines' => $lines, 'size' => round($size, 2), 'anchor' => $anchor,
                'text_x' => $x, 'font' => self::FONTS[$element['font_family']]];
        }
        return view('certificates.layout', compact('s', 'background', 'logoImage', 'blocks'))->render();
    }

    private static function wrap(string $text, int $limit): array
    {
        $text = preg_replace('/\s+/u', ' ', $text);
        $lines = [];
        while (mb_strlen($text) > $limit) {
            $space = mb_strrpos(mb_substr($text, 0, $limit), ' ');
            $cut = $space !== false && $space > $limit / 2 ? $space : $limit;
            $lines[] = mb_substr($text, 0, $cut);
            $text = ltrim(mb_substr($text, $cut));
        }
        $lines[] = $text;
        return $lines;
    }
}
