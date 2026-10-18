<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\RagEnvironment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentController extends Controller
{
    public function index()
    {
        $agents = Agent::withCount('chatSessions')->orderBy('id', 'desc')->get();
        return view('agents.index', compact('agents'));
    }

    public function create()
    {
        $envs = RagEnvironment::orderBy('name')->get();
        return view('agents.form', ['agent' => new Agent(['provider' => 'openai', 'model' => 'gpt-4o-mini', 'temperature' => 0.7, 'is_active' => true]), 'envs' => $envs]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']) . '-' . Str::random(4);
        Agent::create($data);
        return redirect()->route('agents.index')->with('ok', 'ایجنت ساخته شد');
    }

    public function edit(Agent $agent)
    {
        $envs = RagEnvironment::orderBy('name')->get();
        return view('agents.form', compact('agent', 'envs'));
    }

    public function update(Request $request, Agent $agent)
    {
        $agent->update($this->validated($request, $agent->id));
        return redirect()->route('agents.index')->with('ok', 'ایجنت به‌روز شد');
    }

    public function destroy(Agent $agent)
    {
        $agent->delete();
        return redirect()->route('agents.index')->with('ok', 'ایجنت حذف شد');
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'provider' => 'required|string',
            'model' => 'required|string|max:255',
            'api_key' => 'nullable|string',
            'base_url' => 'nullable|string|max:500',
            'system_prompt' => 'nullable|string',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1|max:200000',
            'context_window' => 'nullable|integer|min:1000|max:1000000',
            'max_tool_runs' => 'nullable|integer|min:1|max:50',
            'rag_top_k' => 'nullable|integer|min:1|max:20',
            'tools' => 'nullable|array',
            'tools.*' => 'string',
            'rag_environment_ids' => 'nullable|array',
            'rag_environment_ids.*' => 'integer|exists:rag_environments,id',
            'is_active' => 'nullable|boolean',
        ]);

        if (empty($v['slug']) && $ignoreId) {
            unset($v['slug']);
        }
        // keep existing api_key if blank on update
        if ($ignoreId && empty($v['api_key'])) {
            unset($v['api_key']);
        }
        $v['tools'] = array_values($v['tools'] ?? []);
        $v['rag_environment_ids'] = array_values($v['rag_environment_ids'] ?? []);
        $v['is_active'] = $request->boolean('is_active', true);
        $v['temperature'] = (float) ($v['temperature'] ?? 0.7);
        return $v;
    }
}
