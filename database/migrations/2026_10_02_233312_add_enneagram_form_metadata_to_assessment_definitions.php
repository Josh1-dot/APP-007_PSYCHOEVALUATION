<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assessment_definitions', function (Blueprint $table) {
            $table->string('form_key', 40)->nullable();
            $table->json('scoring_rules')->nullable();
            $table->string('content_status', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->index(['tenant_id', 'family', 'content_status', 'form_key', 'version'], 'assessment_definitions_ennea_rotation_index');
        });
        DB::table('assessment_definitions')->where('kind', 'enneagramme')->update(['form_key' => 'LEGACY']);
        DB::table('assessment_definitions')->where('kind', 'enneagramme')->where('is_demo', true)->update(['content_status' => 'DEMO']);
        DB::table('assessment_definitions')->where('kind', 'enneagramme')->where('is_demo', false)->update(['content_status' => 'DRAFT']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessment_definitions', function (Blueprint $table) {
            $table->dropIndex('assessment_definitions_ennea_rotation_index');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['form_key', 'scoring_rules', 'content_status', 'reviewed_at', 'approved_at']);
        });
    }
};
