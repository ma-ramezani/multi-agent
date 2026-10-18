<?php

namespace App\Neuron\Tools;

use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolOutput;
use NeuronAI\Tools\ToolProperty;

class DatabaseReadTool extends Tool
{
    protected string $name = 'database_read';
    protected ?string $description = 'Run a read-only SELECT/SHOW/DESCRIBE query on the application database and return rows as JSON. Only SELECT allowed.';

    protected function properties(): array
    {
        return [
            new ToolProperty('sql', PropertyType::STRING, 'SELECT query to run (read-only)', true),
            new ToolProperty('limit', PropertyType::NUMBER, 'Max rows (default 20, max 100)', false),
        ];
    }

    public function __invoke(string $sql, int $limit = 20): ToolOutput
    {
        $trimmed = ltrim(strtoupper(trim($sql)));
        foreach (['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'] as $ok) {
            if (str_starts_with($trimmed, $ok)) {
                goto allowed;
            }
        }
        return ToolOutput::text('Only read-only queries (SELECT/SHOW/DESCRIBE/EXPLAIN) are allowed.');
        allowed:
        $limit = max(1, min(100, (int) $limit));
        try {
            $rows = \DB::select($sql);
            $rows = array_slice($rows, 0, $limit);
            $arr = array_map(fn ($r) => (array) $r, $rows);
            return ToolOutput::text(json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            return ToolOutput::text('SQL error: ' . $e->getMessage());
        }
    }
}
