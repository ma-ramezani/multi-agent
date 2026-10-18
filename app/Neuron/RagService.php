<?php

namespace App\Neuron;

use App\Models\RagChunk;
use App\Models\RagDocument;
use App\Models\RagEnvironment;
use NeuronAI\RAG\DataLoader\StringDataLoader;
use NeuronAI\RAG\Document;

class RagService
{
    public static function chunkText(string $text, int $size = 1000, int $overlap = 100): array
    {
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
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['txt', 'md', 'markdown', 'csv', 'json', 'log', 'html', 'htm', 'xml'])) {
            $t = @file_get_contents($path);
            if ($ext === 'html' || $ext === 'htm') {
                $t = strip_tags($t);
            }
            return (string) $t;
        }
        if ($ext === 'pdf' && class_exists(\Smalot\PdfParser\Parser::class)) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($path);
                return $pdf->getText();
            } catch (\Throwable $e) {
                return '';
            }
        }
        // Fallback: try raw read (works for plain text)
        $raw = @file_get_contents($path);
        if ($raw !== false && mb_check_encoding($raw, 'UTF-8')) {
            // strip binary garbage heuristic
            if (substr_count($raw, "\0") > 0) {
                return '';
            }
            return $raw;
        }
        return '';
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
            if ($doc->raw_text) {
                $text = $doc->raw_text;
            } elseif ($doc->stored_path && \Storage::disk('local')->exists($doc->stored_path)) {
                $text = self::extractText(\Storage::disk('local')->path($doc->stored_path), $doc->mime);
            } else {
                throw new \RuntimeException('فایل یافت نشد');
            }

            if (trim($text) === '') {
                throw new \RuntimeException('متن قابل استخرجی از فایل به دست نیامد (فعلاً txt/md/csv/json پشتیبانی می‌شود)');
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
                    'metadata' => ['chunk_index' => $i],
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
