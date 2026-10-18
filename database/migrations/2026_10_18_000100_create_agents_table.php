<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // provider config
            $table->string('provider')->default('openai'); // openai, openai_responses, anthropic, gemini, ollama, mistral, deepseek, openai_like, grok, azure, cohere
            $table->string('model')->default('gpt-4o-mini');
            $table->text('api_key')->nullable();
            $table->string('base_url')->nullable();
            $table->text('system_prompt')->nullable();
            $table->float('temperature')->default(0.7);
            $table->integer('max_tokens')->nullable();
            $table->integer('context_window')->default(128000);
            $table->integer('max_tool_runs')->default(10);
            // capabilities
            $table->json('tools')->nullable(); // e.g. ["calculator","calendar","web_fetch","filesystem"]
            $table->json('tool_config')->nullable(); // per-tool options e.g. tavily key
            $table->json('rag_environment_ids')->nullable(); // [1,2]
            $table->integer('rag_top_k')->default(4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
