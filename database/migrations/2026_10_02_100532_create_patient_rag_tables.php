<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_rag_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('document_key', 80);
            $table->string('title', 120);
            $table->string('version', 80);
            $table->text('source');
            $table->string('source_label', 120);
            $table->string('audience');
            $table->foreignId('assessment_definition_id')->nullable()->constrained()->restrictOnDelete();
            $table->longText('content');
            $table->string('status')->default('draft');
            $table->string('review_status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_checksum', 64)->nullable();
            $table->string('approval_status')->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('approval_checksum', 64)->nullable();
            $table->string('index_checksum', 64)->nullable();
            $table->string('index_version')->nullable();
            $table->unsignedInteger('chunk_size')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'document_key', 'version']);
            $table->index(['tenant_id', 'status', 'audience']);
        });
        Schema::create('patient_rag_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_rag_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ordinal');
            $table->longText('content');
            $table->string('checksum', 64);
            $table->timestamps();
            $table->unique(['patient_rag_document_id', 'ordinal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_rag_chunks');
        Schema::dropIfExists('patient_rag_documents');
    }
};
