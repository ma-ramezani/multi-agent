<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Neuron\AgentFactory;
use Illuminate\Http\Request;
use NeuronAI\Chat\Messages\UserMessage;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $agents = Agent::where('is_active', true)->orderBy('name')->get();
        $selectedAgent = null;
        if ($request->filled('agent_id')) {
            $selectedAgent = Agent::find($request->input('agent_id'));
        }
        $selectedAgent ??= $agents->first();

        $sessions = $selectedAgent
            ? ChatSession::where('agent_id', $selectedAgent->id)->orderBy('id', 'desc')->limit(50)->get()
            : collect();

        return view('chats.index', compact('agents', 'selectedAgent', 'sessions'));
    }

    public function start(Request $request, Agent $agent)
    {
        $session = ChatSession::create([
            'agent_id' => $agent->id,
            'title' => 'گفتگوی جدید - ' . now()->format('H:i'),
        ]);
        return redirect()->route('chats.show', $session);
    }

    public function show(ChatSession $session)
    {
        $session->load(['agent', 'messages']);
        $agents = Agent::where('is_active', true)->orderBy('name')->get();
        return view('chats.show', compact('session', 'agents'));
    }

    public function destroy(ChatSession $session)
    {
        $agentId = $session->agent_id;
        $session->messages()->delete();
        $session->delete();
        // clear neuron file history
        @unlink(storage_path('app/neuron-chats/' . $session->thread_id . '.json'));
        return redirect()->route('chats.index', ['agent_id' => $agentId])->with('ok', 'گفتگو حذف شد');
    }

    /**
     * Non-streaming send (fallback + API-style).
     */
    public function send(Request $request, ChatSession $session)
    {
        $request->validate(['message' => 'required|string|max:20000']);
        $text = $request->input('message');

        // handle uploaded files attached to this turn (stored in session meta temp)
        $context = $this->buildAttachmentContext($session);

        $fullText = $text . ($context ? "\n\n[فایل‌های پیوست]:\n" . $context : '');

        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'user', 'content' => $text, 'attachments' => $session->meta['pending_attachments'] ?? []]);
        $session->update(['meta' => array_merge($session->meta ?? [], ['pending_attachments' => []])]);

        if ($session->messages()->where('role', 'user')->count() <= 1) {
            $session->update(['title' => mb_substr($text, 0, 60)]);
        }

        try {
            $neuron = AgentFactory::make($session->agent, $session->thread_id);
            $state = $neuron->chat(new UserMessage($fullText));
            $answer = $state->getMessage()?->getContent() ?? '(پاسخی از مدل دریافت نشد)';

            $sources = $this->extractRagSources($state);
            ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'assistant', 'content' => $answer, 'meta' => ['sources' => $sources]]);
        } catch (\Throwable $e) {
            ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'assistant', 'content' => 'خطا: ' . $e->getMessage()]);
        }

        return redirect()->route('chats.show', $session);
    }

    /**
     * SSE streaming endpoint. GET /chats/{session}/stream?message=...
     */
    public function stream(Request $request, ChatSession $session)
    {
        $text = $request->query('message', '');
        if (trim($text) === '') {
            return response('پیام خالی است', 422);
        }

        $context = $this->buildAttachmentContext($session);
        $fullText = $text . ($context ? "\n\n[فایل‌های پیوست]:\n" . $context : '');
        $attachments = $session->meta['pending_attachments'] ?? [];

        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'user', 'content' => $text, 'attachments' => $attachments]);
        $session->update(['meta' => array_merge($session->meta ?? [], ['pending_attachments' => []])]);
        if ($session->messages()->where('role', 'user')->count() <= 1) {
            $session->update(['title' => mb_substr($text, 0, 60)]);
        }

        return response()->stream(function () use ($session, $fullText) {
            $answer = '';
                try {
                    $neuron = AgentFactory::make($session->agent, $session->thread_id);
                    // NOTE: in neuron-ai 4.x stream() returns a plain Generator of
                    // chunks (TextChunk->content, ...); the full answer is the
                    // concatenation of the text chunks (tool calls resolve inside
                    // the same generator loop).
                    foreach ($neuron->stream(new UserMessage($fullText)) as $chunk) {
                        $piece = self::chunkText($chunk);
                        if ($piece !== '') {
                            $answer .= $piece;
                            echo 'data: ' . json_encode(['text' => $piece], JSON_UNESCAPED_UNICODE) . "\n\n";
                            if (ob_get_level() > 0) { ob_flush(); }
                            flush();
                        }
                    }
                } catch (\Throwable $e) {
                $err = 'خطا: ' . $e->getMessage();
                $answer .= $err;
                echo 'data: ' . json_encode(['text' => $err], JSON_UNESCAPED_UNICODE) . "\n\n";
                if (ob_get_level() > 0) { ob_flush(); }
                flush();
            }

            ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'assistant', 'content' => $answer ?: '(پاسخی دریافت نشد)']);
            echo 'data: ' . json_encode(['done' => true], JSON_UNESCAPED_UNICODE) . "\n\n";
            if (ob_get_level() > 0) { ob_flush(); }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function upload(Request $request, ChatSession $session)
    {
        $request->validate([
            'files' => 'nullable|array',
            'files.*' => 'file|max:51200',
            'file' => 'nullable|file|max:51200',
        ]);
        $files = [];
        if ($request->hasFile('files')) {
            $files = $request->file('files');
        } elseif ($request->hasFile('file')) {
            $files = [$request->file('file')];
        }
        if (empty($files)) {
            return back()->withErrors(['file' => 'فایلی انتخاب نشد']);
        }
        $meta = $session->meta ?? [];
        $pending = $meta['pending_attachments'] ?? [];
        $added = 0;
        $rejected = [];
        foreach ($files as $file) {
            $name = $file->getClientOriginalName();
            if (!\App\Neuron\DocumentReader::isSupported($name)) {
                $rejected[] = $name;
                continue;
            }
            $path = $file->store('chat-uploads/' . $session->id, 'local');
            // pre-read: extraction status shown to the user right away
            // NOTE: local disk root is storage/app/private (Laravel 12), so always
            // resolve via the disk, never storage_path('app/...').
            $r = \App\Neuron\DocumentReader::read(
                \Storage::disk('local')->path($path),
                $file->getMimeType(),
                $name
            );
            $pending[] = [
                'name' => $name,
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'readable' => trim($r['text']) !== '',
                'chars' => mb_strlen(trim($r['text'])),
                'detail' => $r['kind'] === 'text' || $r['kind'] === 'table' || $r['kind'] === 'slides' || $r['kind'] === 'ocr'
                    ? ($r['detail'] ?: 'متن استخراج شد')
                    : ($r['detail'] ?: 'قابل خواندن نیست'),
            ];
            $added++;
        }
        $meta['pending_attachments'] = $pending;
        $session->update(['meta' => $meta]);

        $msg = $added > 0 ? fa_num($added) . ' فایل پیوست شد' : 'فایلی پیوست نشد';
        if ($rejected) {
            return back()->withErrors(['file' => 'فرمت پشتیبانی نمی‌شود: ' . implode('، ', $rejected)]);
        }
        return back()->with('ok', $msg . ' — حالا پیامتان را بفرستید');
    }

    protected function buildAttachmentContext(ChatSession $session): string
    {
        $pending = $session->meta['pending_attachments'] ?? [];
        if (empty($pending)) {
            return '';
        }
        $parts = [];
        $total = 0;
        $disk = \Storage::disk('local');
        $diskRoot = (string) realpath($disk->path(''));
        foreach ($pending as $att) {
            if ($total > 30000) {
                break;
            }
            // resolve via the disk (root = storage/app/private on Laravel 12)
            $abs = $disk->path(ltrim($att['path'] ?? '', '/'));
            $name = $att['name'] ?? 'فایل';
            $real = is_file($abs) ? realpath($abs) : false;
            if ($real === false || !str_starts_with($real, $diskRoot)) {
                $parts[] = "--- فایل: {$name} ---\n[فایل یافت نشد]";
                continue;
            }
            $r = \App\Neuron\DocumentReader::read($abs, $att['mime'] ?? null, $name);
            $text = mb_substr(trim($r['text']), 0, 12000);
            if ($text === '') {
                $parts[] = "--- فایل: {$name} ---\n[متن قابل استخراج نیست: {$r['detail']}]";
            } else {
                $total += mb_strlen($text);
                $parts[] = "--- فایل: {$name} ({$r['detail']}) ---\n" . $text;
            }
        }
        return implode("\n\n", $parts);
    }

    protected static function chunkText(mixed $chunk): string
    {
        if (is_string($chunk)) {
            return $chunk;
        }
        if (is_object($chunk) && isset($chunk->content) && is_string($chunk->content)) {
            // TextChunk (and compatible chunks) expose public readonly $content
            return $chunk->content;
        }
        return '';
    }

    protected function extractRagSources($state): array
    {
        try {
            $sources = [];
            foreach ($state->getSteps() ?? [] as $msg) {
                if (method_exists($msg, 'getContent')) {
                    continue;
                }
            }
            return $sources;
        } catch (\Throwable) {
            return [];
        }
    }
}
