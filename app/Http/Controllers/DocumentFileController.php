<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves document files from the private disk (SRS §197): only to users who can see the
 * document, sensitive types only to permitted users; every access is logged and
 * sensitive downloads are also written to the audit log.
 */
class DocumentFileController extends Controller
{
    public function __invoke(Request $request, Document $document, ?DocumentVersion $version = null): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->can('documents.view'), 403);
        abort_unless(Document::query()->visibleTo($user)->whereKey($document->id)->exists(), 404);

        $document->load(['type', 'currentVersion']);
        abort_unless($document->canViewFile($user), 403);

        $version ??= $document->currentVersion;
        abort_if($version === null || $version->document_id !== $document->id, 404);
        abort_unless(Storage::disk($version->disk)->exists($version->path), 404);

        $download = $request->boolean('download');

        $document->accessLogs()->create([
            'document_version_id' => $version->id,
            'user_id' => $user->id,
            'action' => $download ? 'download' : 'view',
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
        ]);

        if ($document->type->isSensitive()) {
            app(AuditService::class)->record('sensitive_document_accessed', 'documents', $document, [], ['version' => $version->version, 'action' => $download ? 'download' : 'view']);
        }

        $headers = ['Content-Type' => $version->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];

        return $download
            ? Storage::disk($version->disk)->download($version->path, $version->original_name, $headers)
            : Storage::disk($version->disk)->response($version->path, $version->original_name, $headers);
    }
}
