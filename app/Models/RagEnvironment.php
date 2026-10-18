<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RagEnvironment extends Model
{
    protected $fillable = [
        'name', 'slug', 'description',
        'embedding_provider', 'embedding_model', 'embedding_api_key',
        'embedding_base_url', 'chunk_size', 'chunk_overlap', 'top_k',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function documents()
    {
        return $this->hasMany(RagDocument::class);
    }

    public function chunks()
    {
        return $this->hasMany(RagChunk::class);
    }

    public static function embeddingProviders(): array
    {
        return [
            'openai' => 'OpenAI',
            'ollama' => 'Ollama (local)',
            'gemini' => 'Gemini',
            'openai_like' => 'OpenAI-Like',
        ];
    }
}
