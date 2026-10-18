<?php

if (!function_exists('fa_date')) {
    /**
     * Format a date/datetime in Jalali calendar, Persian locale.
     * e.g. fa_date(now()) => "۲۷ مهر ۱۴۰۵، ۱۰:۳۰"
     */
    function fa_date($dt, bool $withTime = true): string
    {
        try {
            if (!$dt) {
                return '—';
            }
            if (is_string($dt)) {
                $dt = new DateTime($dt);
            }
            $pattern = $withTime ? 'd MMMM y، HH:mm' : 'd MMMM y';
            $fmt = new IntlDateFormatter(
                'fa_IR@calendar=persian',
                IntlDateFormatter::FULL,
                IntlDateFormatter::FULL,
                $dt->getTimezone()->getName(),
                IntlDateFormatter::TRADITIONAL,
                $pattern
            );
            return $fmt->format($dt) ?: (string) $dt;
        } catch (Throwable) {
            return (string) $dt;
        }
    }
}

if (!function_exists('fa_num')) {
    /** Convert latin digits to Persian digits. */
    function fa_num($v): string
    {
        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            (string) $v
        );
    }
}

if (!function_exists('fa_bytes')) {
    /** Human-readable file size with Persian digits. */
    function fa_bytes(?int $bytes): string
    {
        $bytes = (int) ($bytes ?? 0);
        if ($bytes < 1024) {
            return fa_num($bytes) . ' بایت';
        }
        $units = ['کیلوبایت', 'مگابایت', 'گیگابایت'];
        $i = -1;
        $v = (float) $bytes;
        do {
            $v /= 1024;
            $i++;
        } while ($v >= 1024 && $i < count($units) - 1);
        return fa_num(number_format($v, $v >= 10 ? 0 : 1)) . ' ' . $units[$i];
    }
}

if (!function_exists('provider_badge_class')) {
    /** Color class per provider for badges. */
    function provider_badge_class(string $provider): string
    {
        return match ($provider) {
            'openai', 'openai_responses' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'opencode_go' => 'bg-violet-50 text-violet-700 ring-violet-200',
            'anthropic' => 'bg-orange-50 text-orange-700 ring-orange-200',
            'gemini' => 'bg-blue-50 text-blue-700 ring-blue-200',
            'ollama' => 'bg-slate-100 text-slate-700 ring-slate-300',
            'mistral' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'deepseek' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
            default => 'bg-gray-50 text-gray-700 ring-gray-200',
        };
    }
}
