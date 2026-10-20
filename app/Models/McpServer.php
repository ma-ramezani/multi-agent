<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpServer extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'url', 'headers', 'is_active'];
    protected $casts = ['headers' => 'array', 'is_active' => 'boolean'];

    public function agents()
    {
        return $this->belongsToMany(Agent::class, 'agent_mcp_server');
    }

    public static function healthCheck(string $url, ?array $headers = null): array
    {
        $checks = [];

        // Check PHP prerequisites
        $checks['php_curl'] = extension_loaded('curl');
        $checks['php_openssl'] = extension_loaded('openssl');
        $checks['allow_url_fopen'] = (bool) ini_get('allow_url_fopen');
        $checks['has_curl_or_fopen'] = $checks['php_curl'] || $checks['allow_url_fopen'];

        // Check outgoing HTTP capability (try to fetch a reliable endpoint)
        $canConnect = false;
        $latency = null;
        $error = null;

        $testUrl = 'https://httpbin.org/get';
        $start = microtime(true);
        $ctx = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 8, 'header' => "User-Agent: NeuronAgent/1.0\r\n"],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $res = @file_get_contents($testUrl, false, $ctx);
        if ($res !== false) {
            $canConnect = true;
            $latency = round((microtime(true) - $start) * 1000);
        } else {
            $error = 'اتصال به اینترنت تست با شکست مواجه شد — هاست ممکن است خروجی HTTP را مسدود کرده باشد';
        }
        $checks['outgoing_http'] = $canConnect;
        $checks['latency_ms'] = $latency;
        $checks['outgoing_error'] = $error;

        // Check target MCP server reachability (if provided)
        $targetOk = null;
        $targetError = null;
        if ($url) {
            $parsed = parse_url($url);
            if (!$parsed || empty($parsed['host'])) {
                $targetOk = false;
                $targetError = 'آدرس MCP نامعتبر است';
            } elseif (!$canConnect) {
                $targetOk = false;
                $targetError = 'به دلیل مسدود بودن خروجی HTTP، امکان تست هدف وجود ندارد';
            } else {
                // Try HEAD/GET to MCP endpoint
                $headersStr = "User-Agent: NeuronAgent/1.0\r\nAccept: application/json, text/event-stream\r\n";
                if ($headers) {
                    foreach ($headers as $k => $v) {
                        $headersStr .= "$k: $v\r\n";
                    }
                }
                $ctx2 = stream_context_create([
                    'http' => ['method' => 'GET', 'header' => $headersStr, 'timeout' => 10, 'ignore_errors' => true],
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                ]);
                $r2 = @file_get_contents($url, false, $ctx2);
                $code = 0;
                if (isset($http_response_header[0]) && preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m)) {
                    $code = (int) $m[1];
                }
                // MCP http servers typically return 200, 401, 405, or 406 — all indicate reachable
                // Only 0 (no response) or 5xx with timeout is considered unreachable
                if ($r2 !== false || $code >= 200) {
                    $targetOk = $code < 500 || $code === 0 ? true : false;
                    if ($code === 401 || $code === 403) {
                        $targetError = "سرور در دسترس است اما نیاز به احراز هویت دارد (HTTP $code)";
                        $targetOk = true; // reachable, just needs auth
                    } elseif ($code >= 400 && $code < 500 && $code !== 404) {
                        $targetOk = true;
                    } elseif ($code === 404) {
                        $targetError = "آدرس یافت نشد (404) — مسیر MCP را بررسی کنید";
                    }
                } else {
                    $targetOk = false;
                    $targetError = 'اتصال به سرور MCP برقرار نشد (timeout یا DNS)';
                }
            }
        }
        $checks['target_ok'] = $targetOk;
        $checks['target_error'] = $targetError;

        return $checks;
    }
}
