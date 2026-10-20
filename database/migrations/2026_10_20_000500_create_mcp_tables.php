<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('url'); // http(s) URL for Streamable HTTP transport
            $table->json('headers')->nullable(); // extra headers (e.g. Authorization)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('agent_mcp_server', function (Blueprint $table) {
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mcp_server_id')->constrained()->cascadeOnDelete();
            $table->primary(['agent_id', 'mcp_server_id']);
        });

        Schema::table('agents', function (Blueprint $table) {
            // Keep for backward compat but now via pivot; not needed to add column
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_mcp_server');
        Schema::dropIfExists('mcp_servers');
    }
};
