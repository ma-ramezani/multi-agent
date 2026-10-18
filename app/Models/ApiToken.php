<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiToken extends Model
{
    protected $fillable = ['name', 'token_hash', 'plain_prefix', 'agent_ids', 'last_used_at', 'expires_at', 'is_active'];
    protected $casts = ['agent_ids' => 'array', 'is_active' => 'boolean', 'last_used_at' => 'datetime', 'expires_at' => 'datetime'];

    public static function generate(string $name, ?array $agentIds = null): array
    {
        $plain = 'ak_' . bin2hex(random_bytes(24));
        $token = static::create([
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'plain_prefix' => substr($plain, 0, 10),
            'agent_ids' => $agentIds,
        ]);
        return [$token, $plain];
    }

    public function canAccessAgent(int $agentId): bool
    {
        if (empty($this->agent_ids)) return true;
        return in_array($agentId, $this->agent_ids);
    }
}
