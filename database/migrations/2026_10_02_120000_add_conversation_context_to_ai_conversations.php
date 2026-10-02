<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table): void {
            $table->longText('conversation_context')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table): void {
            $table->dropColumn('conversation_context');
        });
    }
};
