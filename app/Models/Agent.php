<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    protected $fillable = [
        'name', 'slug', 'description',
        'provider', 'model', 'api_key', 'base_url',
        'system_prompt', 'temperature', 'max_tokens',
        'context_window', 'max_tool_runs',
        'tools', 'tool_config',
        'rag_environment_ids', 'rag_top_k',
        'is_active',
    ];

    protected $casts = [
        'tools' => 'array',
        'tool_config' => 'array',
        'rag_environment_ids' => 'array',
        'temperature' => 'float',
        'is_active' => 'boolean',
    ];

    public function chatSessions()
    {
        return $this->hasMany(ChatSession::class);
    }

    public function ragEnvironments()
    {
        $ids = $this->rag_environment_ids ?? [];
        if (empty($ids)) return RagEnvironment::whereRaw('1=0');
        return RagEnvironment::whereIn('id', $ids);
    }

    public function resolvedTools(): array
    {
        return $this->tools ?? [];
    }

    public function resolvedRagIds(): array
    {
        return $this->rag_environment_ids ?? [];
    }

    public function mcpServers()
    {
        return $this->belongsToMany(McpServer::class, 'agent_mcp_server');
    }

    public static function providers(): array
    {
        return [
            'openai' => 'OpenAI',
            'opencode_go' => 'OpenCode Go (ارزان/رایگان)',
            'openai_responses' => 'OpenAI Responses',
            'anthropic' => 'Anthropic',
            'gemini' => 'Gemini',
            'ollama' => 'Ollama (local)',
            'mistral' => 'Mistral',
            'deepseek' => 'DeepSeek',
            'grok' => 'Grok (xAI)',
            'openai_like' => 'OpenAI-Like (custom)',
            'azure' => 'Azure OpenAI',
            'cohere' => 'Cohere',
        ];
    }
}
