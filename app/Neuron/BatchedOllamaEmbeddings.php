<?php

namespace App\Neuron;

use NeuronAI\RAG\Document;
use NeuronAI\RAG\Embeddings\OllamaEmbeddingsProvider;

/**
 * Ollama embeddings with true batching.
 *
 * Neuron's default embedDocuments() calls the API once per chunk.
 * Ollama's /api/embed accepts an array of inputs and returns all
 * vectors in a single call — ~15x faster ingestion.
 */
class BatchedOllamaEmbeddings extends OllamaEmbeddingsProvider
{
    public function __construct(
        string $model,
        string $url = 'http://localhost:11434/api',
        array $parameters = [],
        $httpClient = null,
        protected int $batchSize = 20,
    ) {
        parent::__construct($model, $url, $parameters, $httpClient);
    }

    /**
     * @param Document[] $documents
     * @return Document[]
     */
    public function embedDocuments(array $documents): array
    {
        if ($documents === []) {
            return [];
        }
        foreach (array_chunk($documents, max(1, $this->batchSize)) as $batch) {
            $texts = array_map(fn (Document $d) => $d->getContent(), $batch);
            $vectors = $this->embedBatch($texts);
            foreach ($batch as $i => $doc) {
                if (isset($vectors[$i])) {
                    $doc->setEmbedding($vectors[$i]);
                } else {
                    // fall back to single call for this document
                    $doc->setEmbedding($this->embedText($doc->getContent()));
                }
            }
        }
        return $documents;
    }

    /** @return array<int, float[]> */
    protected function embedBatch(array $texts): array
    {
        $response = $this->httpClient->request(
            \NeuronAI\HttpClient\HttpRequest::post(
                uri: rtrim($this->baseUri, '/') . '/embed',
                body: [
                    'model' => $this->model,
                    'input' => array_values($texts),
                    ...$this->parameters,
                ],
                headers: $this->httpHeaders,
            )
        )->json();

        return $response['embeddings'] ?? [];
    }
}
