<?php

namespace App\Neuron;

use NeuronAI\Agent\Agent;
use NeuronAI\Chat\History\FileMessageStore;
use NeuronAI\Chat\History\MessageStoreInterface;
use NeuronAI\Chat\Messages\SystemMessage;
use NeuronAI\Providers\AIProviderInterface;

class DynamicAgent extends Agent
{
    protected array $config = [];

    public function __construct(array $config = [], ?string $workflowId = null, $state = null)
    {
        parent::__construct($workflowId, $state);
        $this->config = $config;
    }

    public static function fromConfig(array $config, ?string $threadId = null): static
    {
        $a = new static($config);
        if ($threadId) {
            $a->setThreadId($threadId);
        }
        return $a;
    }

    protected function provider(): AIProviderInterface
    {
        return ProviderFactory::make(
            $this->config['provider'] ?? 'openai',
            $this->config['model'] ?? 'gpt-4o-mini',
            $this->config['api_key'] ?? null,
            $this->config['base_url'] ?? null,
            ProviderFactory::parametersFor(
                (float) ($this->config['temperature'] ?? 0.7),
                $this->config['max_tokens'] ?? null,
            ),
            $this->getThreadId(),
        );
    }

    protected function instructions(): SystemMessage|string
    {
        $prompt = $this->config['system_prompt'] ?? null;
        if (!$prompt) {
            $prompt = 'شما یک دستیار هوشمند فارسی‌زبان هستید که با فریم‌ورک Neuron AI ساخته شده‌اید. دقیق، مودب و کاربردی پاسخ دهید.';
        }
        // Enforced guardrails: always appended, not editable from UI.
        return new SystemMessage(SecurityPolicy::apply($prompt));
    }

    protected function tools(): array
    {
        return ToolRegistry::build($this->config['tools'] ?? [], $this->config['tool_config'] ?? []);
    }

    protected function messageStore(): MessageStoreInterface
    {
        return new FileMessageStore(directory: storage_path('app/neuron-chats'));
    }

    protected function contextWindow(): int
    {
        return (int) ($this->config['context_window'] ?? 128000);
    }

    protected function resolveToolErrorHandler(): ?callable
    {
        return fn (\Throwable $e) => 'خطا در اجرای ابزار: ' . $e->getMessage();
    }
}
