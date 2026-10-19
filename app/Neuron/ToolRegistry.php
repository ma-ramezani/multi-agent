<?php

namespace App\Neuron;

use App\Neuron\Tools\DatabaseReadTool;
use App\Neuron\Tools\ScopedFileTool;
use App\Neuron\Tools\WebFetchTool;

class ToolRegistry
{
    public const AVAILABLE = [
        'calculator' => ['label' => 'ماشین‌حساب (Calculator)', 'desc' => 'محاسبات ریاضی دقیق'],
        'calendar' => ['label' => 'تقویم و تاریخ (Calendar)', 'desc' => 'کار با تاریخ، ساعت و تایم‌زون'],
        'filesystem' => ['label' => 'فایل‌سیستم محدود (Sandbox)', 'desc' => 'فقط خواندن فایل‌های داخل پوشه امن workspace (بدون bash/حذف/نوشتن)'],
        'web_fetch' => ['label' => 'دریافت صفحه وب (WebFetch)', 'desc' => 'خواندن متن یک URL عمومی (محافظت SSRF فعال)'],
        'database_read' => ['label' => 'خواندن دیتابیس (DB Read)', 'desc' => 'اجرای SELECT فقط-خواندنی؛ جداول حساس مسدود'],
    ];

    public static function labels(): array
    {
        return array_map(fn ($v) => $v['label'], self::AVAILABLE);
    }

    /**
     * @param string[] $enabled
     */
    public static function build(array $enabled, ?array $toolConfig = []): array
    {
        $tools = [];
        $S = \App\Models\SecuritySetting::class;
        foreach ($enabled as $key) {
            switch ($key) {
                case 'calculator':
                    if (extension_loaded('bcmath')) {
                        $tools[] = \NeuronAI\Tools\Toolkits\Calculator\CalculatorToolkit::make();
                    }
                    break;
                case 'calendar':
                    $tools[] = \NeuronAI\Tools\Toolkits\Calendar\CalendarToolkit::make();
                    break;
                case 'filesystem':
                    if (!$S::bool('fs_enabled')) {
                        break;
                    }
                    // Sandboxed read-only workspace instead of the raw toolkit
                    // (which ships bash/write/delete tools — never expose those).
                    $tools[] = new ScopedFileTool();
                    break;
                case 'web_fetch':
                    if (!$S::bool('webfetch_enabled')) {
                        break;
                    }
                    $tools[] = new WebFetchTool();
                    break;
                case 'database_read':
                    if (!$S::bool('db_enabled')) {
                        break;
                    }
                    $tools[] = new DatabaseReadTool();
                    break;
            }
        }
        return $tools;
    }
}
