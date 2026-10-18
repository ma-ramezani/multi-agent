<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rag_documents', function (Blueprint $table) {
            $table->boolean('force_ocr')->default(false)->after('raw_text');
        });
    }

    public function down(): void
    {
        Schema::table('rag_documents', function (Blueprint $table) {
            $table->dropColumn('force_ocr');
        });
    }
};
