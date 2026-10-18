<?php

namespace App\Neuron;

use App\Models\Agent;
use App\Models\RagEnvironment;
use NeuronAI\Agent\Agent as BaseAgent;

class AgentFactory
{
    public static function agentConfig(Agent $agent): array
    {
        return [
            'provider' => $agent->provider,
            'model' => $agent->model,
            'api_key' => $agent->api_key ?: self::envKeyFor($agent->provider),
            'base_url' => $agent->base_url,
            'system_prompt' => $agent->system_prompt,
            'temperature' => (float) $agent->temperature,
            'max_tokens' => $agent->max_tokens,
            'context_window' => (int) ($agent->context_window ?: 128000),
            'tools' => $agent->resolvedTools(),
            'tool_config' => $agent->tool_config ?? [],
        ];
    }

    protected static function envKeyFor(string $provider): ?string
    {
        return match ($provider) {
            'openai', 'openai_responses' => env('OPENAI_API_KEY'),
            'anthropic' => env('ANTHROPIC_API_KEY'),
            'gemini' => env('GEMINI_API_KEY'),
            'mistral' => env('MISTRAL_API_KEY'),
            'deepseek' => env('DEEPSEEK_API_KEY'),
            'grok' => env('GROK_API_KEY'),
            'opencode_go' => env('OPENCODE_GO_API_KEY'),
            default => null,
        };
    }

    public static function make(Agent $agent, ?string $threadId = null): BaseAgent
    {
        $config = self::agentConfig($agent);
        $ragIds = $agent->resolvedRagIds();

        if (!empty($ragIds)) {
            $envs = RagEnvironment::whereIn('id', $ragIds)->where('is_active', true)->get();
            if ($envs->isNotEmpty()) {
                $primary = $envs->first();
                return DynamicRag::fromConfig($config, [
                    'environment_ids' => $envs->pluck('id')->all(),
                    'primary_env' => $primary,
                    'top_k' => (int) ($agent->rag_top_k ?: 4),
                ], $threadId)->toolMaxRuns((int) ($agent->max_tool_runs ?: 10));
            }
        }

        return DynamicAgent::fromConfig($config, $threadId)
            ->toolMaxRuns((int) ($agent->max_tool_runs ?: 10));
    }
}
