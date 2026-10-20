<?php

namespace App\Http\Controllers;

use App\Models\McpServer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class McpController extends Controller
{
    public function index(Request $request)
    {
        $servers = McpServer::orderBy('id', 'desc')->get();
        $prereq = null;
        if ($request->query('check') === '1') {
            $prereq = McpServer::healthCheck('', null);
        }
        return view('mcps.index', compact('servers', 'prereq'));
    }

    public function create()
    {
        return view('mcps.form', ['server' => new McpServer(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']) . '-' . Str::random(4);
        if (!empty($data['headers_raw'])) {
            $data['headers'] = $this->parseHeaders($data['headers_raw']);
        }
        unset($data['headers_raw']);
        McpServer::create($data);
        return redirect()->route('mcps.index')->with('ok', 'سرور MCP ساخته شد');
    }

    public function edit(McpServer $mcp)
    {
        // For route model binding with {mcp} vs {mcp_server}, handle both
        return view('mcps.form', ['server' => $mcp]);
    }

    // Support route param {mcp_server}
    public function editById(McpServer $mcp_server)
    {
        return $this->edit($mcp_server);
    }

    public function update(Request $request, McpServer $mcp)
    {
        $data = $this->validated($request, $mcp->id);
        if (!empty($data['headers_raw'])) {
            $data['headers'] = $this->parseHeaders($data['headers_raw']);
        } elseif (array_key_exists('headers_raw', $data)) {
            $data['headers'] = null;
        }
        unset($data['headers_raw']);
        if (empty($data['slug'])) {
            unset($data['slug']);
        }
        $mcp->update($data);
        return redirect()->route('mcps.index')->with('ok', 'سرور MCP به‌روز شد');
    }

    public function updateById(Request $request, McpServer $mcp_server)
    {
        return $this->update($request, $mcp_server);
    }

    public function destroy(McpServer $mcp)
    {
        $mcp->delete();
        return redirect()->route('mcps.index')->with('ok', 'سرور MCP حذف شد');
    }

    public function destroyById(McpServer $mcp_server)
    {
        return $this->destroy($mcp_server);
    }

    public function test(Request $request, McpServer $mcp)
    {
        $headers = $mcp->headers ?? [];
        $result = McpServer::healthCheck($mcp->url, $headers);
        // Also try to list tools via McpConnector if reachable
        $tools = null;
        $toolsError = null;
        if ($result['target_ok']) {
            try {
                $connector = \NeuronAI\MCP\McpConnector::make(['url' => $mcp->url] + ($headers ? ['headers' => $headers] : []));
                $tools = array_map(fn($t) => ['name' => $t->getName(), 'description' => $t->getDescription()], $connector->tools());
            } catch (\Throwable $e) {
                $toolsError = mb_substr($e->getMessage(), 0, 400);
            }
        }
        return view('mcps.test', compact('mcp', 'result', 'tools', 'toolsError'));
    }

    public function checkPrereq(Request $request)
    {
        $url = $request->input('url', '');
        $headersRaw = $request->input('headers_raw', '');
        $headers = $headersRaw ? $this->parseHeaders($headersRaw) : null;
        $result = McpServer::healthCheck($url, $headers);

        if ($request->wantsJson()) {
            return response()->json($result);
        }
        return back()->with('prereq', $result);
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:mcp_servers,slug' . ($ignoreId ? ",{$ignoreId}" : ''),
            'description' => 'nullable|string|max:1000',
            'url' => 'required|url|max:500',
            'headers_raw' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }

    protected function parseHeaders(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        // Supports JSON or "Key: Value" lines
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && array_keys($decoded) !== range(0, count($decoded) - 1)) {
            return $decoded;
        }
        $out = [];
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $out[trim($k)] = trim($v);
            }
        }
        return $out ?: null;
    }
}
