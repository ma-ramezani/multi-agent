<?php

namespace App\Jobs;

use App\Models\RagDocument;
use App\Neuron\RagService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IngestRagDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $documentId) {}

    public function handle(): void
    {
        set_time_limit(0);
        $doc = RagDocument::find($this->documentId);
        if (!$doc) {
            return;
        }
        // RagService sets status/error itself; rethrow only for the queue to log it.
        RagService::ingestDocument($doc);
    }

    public function failed(\Throwable $e): void
    {
        RagDocument::where('id', $this->documentId)
            ->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
    }
}
