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

        try {
            $embedding = $this->embeddingProvider->embedText($text);
            $docs = $this->vectorStore->search(new SearchRequest(
                embedding: $embedding,
                filters: FilterScope::merge(null, $filters)?->expression(),
                topK: $this->topK,
            ));
            return is_array($docs) ? array_values($docs) : iterator_to_array($docs, false);
        } catch (Throwable $e) {
            // Embedding failed (no API key / offline) → keyword fallback
            return $this->vectorStore->keywordSearch($text, $this->topK);
        }
    }
}
