<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RagDocument extends Model
{
    protected $fillable = [
        'rag_environment_id', 'original_name', 'stored_path',
        'mime', 'size', 'status', 'error', 'chunks_count', 'raw_text', 'force_ocr',
    ];

    protected $casts = ['force_ocr' => 'boolean'];

    public function environment()
    {
        return $this->belongsTo(RagEnvironment::class, 'rag_environment_id');
    }

    public function chunks()
    {
        return $this->hasMany(RagChunk::class);
    }
}
