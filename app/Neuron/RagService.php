<?php

namespace App\Neuron;

use App\Models\RagChunk;
use App\Models\RagDocument;
use App\Models\RagEnvironment;
use NeuronAI\RAG\DataLoader\StringDataLoader;
use NeuronAI\RAG\Document;

class RagService
{
    /** Files that may take minutes (OCR/embeddings) go to the queue worker. */
    public static function needsBackground(RagDocument $doc): bool
    {
        if ($doc->force_ocr) {
            return true;
        }
        if ($doc->raw_text) {
            return mb_strlen($doc->raw_text) > 200000;
        }
        $ext = strtolower(pathinfo($doc->original_name ?? '', PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            return true; // may require per-page OCR
        }
        return ((int) ($doc->size ?? 0)) > 3 * 1024 * 1024;
    }

    /** Ingest now for light files, or dispatch to the queue for heavy ones. */
    public static function ingestSoon(RagDocument $doc): void
    {
        if (self::needsBackground($doc)) {
            $doc->update(['status' => 'processing', 'error' => null]);
            \App\Jobs\IngestRagDocument::dispatch($doc->id);
        } else {
            self::ingestDocument($doc);
        }
    }
    public static function chunkText(string $text, int $size = 1000, int $overlap = 100): array
    {
        $size = max(200, $size);
        // Guard: overlap must stay well below size, otherwise the sliding
        // window barely advances and chunk count explodes.
        $overlap = max(0, min($overlap, (int) ($size / 2)));
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return [];
        }
        $chunks = [];
        $len = mb_strlen($text);
        $start = 0;
        $step = max(1, $size - $overlap);
        while ($start < $len) {
            $part = mb_substr($text, $start, $size);
            // try to cut on sentence boundary (only if more text remains)
            if ($start + $size < $len) {
                $candidates = [mb_strrpos($part, '.'), mb_strrpos($part, '。'), mb_strrpos($part, "\n"), mb_strrpos($part, '؟'), mb_strrpos($part, '?')];
                $candidates = array_filter($candidates, fn ($v) => $v !== false);
                $lastDot = $candidates ? max($candidates) : false;
                if ($lastDot !== false && $lastDot > $size * 0.5) {
                    $part = mb_substr($part, 0, $lastDot + 1);
                    $chunks[] = trim($part);
                    $start += mb_strlen($part) - min($overlap, (int) (mb_strlen($part) / 2));
                    continue;
                }
            }
            $chunks[] = trim($part);
            if ($start + $size >= $len) {
                break;
            }
            $start += $step;
        }
        return array_values(array_filter($chunks, fn ($c) => $c !== ''));
    }

    public static function extractText(string $path, ?string $mime = null): string
    {
        $abs = $path;
        if (!str_starts_with($abs, '/') && !preg_match('#^[A-Za-z]:\\\\#', $abs)) {
            $abs = \Storage::disk('local')->path($path);
        }
        $r = DocumentReader::read($abs, $mime, basename($path));
        return $r['text'];
    }

    public static function ingestDocument(RagDocument $doc): void
    {
        $env = $doc->environment;
        if (!$env) {
            $doc->update(['status' => 'failed', 'error' => 'محیط RAG یافت نشد']);
            return;
        }
        $doc->update(['status' => 'processing', 'error' => null]);
        try {
            $readKind = 'manual';
            $readDetail = '';
            if ($doc->raw_text && !$doc->force_ocr) {
                $text = $doc->raw_text;
            } elseif ($doc->stored_path && \Storage::disk('local')->exists($doc->stored_path)) {
                $abs = \Storage::disk('local')->path($doc->stored_path);
                $r = DocumentReader::read($abs, $doc->mime, $doc->original_name, (bool) $doc->force_ocr);
                $text = $r['text'];
                $readKind = $r['kind'];
                $readDetail = $r['detail'];
                if (trim($text) === '') {
                    throw new \RuntimeException($r['detail'] ?: 'از فایل متنی استخراج نشد');
                }
            } else {
                throw new \RuntimeException('فایل یافت نشد');
            }

            if (trim($text) === '') {
                throw new \RuntimeException('متن قابل استخرجی از فایل به دست نیامد');
            }

            $parts = self::chunkText($text, $env->chunk_size ?: 1000, $env->chunk_overlap ?: 100);

            // Clear old chunks
            RagChunk::where('rag_document_id', $doc->id)->delete();

            $embeddings = null;
            try {
                $provider = EmbeddingFactory::make($env);
                // embed in batches
                $embeddings = [];
                foreach (array_chunk($parts, 20) as $batch) {
                    $docs = array_map(fn ($t) => new Document($t), $batch);
                    $embedded = $provider->embedDocuments($docs);
                    foreach ($embedded as $d) {
                        $embeddings[] = $d->getEmbedding();
                    }
                }
            } catch (\Throwable $e) {
                $embeddings = array_fill(0, count($parts), null); // keyword fallback
            }

            foreach ($parts as $i => $part) {
                RagChunk::create([
                    'rag_environment_id' => $env->id,
                    'rag_document_id' => $doc->id,
                    'content' => $part,
                    'embedding' => !empty($embeddings[$i]) ? json_encode($embeddings[$i]) : null,
                    'metadata' => array_merge(['chunk_index' => $i], array_filter([
                        'read_kind' => $readKind ?? null,
                        'read_detail' => $readDetail ?? null,
                    ])),
                    'source_type' => 'file',
                    'source_name' => $doc->original_name,
                ]);
            }

            $doc->update(['status' => 'ready', 'chunks_count' => count($parts), 'error' => null]);
        } catch (\Throwable $e) {
            $doc->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }
}
