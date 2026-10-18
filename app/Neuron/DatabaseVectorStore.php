<?php

namespace App\Neuron;

use App\Models\RagChunk;
use NeuronAI\RAG\Document;
use NeuronAI\RAG\Schema\DocumentSchema;
use NeuronAI\RAG\VectorSimilarity;
use NeuronAI\RAG\VectorStore\Filter\FilterExpression;
use NeuronAI\RAG\VectorStore\SearchRequest;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;

/**
 * VectorStore backed by `rag_chunks` table.
 * Scoped to one or more RagEnvironment ids.
 * If chunks have no embeddings (no API key), falls back to keyword search.
 */
class DatabaseVectorStore implements VectorStoreInterface
{
    protected DocumentSchema $schema;

    public function __construct(
        protected array $environmentIds,
        protected int $topK = 4,
        ?DocumentSchema $schema = null,
    ) {
        $this->schema = $schema ?? DocumentSchema::of();
    }

    public function getSchema(): DocumentSchema
    {
        return $this->schema;
    }

    public function addDocument(Document $document): VectorStoreInterface
    {
        return $this->addDocuments([$document]);
    }

    public function addDocuments(array $documents): VectorStoreInterface
    {
        foreach ($documents as $doc) {
            $envId = $doc->getMetadata()['rag_environment_id'] ?? ($this->environmentIds[0] ?? null);
            if (!$envId) {
                continue;
            }
            RagChunk::create([
                'rag_environment_id' => $envId,
                'rag_document_id' => $doc->getMetadata()['rag_document_id'] ?? null,
                'content' => $doc->getContent(),
                'embedding' => $doc->getEmbedding() ? json_encode($doc->getEmbedding()) : null,
                'metadata' => array_diff_key($doc->getMetadata(), ['rag_environment_id' => 1, 'rag_document_id' => 1]),
                'source_type' => $doc->getSourceType(),
                'source_name' => $doc->getSourceName(),
            ]);
        }
        return $this;
    }

    public function delete(FilterExpression $filters): VectorStoreInterface
    {
        // Best-effort: delete by sourceType/sourceName encoded in filter is complex;
        // for our app we delete via explicit methods. Support full wipe per env:
        RagChunk::whereIn('rag_environment_id', $this->environmentIds)->delete();
        return $this;
    }

    public function deleteBySource(string $sourceType, string $sourceName): void
    {
        RagChunk::whereIn('rag_environment_id', $this->environmentIds)
            ->where('source_type', $sourceType)
            ->where('source_name', $sourceName)
            ->delete();
    }

    public function search(SearchRequest $request): iterable
    {
        $queryEmbedding = $request->embedding ?? null;
        $topK = $request->topK ?? $this->topK;

        $chunks = RagChunk::whereIn('rag_environment_id', $this->environmentIds)
            ->orderBy('id')
            ->limit(2000)
            ->get();

        if ($chunks->isEmpty()) {
            return [];
        }

        $scored = [];
        if (is_array($queryEmbedding) && count($queryEmbedding) > 0) {
            foreach ($chunks as $chunk) {
                $emb = $chunk->getEmbeddingArray();
                if (!$emb) {
                    continue;
                }
                $score = $this->cosine($queryEmbedding, $emb);
                $scored[] = [$score, $chunk];
            }
            usort($scored, fn ($a, $b) => $b[0] <=> $a[0]);
        } else {
            // No embedding available — caller should use keywordSearch() instead.
            foreach ($chunks as $chunk) {
                $scored[] = [0.0, $chunk];
            }
        }

        $results = [];
        foreach (array_slice($scored, 0, $topK) as [$score, $chunk]) {
            $doc = new Document($chunk->content);
            $doc->setSourceType($chunk->source_type ?? 'file');
            $doc->setSourceName($chunk->source_name ?? ('chunk#' . $chunk->id));
            $doc->setScore((float) $score);
            $doc->setMetadata(array_merge($chunk->metadata ?? [], [
                'rag_environment_id' => $chunk->rag_environment_id,
                'rag_chunk_id' => $chunk->id,
            ]));
            $results[] = $doc;
        }

        return $results;
    }

    /**
     * Fallback keyword search when embeddings are unavailable.
     */
    public function keywordSearch(string $query, int $topK = 4): array
    {
        $chunks = RagChunk::whereIn('rag_environment_id', $this->environmentIds)->get();
        $terms = preg_split('/\s+/u', mb_strtolower($query));
        $terms = array_filter($terms, fn ($t) => mb_strlen($t) > 1);

        $scored = [];
        foreach ($chunks as $chunk) {
            $text = mb_strtolower($chunk->content);
            $raw = 0;
            $distinct = 0;
            foreach ($terms as $t) {
                $c = substr_count($text, $t);
                if ($c > 0) {
                    $raw += $c;
                    $distinct++;
                }
            }
            // small boost for exact phrase
            if (mb_stripos($chunk->content, $query) !== false) {
                $raw += 5;
                $distinct++;
            }
            if ($raw > 0) {
                // density: prefer concentrated matches over long repetitive chunks
                $score = ($raw + $distinct * 3) / (1 + mb_strlen($chunk->content) / 400);
                $scored[] = [$score, $chunk];
            }
        }
        usort($scored, fn ($a, $b) => $b[0] <=> $a[0]);

        $results = [];
        foreach (array_slice($scored, 0, $topK) as [$score, $chunk]) {
            $norm = $score > 0 ? min(0.99, $score / 8) : 0;
            $doc = new Document($chunk->content);
            $doc->setSourceType($chunk->source_type ?? 'file');
            $doc->setSourceName($chunk->source_name ?? ('chunk#' . $chunk->id));
            $doc->setScore((float) $norm);
            $doc->setMetadata(array_merge($chunk->metadata ?? [], [
                'rag_environment_id' => $chunk->rag_environment_id,
                'rag_chunk_id' => $chunk->id,
            ]));
            $results[] = $doc;
        }
        return $results;
    }

    protected function cosine(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na == 0 || $nb == 0) {
            return 0.0;
        }
        return $dot / (sqrt($na) * sqrt($nb));
    }
}
