<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\ChatSession;
use App\Models\McpServer;
use App\Models\RagEnvironment;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard', [
            'agents' => Agent::count(),
            'envs' => RagEnvironment::count(),
            'mcps' => McpServer::count(),
            'sessions' => ChatSession::count(),
            'recentSessions' => ChatSession::with('agent')->orderBy('id', 'desc')->limit(8)->get(),
            'agentsList' => Agent::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
