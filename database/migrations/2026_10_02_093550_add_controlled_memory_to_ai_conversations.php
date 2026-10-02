<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table): void {
            $table->boolean('memory_enabled')->default(false);
            $table->string('memory_consent_version')->nullable();
            $table->timestamp('memory_consented_at')->nullable();
            $table->text('memory')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table): void {
            $table->dropColumn(['memory_enabled', 'memory_consent_version', 'memory_consented_at', 'memory']);
        });
    }
};
