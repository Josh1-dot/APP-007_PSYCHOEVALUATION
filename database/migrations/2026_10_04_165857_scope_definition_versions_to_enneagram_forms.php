<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_definitions', function (Blueprint $table): void {
            $table->string('version_scope', 40)->default('');
        });
        DB::table('assessment_definitions')->where('kind', 'enneagramme')->where('engine_version', 'enneagramme-weighted-v1')->update(['version_scope' => DB::raw("COALESCE(form_key, '')")]);
        Schema::table('assessment_definitions', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'family', 'version_scope', 'version'], 'definitions_form_version_unique');
            $table->dropUnique(['tenant_id', 'family', 'version']);
        });
    }

    public function down(): void
    {
        if (DB::table('assessment_definitions')->select('tenant_id', 'family', 'version')->groupBy('tenant_id', 'family', 'version')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Rollback impossible sans perdre des versions de formes ; aucune donnée supprimée.');
        }
        Schema::table('assessment_definitions', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'family', 'version']);
            $table->dropUnique('definitions_form_version_unique');
            $table->dropColumn('version_scope');
        });
    }
};
