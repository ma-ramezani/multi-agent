<?php

namespace App\Neuron;

use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Cohere\Cohere;
use NeuronAI\Providers\Deepseek\Deepseek;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\Mistral\Mistral;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAI\Responses\OpenAIResponses;
use NeuronAI\Providers\OpenAILike;
use NeuronAI\Providers\XAI\Grok;
use NeuronAI\Providers\ZAI\ZAI;

class ProviderFactory
{
    public static function make(
        string $provider,
        string $model,
        ?string $apiKey = null,
        ?string $baseUrl = null,
        array $parameters = [],
        ?string $sessionId = null,
    ): AIProviderInterface {
        $key = $apiKey ?: config('services.neuron.fallback_key', 'demo');
        $params = $parameters;

        return match ($provider) {
            'opencode_go' => new \App\Neuron\Providers\OpenCodeGo(
                key: $key ?: env('OPENCODE_GO_API_KEY', 'demo'),
                model: $model ?: \App\Neuron\Providers\OpenCodeGo::DEFAULT_MODEL,
                sessionId: $sessionId,
                parameters: $params,
                baseUrl: $baseUrl ?: \App\Neuron\Providers\OpenCodeGo::DEFAULT_BASE_URL,
            ),
            'anthropic' => new Anthropic(key: $key, model: $model ?: 'claude-3-5-sonnet-20241022', parameters: $params),
            'gemini' => new Gemini(key: $key, model: $model ?: 'gemini-2.0-flash', parameters: $params),
            'ollama' => new Ollama(url: $baseUrl ?: env('OLLAMA_URL', 'http://localhost:11434/api'), model: $model ?: 'llama3.1', parameters: $params),
            'mistral' => new Mistral(key: $key, model: $model ?: 'mistral-large-latest', parameters: $params),
            'deepseek' => new Deepseek(key: $key, model: $model ?: 'deepseek-chat', parameters: $params),
            'grok' => new Grok(key: $key, model: $model ?: 'grok-3', parameters: $params),
            'zai' => new ZAI(key: $key, model: $model ?: 'glm-4', parameters: $params),
            'cohere' => new Cohere(key: $key, model: $model ?: 'command-a-03-2025'),
            'openai_like' => new OpenAILike(baseUri: rtrim($baseUrl ?: 'http://localhost:11434/v1', '/'), key: $key ?: 'ollama', model: $model ?: 'llama3.1', parameters: $params),
            'azure' => new OpenAILike(baseUri: rtrim($baseUrl ?: '', '/'), key: $key, model: $model, parameters: $params),
            'openai_responses' => new OpenAIResponses(key: $key, model: $model ?: 'gpt-4o-mini', parameters: $params),
            default => new OpenAI(key: $key, model: $model ?: 'gpt-4o-mini', parameters: $params),
        };
    }

    public static function parametersFor(float $temperature = 0.7, ?int $maxTokens = null): array
    {
        $p = ['temperature' => $temperature];
        if ($maxTokens) {
            $p['max_tokens'] = $maxTokens;
        }
        return $p;
    }
}
