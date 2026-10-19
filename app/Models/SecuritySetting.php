<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SecuritySetting extends Model
{
    public $incrementing = false;
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];
    public $timestamps = true;

    public const DEFAULTS = [
        // guardrails
        'injection_guard' => '1',
        'prompt_extra_rules' => '',
        'output_redact' => '1',
        // tools
        'db_enabled' => '1',
        'db_max_rows' => '20',
        'db_deny_tables' => 'users,api_tokens,password_resets,sessions,jobs,failed_jobs,job_batches,cache,cache_locks,migrations,personal_access_tokens',
        'webfetch_enabled' => '1',
        'webfetch_ssrf_strict' => '1',
        'fs_enabled' => '1',
        // uploads
        'upload_max_mb' => '50',
        'upload_allowed_exts' => '', // empty = all supported formats
        // limits
        'chat_rate_limit' => '30',
        'api_rate_limit' => '60',
        'audit_retention_days' => '30',
    ];

    public const LABELS = [
        'injection_guard' => 'سپر ضد اینجکشن (الحاق سیاست امنیتی به همه ایجنت‌ها)',
        'prompt_extra_rules' => 'قوانین اضافه مدیر (به همه ایجنت‌ها اضافه می‌شود)',
        'output_redact' => 'حذف خودکار اسرار از پاسخ‌ها',
        'db_enabled' => 'ابزار دیتابیس فعال باشد',
        'db_max_rows' => 'سقف ردیف هر کوئری دیتابیس',
        'db_deny_tables' => 'جداول ممنوعه دیتابیس (کاما جدا)',
        'webfetch_enabled' => 'ابزار دریافت وب فعال باشد',
        'webfetch_ssrf_strict' => 'حالت سخت‌گیرانه ضد SSRF',
        'fs_enabled' => 'ابزار فایل‌سیستم (سندباکس) فعال باشد',
        'upload_max_mb' => 'حداکثر حجم آپلود (مگابایت)',
        'upload_allowed_exts' => 'پسوندهای مجاز آپلود (کاما جدا؛ خالی = همه فرمت‌های پشتیبانی‌شده)',
        'chat_rate_limit' => 'سقف پیام چت در دقیقه (هر IP)',
        'api_rate_limit' => 'سقف درخواست API در دقیقه',
        'audit_retention_days' => 'نگهداری رویدادهای امنیتی (روز)',
    ];

    public const TYPES = [
        'injection_guard' => 'bool', 'output_redact' => 'bool',
        'db_enabled' => 'bool', 'webfetch_enabled' => 'bool',
        'webfetch_ssrf_strict' => 'bool', 'fs_enabled' => 'bool',
        'prompt_extra_rules' => 'text', 'db_deny_tables' => 'text', 'upload_allowed_exts' => 'text',
    ];

    public static function allResolved(): array
    {
        return Cache::remember('security_settings_v1', 120, function () {
            $stored = static::pluck('value', 'key')->all();
            return array_merge(self::DEFAULTS, $stored);
        });
    }

    public static function get(string $key, $default = null)
    {
        $all = static::allResolved();
        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function bool(string $key): bool
    {
        return in_array(strtolower((string) static::get($key, '0')), ['1', 'true', 'on', 'yes'], true);
    }

    public static function int(string $key, int $fallback = 0): int
    {
        return max(0, (int) static::get($key, (string) $fallback));
    }

    public static function putMany(array $values): void
    {
        foreach ($values as $k => $v) {
            if (!array_key_exists($k, self::DEFAULTS)) {
                continue;
            }
            static::updateOrCreate(['key' => $k], ['value' => is_string($v) ? $v : (string) $v]);
        }
        Cache::forget('security_settings_v1');
    }

    public static function forgetCache(): void
    {
        Cache::forget('security_settings_v1');
    }
}
