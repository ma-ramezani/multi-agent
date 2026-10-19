<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    protected $fillable = ['type', 'level', 'message', 'ip', 'meta'];
    protected $casts = ['meta' => 'array'];

    public static function log(string $type, string $message, string $level = 'info', array $meta = []): void
    {
        try {
            static::create([
                'type' => $type,
                'level' => $level,
                'message' => mb_substr($message, 0, 500),
                'ip' => request()?->ip(),
                'meta' => $meta ?: null,
            ]);
            // retention cleanup (cheap probabilistic prune)
            if (random_int(1, 50) === 1) {
                $days = SecuritySetting::int('audit_retention_days', 30);
                static::where('created_at', '<', now()->subDays(max(1, $days)))->delete();
            }
        } catch (\Throwable) {
            // auditing must never break the request
        }
    }

    public static function counts(): array
    {
        return [
            'blocked' => static::where('level', 'blocked')->count(),
            'warn' => static::where('level', 'warn')->count(),
            'total' => static::count(),
        ];
    }
}
