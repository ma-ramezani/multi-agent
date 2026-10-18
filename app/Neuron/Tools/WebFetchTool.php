<?php

namespace App\Neuron\Tools;

use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolOutput;
use NeuronAI\Tools\ToolProperty;

class WebFetchTool extends Tool
{
    protected string $name = 'web_fetch';
    protected ?string $description = 'Fetch the text content of a web page URL. Use when the user asks about a URL or needs fresh web info.';

    protected function properties(): array
    {
        return [
            new ToolProperty('url', PropertyType::STRING, 'Full http(s) URL to fetch', true),
        ];
    }

    public function __invoke(string $url): ToolOutput
    {
        if (!preg_match('#^https?://#i', $url)) {
            return ToolOutput::text('Invalid URL. Must start with http:// or https://');
        }
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 15, 'header' => "User-Agent: NeuronAgent/1.0\r\n"]]);
            $html = @file_get_contents($url, false, $ctx, 0, 500000);
            if ($html === false) {
                return ToolOutput::text("Could not fetch URL: $url");
            }
            $text = strip_tags($html);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = preg_replace('/\s+/u', ' ', $text);
            return ToolOutput::text(mb_substr(trim($text), 0, 12000));
        } catch (\Throwable $e) {
            return ToolOutput::text('Fetch error: ' . $e->getMessage());
        }
    }
}
