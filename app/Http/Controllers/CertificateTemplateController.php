<?php

namespace App\Http\Controllers;

use App\Services\CertificateTemplate;
use Illuminate\Http\Request;

class CertificateTemplateController extends Controller
{
    public function preview(Request $request)
    {
        $input = CertificateTemplate::normalize($request->validate(CertificateTemplate::rules()));
        $snapshot = [...CertificateTemplate::current(), ...$input];
        if ($request->boolean('remove_image')) $snapshot['image_path'] = null;
        if ($request->boolean('remove_logo')) $snapshot['logo_path'] = null;
        $image = $request->file('image');
        $data = $image ? 'data:'.$image->getMimeType().';base64,'.base64_encode(file_get_contents($image->getRealPath())) : null;
        $logo = $request->file('logo_image');
        $logoData = $logo ? 'data:'.$logo->getMimeType().';base64,'.base64_encode(file_get_contents($logo->getRealPath())) : null;

        return response()->json(['svg' => CertificateTemplate::render($snapshot, 'CFO-'.now()->year.'-PREVIEW', $data, $logoData)])
            ->header('Cache-Control', 'private, no-store');
    }
}
