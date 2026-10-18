<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('گفتگوی جدید');
            $table->string('thread_id')->unique();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('user'); // user, assistant, system, tool
            $table->longText('content');
            $table->json('attachments')->nullable(); // [{name, path, mime, size}]
            $table->json('meta')->nullable(); // tool calls, rag sources, etc
            $table->timestamps();
            $table->index(['chat_session_id', 'id']);
        });

        // Compatible with Neuron SQLMessageStore for persistent memory (optional)
        Schema::create('neuron_chat_history', function (Blueprint $table) {
            $table->id();
            $table->string('thread_id')->index();
            $table->string('message_id');
            $table->string('role');
            $table->longText('content');
            $table->longText('meta')->nullable();
            $table->timestamps();
            $table->unique(['thread_id', 'message_id']);
        });

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('token_hash')->unique();
            $table->string('plain_prefix')->nullable();
            $table->json('agent_ids')->nullable(); // null = all agents
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('neuron_chat_history');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
    }
};
