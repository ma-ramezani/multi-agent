<?php

namespace App\Neuron;

use App\Models\SecurityEvent;

/**
 * Heuristic prompt-injection detector (Persian + English).
 * Non-blocking by design: suspicious inputs are AUDITED, the model itself
 * is instructed (via the security policy) to ignore embedded instructions.
 * Blocking on heuristics would break legitimate questions, so we log instead.
 */
class PromptGuard
{
    protected const PATTERNS = [
        // direct overrides
        '/ignore\s+(all\s+)?(previous|prior|above)\s+instructions?/i',
        '/disregard\s+(all\s+)?(previous|prior|above)\s+instructions?/i',
        '/forget\s+(all\s+)?(previous|prior)\s+instructions?/i',
        '/reveal\s+(your\s+)?(system\s+)?prompt/i',
        '/show\s+me\s+your\s+(system\s+)?prompt/i',
        '/developer\s+mode/i',
        '/\bDAN\b.*mode/i',
        '/jailbreak/i',
        '/you\s+are\s+now\s+(a|an)\s+\w+\s*(hacker|assistant|agent)/i',
        // persian
        '/نادیده\s+بگیر/u',
        '/دستور(ات)?\s+(قبلی|بالا|سیستمی)/u',
        '/پرامپت\s+(سیستم|خودت|را).*(نشان|نمایش|بده|بگو)/u',
        '/(نشان|نمایش)\s+.*پرامپت/u',
        '/نقش\s+\S+\s+را\s+بازی\s+کن/u',
        '/حالت\s+توسعه‌دهنده/u',
        '/محدودیت.*(بردار|حذف|دور بزن)/u',
    ];

    /** @return string[] matched pattern descriptions (empty = clean) */
    public static function scan(string $text): array
    {
        $hits = [];
        foreach (self::PATTERNS as $i => $rx) {
            if (@preg_match($rx, $text)) {
                $hits[] = 'rule#' . ($i + 1);
            }
        }
        return $hits;
    }

    /** Scan + audit-log when suspicious. Returns true if suspicious. */
    public static function check(string $text, string $source = 'chat'): bool
    {
        if (mb_strlen(trim($text)) < 8) {
            return false;
        }
        $hits = self::scan($text);
        if ($hits !== []) {
            SecurityEvent::log(
                'injection_suspected',
                'ورودی مشکوک به اینجکشن شناسایی شد',
                'warn',
                ['source' => $source, 'rules' => $hits, 'preview' => mb_substr($text, 0, 120)]
            );
            return true;
        }
        return false;
    }
}
