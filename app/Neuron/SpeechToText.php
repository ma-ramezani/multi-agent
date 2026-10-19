<?php

namespace App\Neuron;

/**
 * Server-side speech-to-text (Persian-first) via local faster-whisper.
 * Used for voice-message attachments in chat and audio files in RAG.
 * Gracefully disabled when the transcriber is not installed.
 */
class SpeechToText
{
    public const SUPPORTED = ['mp3', 'wav', 'ogg', 'm4a', 'webm', 'flac'];

    public static function script(): string
    {
        return env('STT_SCRIPT', '/opt/stt/transcribe.py');
    }

    public static function model(): string
    {
        $m = env('STT_MODEL', '');
        if ($m !== '') {
            return $m;
        }
        // prefer the local install (no download surprises)
        if (is_file('/opt/stt/models/large-v3-turbo/model.bin')) {
            return '/opt/stt/models/large-v3-turbo';
        }
        return 'large-v3-turbo';
    }

    public static function serverUrl(): string
    {
        return env('STT_SERVER_URL', 'http://127.0.0.1:11437');
    }

    public static function available(): bool
    {
        // Prefer the persistent server (model stays loaded); fall back to CLI.
        if (self::ping()) {
            return true;
        }
        $s = self::script();
        return is_file($s) && is_executable($s);
    }

    public static function ping(): bool
    {
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 3, 'method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => '{}']]);
            $r = @file_get_contents(self::serverUrl() . '/health', false, $ctx);
            $j = @json_decode((string) $r, true);
            return is_array($j) && !empty($j['ok']);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{text: string, detail: string}|null null when unavailable/failed
     */
    public static function transcribe(string $absPath, int $timeoutSec = 300): ?array
    {
        if (!is_file($absPath)) {
            return null;
        }
        // 1) persistent server (fast: no model reload)
        $via = self::transcribeViaServer($absPath, $timeoutSec);
        if ($via !== null) {
            return $via;
        }
        // 2) one-shot CLI (slow: reloads the model every call)
        return self::transcribeViaCli($absPath, $timeoutSec);
    }

    protected static function transcribeViaServer(string $absPath, int $timeoutSec): ?array
    {
        try {
            $ctx = stream_context_create(['http' => [
                'timeout' => max(30, $timeoutSec),
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode(['path' => $absPath, 'lang' => 'fa']),
            ]]);
            $raw = @file_get_contents(self::serverUrl() . '/transcribe', false, $ctx);
            $j = @json_decode((string) $raw, true);
            if (!is_array($j) || empty($j['ok']) || trim((string) ($j['text'] ?? '')) === '') {
                return null;
            }
            $detail = 'رونویسی خودکار فارسی';
            if (!empty($j['duration'])) {
                $detail .= ' (' . $j['duration'] . ' ثانیه صوت)';
            }
            return ['text' => trim((string) $j['text']), 'detail' => $detail];
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function transcribeViaCli(string $absPath, int $timeoutSec): ?array
    {
        $s = self::script();
        if (!is_file($s) || !is_executable($s)) {
            return null;
        }
        $cmd = escapeshellarg(self::script()) . ' ' . escapeshellarg($absPath)
            . ' ' . escapeshellarg(self::model()) . ' fa 2>/dev/null';
        if (trim((string) @shell_exec('which timeout 2>/dev/null')) !== '') {
            $cmd = 'timeout ' . max(30, $timeoutSec) . ' ' . $cmd;
        }
        $out = [];
        @exec($cmd, $out, $code);
        $json = @json_decode(implode("\n", $out), true);
        if (!is_array($json) || empty($json['ok']) || trim((string) ($json['text'] ?? '')) === '') {
            return null;
        }
        $detail = 'رونویسی خودکار فارسی';
        if (!empty($json['duration'])) {
            $detail .= ' (' . $json['duration'] . ' ثانیه صوت)';
        }
        return ['text' => trim((string) $json['text']), 'detail' => $detail];
    }
}
