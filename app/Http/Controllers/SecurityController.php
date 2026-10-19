<?php

namespace App\Http\Controllers;

use App\Models\SecurityEvent;
use App\Models\SecuritySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecurityController extends Controller
{
    public function index()
    {
        $settings = SecuritySetting::allResolved();
        $groups = [
            'سپرها' => ['injection_guard', 'prompt_extra_rules', 'output_redact'],
            'ابزارها' => ['db_enabled', 'db_max_rows', 'db_deny_tables', 'webfetch_enabled', 'webfetch_ssrf_strict', 'fs_enabled'],
            'آپلود فایل' => ['upload_max_mb', 'upload_allowed_exts'],
            'محدودیت نرخ' => ['chat_rate_limit', 'api_rate_limit'],
            'ممیزی' => ['audit_retention_days'],
        ];
        $events = SecurityEvent::orderBy('id', 'desc')->limit(100)->get();
        $counts = SecurityEvent::counts();
        $health = [
            'queue' => DB::table('jobs')->count(),
            'failed' => DB::table('failed_jobs')->count(),
            'ollama' => $this->ollamaOk(),
        ];
        return view('security.index', compact('settings', 'groups', 'events', 'counts', 'health'));
    }

    public function update(Request $request)
    {
        $values = [];
        foreach (array_keys(SecuritySetting::DEFAULTS) as $k) {
            $type = SecuritySetting::TYPES[$k] ?? 'string';
            if ($type === 'bool') {
                $values[$k] = $request->boolean($k) ? '1' : '0';
            } elseif ($request->has($k)) {
                $values[$k] = (string) $request->input($k);
            }
        }
        SecuritySetting::putMany($values);
        SecurityEvent::log('settings_changed', 'تنظیمات امنیتی به‌روز شد', 'info');
        return back()->with('ok', 'تنظیمات امنیتی ذخیره شد');
    }

    public function clearEvents()
    {
        SecurityEvent::truncate();
        return back()->with('ok', 'رویدادها پاک شدند');
    }

    protected function ollamaOk(): bool
    {
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 3]]);
            $r = @file_get_contents('http://127.0.0.1:11434/api/tags', false, $ctx);
            return $r !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
