<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\ApiToken;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function index()
    {
        $tokens = ApiToken::orderBy('id', 'desc')->get();
        $agents = Agent::orderBy('name')->get();
        return view('tokens.index', compact('tokens', 'agents'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'agent_ids' => 'nullable|array',
            'agent_ids.*' => 'integer|exists:agents,id',
        ]);
        [$token, $plain] = ApiToken::generate(
            $request->input('name'),
            $request->input('agent_ids') ?: null
        );
        return redirect()->route('tokens.index')->with('plain_token', $plain);
    }

    public function destroy(ApiToken $token)
    {
        $token->delete();
        return back()->with('ok', 'توکن حذف شد');
    }
}
