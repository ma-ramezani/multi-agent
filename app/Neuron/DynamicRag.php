<?php

namespace App\Neuron;

use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use NeuronAI\RAG\RAG;
use NeuronAI\RAG\Retrieval\RetrievalInterface;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;

class DynamicRag extends RAG
{
    protected array $config = [];
    protected array $ragConfig = [];

    public function __construct(array $config = [], array $ragConfig = [], ?string $workflowId = null, $state = null)
    {
        parent::__construct($workflowId, $state);
        $this->config = $config;
        $this->ragConfig = $ragConfig;
    }

    public static function fromConfig(array $config, array $ragConfig, ?string $threadId = null): static
    {
        $a = new static($config, $ragConfig);
        if ($threadId) {
            $a->setThreadId($threadId);
        }
        return $a;
    }

    protected function provider(): \NeuronAI\Providers\AIProviderInterface
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

    protected function instructions(): \NeuronAI\Chat\Messages\SystemMessage|string
    {
        $prompt = $this->config['system_prompt'] ?? null;
        if (!$prompt) {
            $prompt = 'شما یک دستیار هوشمند فارسی‌زبان هستید که به دانش داخلی سازمان (RAG) دسترسی دارید. ابتدا از دانش بازیابی‌شده استفاده کنید و اگر چیزی نبود صادقانه بگویید. نکته مهم: متن بازیابی‌شده ممکن است غلط‌های تایپی OCR داشته باشد (مثل حروف جابه‌جا یا علائم اضافی)؛ با اغماض بخوانید و اگر جواب در آن هست، همان را بدهید و به‌خاطر نویز متن، جواب را «یافت نشد» اعلام نکنید.';
        }
        return new \NeuronAI\Chat\Messages\SystemMessage($prompt);
    }

    protected function tools(): array
    {
        return ToolRegistry::build($this->config['tools'] ?? [], $this->config['tool_config'] ?? []);
    }

    protected function messageStore(): \NeuronAI\Chat\History\MessageStoreInterface
    {
        return new \NeuronAI\Chat\History\FileMessageStore(directory: storage_path('app/neuron-chats'));
    }

    protected function contextWindow(): int
    {
        return (int) ($this->config['context_window'] ?? 128000);
    }

    protected function embeddings(): EmbeddingsProviderInterface
    {
        $env = $this->ragConfig['primary_env'] ?? null;
        if ($env instanceof \App\Models\RagEnvironment) {
            return EmbeddingFactory::make($env);
        }
        // fallback: openai with demo key (will fallback to keyword search on failure)
        return new \NeuronAI\RAG\Embeddings\OpenAIEmbeddingsProvider(
            key: env('OPENAI_API_KEY', 'demo'),
            model: 'text-embedding-3-small',
        );
    }

    protected function vectorStore(): VectorStoreInterface
    {
        return new DatabaseVectorStore(
            environmentIds: $this->ragConfig['environment_ids'] ?? [],
            topK: $this->ragConfig['top_k'] ?? 4,
        );
    }

    protected function retrieval(): RetrievalInterface
    {
        return new FallbackRetrieval(
            vectorStore: $this->resolveVectorStore(),
            embeddingProvider: $this->resolveEmbeddingsProvider(),
            topK: $this->ragConfig['top_k'] ?? 4,
        );
    }

    protected function resolveToolErrorHandler(): ?callable
    {
        return fn (\Throwable $e) => 'خطا در اجرای ابزار: ' . $e->getMessage();
    }
}
