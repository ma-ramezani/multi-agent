<?php

namespace App\Neuron\Providers;

use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ContentBlocks\ContentBlockInterface;
use NeuronAI\Chat\Messages\ContentBlocks\ReasoningContent;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Exceptions\ProviderException;
use NeuronAI\HttpClient\Guzzle\GuzzleHttpClient;
use NeuronAI\HttpClient\StreamInterface;
use NeuronAI\Providers\OpenAILike;
use NeuronAI\Providers\ProviderResponse;

/**
 * OpenCode Go — OpenAI-compatible gateway (https://opencode.ai/v2/docs/console/go/)
 *
 * Differences vs. other providers (handled here):
 *  - Base URL: https://opencode.ai/inference/go/openai/v1  (→ POST /chat/completions)
 *  - Requires its own API key (OPENCODE_GO_API_KEY) as Bearer token
 *  - Wants extra headers (per docs "Where can I use it?"):
 *      * a custom User-Agent identifying our app (not a generic SDK name)
 *      * x-opencode-session: stable session id per conversation (routing + prompt caching)
 *  - Its SSE gateway sometimes closes the stream without a final `finish_reason`
 *    chunk; Neuron's default treats that as a hard failure
 *    ("The stream ended before the answer was complete."). We tolerate it when
 *    text was actually received and return the answer as complete.
 */
class OpenCodeGo extends OpenAILike
{
    public const DEFAULT_BASE_URL = 'https://opencode.ai/inference/go/openai/v1';

    /** Free (limited time) models on Go. */
    public const FREE_MODELS = [
        'longcat-2.5-preview-free' => 'LongCat 2.5 Preview (رایگان)',
        'beam-free' => 'Beam (رایگان، با محدودیت منطقه‌ای)',
    ];

    /** Cheap models worth trying. */
    public const CHEAP_MODELS = [
        'mimo-v2.6-flash' => 'MiMo V2.6 Flash (خیلی ارزان)',
        'muse-spark-1.3-contributor' => 'Muse Spark 1.3 (خیلی ارزان)',
        'glm-5.3-flash' => 'GLM 5.3 Flash (ارزان)',
        'deepseek-v4.1-flash' => 'DeepSeek V4.1 Flash (ارزان)',
        'qwen3.8-flash' => 'Qwen3.8 Flash (ارزان)',
        'kimi-k2.7-code' => 'Kimi K2.7 Code (کدنویسی)',
    ];

    public const DEFAULT_MODEL = 'longcat-2.5-preview-free';

    public function __construct(
        string $key,
        string $model = self::DEFAULT_MODEL,
        ?string $sessionId = null,
        array $parameters = [],
        string $baseUrl = self::DEFAULT_BASE_URL,
        float $timeout = 180.0,
    ) {
        parent::__construct(
            baseUri: rtrim($baseUrl, '/'),
            key: $key,
            model: $model,
            parameters: $parameters,
            httpClient: new GuzzleHttpClient(
                customHeaders: [
                    // Docs: identify with our own user agent, not a generic SDK name.
                    'User-Agent' => 'neuron-laravel-agent/1.0',
                    // Docs: stable session id per conversation for routing + prompt caching.
                    'x-opencode-session' => $sessionId ?: 'default',
                ],
                timeout: $timeout,
                options: ['proxy' => self::proxyOptions()],
            ),
        );
    }

    /**
     * Route through HTTP(S)_PROXY env when present (Guzzle ignores env proxies
     * by default, which surfaces as "Connection refused" on filtered networks).
     */
    public static function proxyOptions(): array|string
    {
        $http = getenv('HTTP_PROXY') ?: getenv('http_proxy') ?: null;
        $https = getenv('HTTPS_PROXY') ?: getenv('https_proxy') ?: null;

        if (!$http && !$https) {
            return [];
        }

        $proxy = [];
        if ($http) {
            $proxy['http'] = $http;
        }
        if ($https) {
            $proxy['https'] = $https;
        }

        $noProxy = getenv('NO_PROXY') ?: getenv('no_proxy') ?: '';
        $no = array_values(array_filter(array_map('trim', explode(',', $noProxy))));
        if ($no !== []) {
            $proxy['no'] = $no;
        }

        return $proxy;
    }

    /**
     * Tolerate gateways that end the SSE stream without `finish_reason`.
     * If text arrived, treat the answer as complete instead of failing the turn.
     */
    protected function earlyEndResponse(StreamInterface $stream, array $blocks, string $messageId, Usage $usage): ProviderResponse
    {
        $text = array_values(array_filter(
            $blocks,
            static fn (ContentBlockInterface $block): bool => $block instanceof TextContent
                && !$block instanceof ReasoningContent
                && $block->content !== '',
        ));

        if ($text === []) {
            throw new ProviderException('The stream ended before the answer was complete.');
        }

        $message = (new AssistantMessage($text))
            ->setStopReason('stop')
            ->setId($messageId)
            ->setUsage($usage);

        return new ProviderResponse(message: $message);
    }
}
