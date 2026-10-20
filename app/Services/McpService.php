<?php

namespace App\Services;

use NeuronAI\MCP\McpConnector;

class McpService
{
    /**
     * List tools from an MCP server, gracefully skipping tools with
     * unsupported schemas (e.g. anyOf) that Neuron cannot represent.
     *
     * @return array{tools: \NeuronAI\Tools\ToolInterface[], skipped: string[], error: ?string}
     */
    public static function listToolsSafe(string $url, ?array $headers = null): array
    {
        $config = ['url' => $url];
        if ($headers) {
            $config['headers'] = $headers;
        }

        try {
            $connector = McpConnector::make($config);
            $tools = $connector->tools();
            return ['tools' => $tools, 'skipped' => [], 'error' => null];
        } catch (\NeuronAI\Exceptions\ToolException $e) {
            if (!str_contains($e->getMessage(), 'anyOf')) {
                return ['tools' => [], 'skipped' => [], 'error' => $e->getMessage()];
            }
            // Fallback: fetch raw list and skip problematic tools one by one
            try {
                $client = new \NeuronAI\MCP\McpClient($config);
                $raw = $client->listTools();
                $ok = [];
                $skipped = [];
                foreach ($raw as $item) {
                    $schema = json_encode($item['inputSchema'] ?? '');
                    if (str_contains($schema, 'anyOf')) {
                        $skipped[] = $item['name'];
                        continue;
                    }
                    try {
                        // Try to create this single tool via a filtered connector
                        $single = McpConnector::make($config)->only([$item['name']])->tools();
                        if (!empty($single)) {
                            $ok = array_merge($ok, $single);
                        }
                    } catch (\Throwable $e2) {
                        $skipped[] = $item['name'];
                    }
                }
                return ['tools' => $ok, 'skipped' => $skipped, 'error' => $skipped ? 'برخی ابزارها به دلیل anyOf نادیده گرفته شدند: '.implode(', ', $skipped) : null];
            } catch (\Throwable $e2) {
                return ['tools' => [], 'skipped' => [], 'error' => $e2->getMessage()];
            }
        } catch (\Throwable $e) {
            return ['tools' => [], 'skipped' => [], 'error' => $e->getMessage()];
        }
    }
}
