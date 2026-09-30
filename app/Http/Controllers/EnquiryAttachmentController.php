<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\EnquiryAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves enquiry files from the private disk only to users who can see the enquiry.
 */
class EnquiryAttachmentController extends Controller
{
    public function __invoke(Request $request, EnquiryAttachment $attachment): StreamedResponse
    {
        abort_unless(Enquiry::query()->visibleTo($request->user())->whereKey($attachment->enquiry_id)->exists(), 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
