<?php

namespace App\Neuron\Tools;

use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolOutput;
use NeuronAI\Tools\ToolProperty;

/**
 * Read-only workspace file access, strictly jailed to storage/app/agent-files.
 * Replaces the raw FileSystemToolkit (which includes bash/write/delete) for safety:
 * the agent can never read .env, source code, or anything outside its sandbox.
 */
class ScopedFileTool extends Tool
{
    protected string $name = 'workspace_read';
    protected ?string $description = 'Read a text file from the agent workspace, or list workspace files when path is empty. You can ONLY access files inside the dedicated workspace directory — any other path is refused.';

    public static function root(): string
    {
        $dir = storage_path('app/agent-files');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return (string) realpath($dir);
    }

    protected function properties(): array
    {
        return [
            new ToolProperty('path', PropertyType::STRING, 'Relative file path inside the workspace (e.g. "notes.txt"). Empty = list files.', false),
        ];
    }

    public function __invoke(string $path = ''): ToolOutput
    {
        $root = self::root();
        $path = trim($path);

        if ($path === '') {
            $files = [];
            foreach (glob($root . '/*') ?: [] as $f) {
                if (is_file($f)) {
                    $files[] = basename($f) . ' (' . round(filesize($f) / 1024, 1) . ' KB)';
                }
            }
            return ToolOutput::text($files === [] ? 'Workspace is empty.' : "Workspace files:\n- " . implode("\n- ", $files));
        }

        // normalize + jail check (blocks ../, absolute paths, symlinks escaping root)
        $candidate = $root . '/' . ltrim(str_replace('\\', '/', $path), '/');
        $real = realpath($candidate);
        if ($real === false || !is_file($real) || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            \App\Models\SecurityEvent::log('fs_blocked', 'تلاش دسترسی خارج از سندباکس فایل', 'blocked', ['path' => mb_substr($path, 0, 120)]);
            return ToolOutput::text('Access denied: path is outside the workspace or does not exist.');
        }
        if (filesize($real) > 200000) {
            return ToolOutput::text('File too large to read (max 200KB).');
        }
        $raw = @file_get_contents($real);
        if ($raw === false || str_contains($raw, "\0") || !mb_check_encoding($raw, 'UTF-8')) {
            return ToolOutput::text('File is not readable as text.');
        }
        return ToolOutput::text(mb_substr($raw, 0, 50000));
    }
}
