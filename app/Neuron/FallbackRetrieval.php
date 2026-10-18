<?php

namespace App\Neuron;

use NeuronAI\Chat\Messages\Message;
use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use NeuronAI\RAG\Retrieval\RetrievalInterface;
use NeuronAI\RAG\VectorStore\Filter\FilterExpression;
use NeuronAI\RAG\VectorStore\Filter\FilterScope;
use NeuronAI\RAG\VectorStore\SearchRequest;
use Throwable;

class FallbackRetrieval implements RetrievalInterface
{
    public function __construct(
        protected DatabaseVectorStore $vectorStore,
        protected EmbeddingsProviderInterface $embeddingProvider,
        protected int $topK = 4,
    ) {
    }

    public function retrieve(Message $query, ?FilterExpression $filters = null): array
    {
        $text = $query->getContent();
        if ($text === null || trim($text) === '') {
            return [];
        }

        $vectorDocs = [];
        try {
            $embedding = $this->embeddingProvider->embedText($text);
            $docs = $this->vectorStore->search(new SearchRequest(
                embedding: $embedding,
                filters: FilterScope::merge(null, $filters)?->expression(),
                topK: $this->topK,
            ));
            $vectorDocs = is_array($docs) ? array_values($docs) : iterator_to_array($docs, false);
        } catch (Throwable $e) {
            // Embedding failed (no API key / offline) → keyword fallback
            return $this->vectorStore->keywordSearch($text, $this->topK);
        }

        // Hybrid: add keyword hits missing from vector results (exact terms,
        // names, phone numbers). Total capped at 2×topK.
        $seen = [];
        foreach ($vectorDocs as $d) {
            $seen[$this->docKey($d)] = true;
        }
        $merged = $vectorDocs;
        foreach ($this->vectorStore->keywordSearch($text, $this->topK) as $d) {
            if (count($merged) >= $this->topK * 2) {
                break;
            }
            if (!isset($seen[$this->docKey($d)])) {
                $seen[$this->docKey($d)] = true;
                $merged[] = $d;
            }
        }
        return $merged;
    }

    protected function docKey($doc): string
    {
        $m = method_exists($doc, 'getMetadata') ? ($doc->getMetadata() ?? []) : [];
        return (string) ($m['rag_chunk_id'] ?? $doc->getSourceName() . '|' . mb_substr($doc->getContent(), 0, 60));
    }
}
