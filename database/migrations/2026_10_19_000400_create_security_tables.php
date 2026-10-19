<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64)->index(); // db_blocked, fs_blocked, ssrf_blocked, upload_rejected, injection_suspected, output_redacted, auth
            $table->string('level', 16)->default('info'); // info, warn, blocked
            $table->string('message', 500);
            $table->string('ip', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('security_settings');
    }
};
