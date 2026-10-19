<?php

namespace App\Neuron\Tools;

use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolOutput;
use NeuronAI\Tools\ToolProperty;

class DatabaseReadTool extends Tool
{
    protected string $name = 'database_read';
    protected ?string $description = 'Run a read-only SELECT/SHOW/DESCRIBE query on the application database and return rows as JSON. Only SELECT allowed. Sensitive tables (users, tokens, jobs, sessions) are blocked.';

    /** Tables the agent must never touch (credentials, tokens, infra). */
    protected const DENIED_TABLES = [
        'users', 'api_tokens', 'password_resets', 'password_reset_tokens',
        'sessions', 'jobs', 'failed_jobs', 'job_batches', 'cache', 'cache_locks',
        'migrations', 'personal_access_tokens', 'security_settings', 'security_events',
    ];

    /** Column/keyword fragments that indicate credential exfiltration. */
    protected const DENIED_KEYWORDS = [
        'password', 'remember_token', 'token_hash', 'api_key', 'apikey', 'secret',
    ];

    protected function properties(): array
    {
        return [
            new ToolProperty('sql', PropertyType::STRING, 'SELECT query to run (read-only)', true),
            new ToolProperty('limit', PropertyType::NUMBER, 'Max rows (default 20, max 100)', false),
        ];
    }

    public function __invoke(string $sql, int $limit = 20): ToolOutput
    {
        $trimmed = ltrim(strtoupper(trim($sql)));
        foreach (['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'] as $ok) {
            if (str_starts_with($trimmed, $ok)) {
                goto allowed;
            }
        }
        return ToolOutput::text('Only read-only queries (SELECT/SHOW/DESCRIBE/EXPLAIN) are allowed.');
        allowed:
        // stacked queries / multi-statements are forbidden
        if (preg_match('/;\s*\S/', trim($sql))) {
            \App\Models\SecurityEvent::log('db_blocked', 'تلاش برای اجرای چند دستور همزمان', 'blocked');
            return ToolOutput::text('Multiple statements are not allowed.');
        }
        // deny sensitive tables: built-in list + admin-configured list
        $extraDenied = array_filter(array_map('trim', explode(',', strtolower((string) \App\Models\SecuritySetting::get('db_deny_tables', '')))));
        $denied = array_unique(array_merge(self::DENIED_TABLES, $extraDenied));
        $lower = strtolower($sql);
        foreach ($denied as $t) {
            if ($t !== '' && preg_match('/\b' . preg_quote($t, '/') . '\b/', $lower)) {
                \App\Models\SecurityEvent::log('db_blocked', "دسترسی به جدول مسدودشده: {$t}", 'blocked');
                return ToolOutput::text("Access to the '{$t}' table is blocked by the security policy.");
            }
        }
        // deny credential-ish columns
        foreach (self::DENIED_KEYWORDS as $k) {
            if (preg_match('/\b' . preg_quote($k, '/') . '\b/', $lower)) {
                \App\Models\SecurityEvent::log('db_blocked', "ستون حساس مسدودشده: {$k}", 'blocked');
                return ToolOutput::text("Columns matching '{$k}' are blocked by the security policy.");
            }
        }
        $maxRows = \App\Models\SecuritySetting::int('db_max_rows', 20) ?: 20;
        $limit = max(1, min($maxRows, 100, (int) $limit));
        try {
            $rows = \DB::select($sql);
            $rows = array_slice($rows, 0, $limit);
            $arr = array_map(fn ($r) => (array) $r, $rows);
            return ToolOutput::text(json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            return ToolOutput::text('SQL error: ' . $e->getMessage());
        }
    }
}
