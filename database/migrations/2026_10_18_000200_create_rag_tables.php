<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rag_environments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('embedding_provider')->default('openai'); // openai, ollama, gemini, openai_like
            $table->string('embedding_model')->default('text-embedding-3-small');
            $table->text('embedding_api_key')->nullable();
            $table->string('embedding_base_url')->nullable();
            $table->integer('chunk_size')->default(1000);
            $table->integer('chunk_overlap')->default(100);
            $table->integer('top_k')->default(4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rag_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rag_environment_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_path')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status')->default('pending'); // pending, processing, ready, failed
            $table->text('error')->nullable();
            $table->integer('chunks_count')->default(0);
            $table->text('raw_text')->nullable(); // for manual text entries
            $table->timestamps();
        });

        Schema::create('rag_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rag_environment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rag_document_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('content');
            $table->longText('embedding')->nullable(); // json array
            $table->json('metadata')->nullable();
            $table->string('source_type')->default('file');
            $table->string('source_name')->nullable();
            $table->timestamps();
            $table->index(['rag_environment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_chunks');
        Schema::dropIfExists('rag_documents');
        Schema::dropIfExists('rag_environments');
    }
};
