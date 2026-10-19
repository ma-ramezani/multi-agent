<?php

namespace App\Neuron;

/**
 * Defense in depth for model OUTPUT:
 * redacts anything that looks like a credential before it is stored/shown.
 * Runs in ChatController (web) and AgentApiController (API).
 */
class SafetyFilter
{
    public static function redact(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // 1) known secret VALUES from env (exact match, case-sensitive)
        foreach (['OPENCODE_GO_API_KEY', 'OPENAI_API_KEY', 'ANTHROPIC_API_KEY', 'GEMINI_API_KEY', 'APP_KEY'] as $k) {
            $v = env($k);
            if (is_string($v) && strlen($v) >= 8) {
                $text = str_replace($v, '[REDACTED]', $text);
            }
        }

        // 2) common token shapes
        $patterns = [
            '/sk-[A-Za-z0-9\-_]{16,}/' => 'sk-[REDACTED]',
            '/ak_[a-f0-9]{24,}/i' => 'ak_[REDACTED]',
            '/ghp_[A-Za-z0-9]{20,}/' => 'ghp_[REDACTED]',
            '/xox[bpas]-[A-Za-z0-9\-]+/' => '[REDACTED-SLACK-TOKEN]',
            '/AIza[0-9A-Za-z\-_]{20,}/' => '[REDACTED-GOOGLE-KEY]',
            '/Bearer\s+[A-Za-z0-9\-_\.~\+=\/]{20,}/' => 'Bearer [REDACTED]',
        ];
        foreach ($patterns as $rx => $rep) {
            $text = (string) preg_replace($rx, $rep, $text);
        }

        return $text;
    }
}
