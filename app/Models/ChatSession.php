<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    protected $fillable = ['agent_id', 'title', 'thread_id', 'meta'];
    protected $casts = ['meta' => 'array'];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }

    protected static function booted()
    {
        static::creating(function ($s) {
            if (empty($s->thread_id)) {
                $s->thread_id = 'th_' . bin2hex(random_bytes(12));
            }
        });
    }
}
