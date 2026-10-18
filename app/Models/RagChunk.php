<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RagChunk extends Model
{
    protected $fillable = [
        'rag_environment_id', 'rag_document_id', 'content',
        'embedding', 'metadata', 'source_type', 'source_name',
    ];

    protected $casts = ['metadata' => 'array'];

    public function environment()
    {
        return $this->belongsTo(RagEnvironment::class, 'rag_environment_id');
    }

    public function document()
    {
        return $this->belongsTo(RagDocument::class, 'rag_document_id');
    }

    public function getEmbeddingArray(): ?array
    {
        if (!$this->embedding) return null;
        $v = json_decode($this->embedding, true);
        return is_array($v) ? $v : null;
    }
}
