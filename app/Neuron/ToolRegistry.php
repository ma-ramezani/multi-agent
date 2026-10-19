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
                    // Sandboxed read-only workspace instead of the raw toolkit
                    // (which ships bash/write/delete tools — never expose those).
                    $tools[] = new ScopedFileTool();
                    break;
                case 'web_fetch':
                    $tools[] = new WebFetchTool();
                    break;
                case 'database_read':
                    $tools[] = new DatabaseReadTool();
                    break;
            }
        }
        return $tools;
    }
}
