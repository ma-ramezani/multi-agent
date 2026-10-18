<?php

namespace App\Neuron;

use App\Models\RagEnvironment;
use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use NeuronAI\RAG\Embeddings\GeminiEmbeddingsProvider;
use NeuronAI\RAG\Embeddings\OllamaEmbeddingsProvider;
use NeuronAI\RAG\Embeddings\OpenAIEmbeddingsProvider;
use NeuronAI\RAG\Embeddings\OpenAILikeEmbeddings;

class EmbeddingFactory
{
    public static function make(RagEnvironment $env): EmbeddingsProviderInterface
    {
        $key = $env->embedding_api_key ?: env('OPENAI_API_KEY', 'demo');

        return match ($env->embedding_provider) {
            'ollama' => new OllamaEmbeddingsProvider(
                model: $env->embedding_model ?: 'nomic-embed-text',
                url: $env->embedding_base_url ?: env('OLLAMA_URL', 'http://localhost:11434/api'),
            ),
            'gemini' => new GeminiEmbeddingsProvider(
                key: $key,
                model: $env->embedding_model ?: 'gemini-embedding-001',
            ),
            'openai_like' => new OpenAILikeEmbeddings(
                baseUri: rtrim($env->embedding_base_url ?: 'http://localhost:11434/v1', '/'),
                key: $key ?: 'ollama',
                model: $env->embedding_model ?: 'nomic-embed-text',
            ),
            default => new OpenAIEmbeddingsProvider(
                key: $key,
                model: $env->embedding_model ?: 'text-embedding-3-small',
            ),
        };
    }
}
