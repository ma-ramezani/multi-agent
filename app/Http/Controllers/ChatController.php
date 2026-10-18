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
        $request->validate(['file' => 'required|file|max:20480']);
        $file = $request->file('file');
        $path = $file->store('chat-uploads/' . $session->id, 'local');
        $meta = $session->meta ?? [];
        $pending = $meta['pending_attachments'] ?? [];
        $pending[] = [
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];
        $meta['pending_attachments'] = $pending;
        $session->update(['meta' => $meta]);
        return back()->with('ok', 'فایل پیوست شد: ' . $file->getClientOriginalName());
    }

    protected function buildAttachmentContext(ChatSession $session): string
    {
        $pending = $session->meta['pending_attachments'] ?? [];
        if (empty($pending)) {
            return '';
        }
        $parts = [];
        foreach ($pending as $att) {
            $abs = storage_path('app/' . ($att['path'] ?? ''));
            $text = '';
            if ($abs && is_file($abs)) {
                $ext = strtolower(pathinfo($att['name'] ?? '', PATHINFO_EXTENSION));
                if (in_array($ext, ['txt', 'md', 'csv', 'json', 'log'])) {
                    $text = mb_substr((string) @file_get_contents($abs), 0, 15000);
                } else {
                    $text = '[فایل باینری: ' . ($att['name'] ?? '') . ' - قابل استخراج متنی نیست، فقط نام فایل را در نظر بگیر]';
                }
            }
            $parts[] = '--- فایل: ' . ($att['name'] ?? '') . " ---\n" . $text;
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
