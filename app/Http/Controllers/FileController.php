<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\RagDocument;

class FileController extends Controller
{
    /** Download a file attached to a chat message. */
    public function chatAttachment(ChatMessage $message, int $index)
    {
        $atts = $message->attachments ?? [];
        if (!isset($atts[$index])) {
            abort(404, 'فایل یافت نشد');
        }
        $disk = \Storage::disk('local');
        $abs = $disk->path(ltrim($atts[$index]['path'] ?? '', '/'));
        $real = realpath($abs);
        $root = (string) realpath($disk->path(''));
        if (!$real || !is_file($real) || !str_starts_with($real, $root)) {
            abort(404, 'فایل یافت نشد');
        }
        return response()->download($real, $atts[$index]['name'] ?? basename($real));
    }

    /** Download an original RAG document file (if stored). */
    public function ragDocument(RagDocument $document)
    {
        if ($document->stored_path && \Storage::disk('local')->exists($document->stored_path)) {
            return \Storage::disk('local')->download($document->stored_path, $document->original_name);
        }
        if ($document->raw_text) {
            return response($document->raw_text, 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $document->original_name . '.txt"',
            ]);
        }
        abort(404, 'فایل یافت نشد');
    }
}
