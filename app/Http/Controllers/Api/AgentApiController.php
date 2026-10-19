<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\ApiToken;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Neuron\AgentFactory;
use Illuminate\Http\Request;
use NeuronAI\Chat\Messages\UserMessage;

class AgentApiController extends Controller
{
    protected function auth(Request $request): ?ApiToken
    {
        $header = $request->header('Authorization', '');
        $plain = null;
        if (str_starts_with($header, 'Bearer ')) {
            $plain = substr($header, 7);
        } else {
            $plain = $request->input('api_token') ?: $request->query('api_token');
        }
        if (!$plain) {
            return null;
        }
        $token = ApiToken::where('token_hash', hash('sha256', $plain))->where('is_active', true)->first();
        if ($token) {
            $token->update(['last_used_at' => now()]);
        }
        return $token;
    }

    protected function denyIfNoAccess(ApiToken $token, Agent $agent)
    {
        if (!$token->canAccessAgent($agent->id)) {
            abort(response()->json(['error' => 'این توکن به این ایجنت دسترسی ندارد'], 403));
        }
    }

    public function agents(Request $request)
    {
        $token = $this->auth($request);
        if (!$token) {
            return response()->json(['error' => 'توکن نامعتبر است (Authorization: Bearer ...)'], 401);
        }
        $q = Agent::where('is_active', true);
        if (!empty($token->agent_ids)) {
            $q->whereIn('id', $token->agent_ids);
        }
        return response()->json(['data' => $q->get()->map(fn ($a) => [
            'id' => $a->id, 'name' => $a->name, 'slug' => $a->slug,
            'description' => $a->description, 'provider' => $a->provider, 'model' => $a->model,
        ])]);
    }

    public function chat(Request $request, string $slug)
    {
        $token = $this->auth($request);
        if (!$token) {
            return response()->json(['error' => 'توکن نامعتبر است'], 401);
        }
        $agent = Agent::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $this->denyIfNoAccess($token, $agent);

        $data = $request->validate([
            'message' => 'required|string|max:20000',
            'thread_id' => 'nullable|string|max:255',
            'stream' => 'nullable|boolean',
        ]);

        $session = null;
        if (!empty($data['thread_id'])) {
            $session = ChatSession::where('thread_id', $data['thread_id'])->where('agent_id', $agent->id)->first();
        }
        if (!$session) {
            $session = ChatSession::create([
                'agent_id' => $agent->id,
                'title' => mb_substr($data['message'], 0, 60),
                'thread_id' => $data['thread_id'] ?? ('api_' . bin2hex(random_bytes(12))),
            ]);
        }

        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'user', 'content' => $data['message']]);

        if ($request->boolean('stream')) {
            return $this->streamResponse($session, $data['message']);
        }

        try {
            $neuron = AgentFactory::make($agent, $session->thread_id);
            $state = $neuron->chat(new UserMessage($data['message']));
            $answer = $state->getMessage()?->getContent() ?? '(پاسخی از مدل دریافت نشد)';
            $answer = \App\Neuron\SafetyFilter::redact($answer);
            ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'assistant', 'content' => $answer]);
            return response()->json(['thread_id' => $session->thread_id, 'answer' => $answer]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function streamResponse(ChatSession $session, string $message)
    {
        $agent = $session->agent;
        return response()->stream(function () use ($session, $agent, $message) {
            $answer = '';
            try {
                $neuron = AgentFactory::make($agent, $session->thread_id);
                // neuron-ai 4.x: stream() is a plain Generator; accumulate text chunks.
                foreach ($neuron->stream(new UserMessage($message)) as $chunk) {
                    $piece = is_string($chunk) ? $chunk : (is_object($chunk) && isset($chunk->content) && is_string($chunk->content) ? $chunk->content : '');
                    if ($piece !== '') {
                        $answer .= $piece;
                        echo 'data: ' . json_encode(['text' => $piece], JSON_UNESCAPED_UNICODE) . "\n\n";
                        if (ob_get_level() > 0) { ob_flush(); }
                        flush();
                    }
                }
            } catch (\Throwable $e) {
                echo 'data: ' . json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE) . "\n\n";
                if (ob_get_level() > 0) { ob_flush(); }
                flush();
            }
            ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'assistant', 'content' => \App\Neuron\SafetyFilter::redact($answer) ?: '(بدون پاسخ)']);
            echo 'data: ' . json_encode(['done' => true, 'thread_id' => $session->thread_id], JSON_UNESCAPED_UNICODE) . "\n\n";
            if (ob_get_level() > 0) { ob_flush(); }
            flush();
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache']);
    }

    public function history(Request $request, string $slug)
    {
        $token = $this->auth($request);
        if (!$token) {
            return response()->json(['error' => 'توکن نامعتبر است'], 401);
        }
        $agent = Agent::where('slug', $slug)->firstOrFail();
        $this->denyIfNoAccess($token, $agent);
        $threadId = $request->query('thread_id');
        $q = ChatSession::where('agent_id', $agent->id);
        if ($threadId) {
            $q->where('thread_id', $threadId);
        }
        $sessions = $q->with('messages')->orderBy('id', 'desc')->limit(20)->get();
        return response()->json(['data' => $sessions]);
    }
}
