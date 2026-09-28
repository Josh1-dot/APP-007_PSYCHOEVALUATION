<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->unsignedInteger('retention_days')->default(1825);
            $t->timestamps();
        });
        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->text('address')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('role')->default('patient');
            $t->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $t->boolean('active')->default(true);
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $t->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->string('first_name');
            $t->string('last_name');
            $t->string('email');
            $t->string('phone')->nullable();
            $t->date('birth_date')->nullable();
            $t->string('status')->default('actif');
            $t->text('reason')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'email']);
        });
        Schema::create('consents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('client_id')->constrained()->restrictOnDelete();
            $t->string('version');
            $t->text('text');
            $t->timestamp('accepted_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('assessment_definitions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->uuid('family');
            $t->string('name');
            $t->string('kind');
            $t->unsignedInteger('version');
            $t->string('engine_version')->default('raw-v1');
            $t->json('questions');
            $t->boolean('is_demo')->default(false);
            $t->timestamps();
            $t->unique(['tenant_id', 'family', 'version']);
        });
        Schema::create('assessments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('client_id')->constrained()->restrictOnDelete();
            $t->foreignId('assessment_definition_id')->constrained()->restrictOnDelete();
            $t->foreignId('assigned_by')->constrained('users');
            $t->string('status')->default('en_cours');
            $t->date('due_at')->nullable();
            $t->longText('answers')->nullable();
            $t->longText('results')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('interpretations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('assessment_id')->unique()->constrained();
            $t->longText('draft');
            $t->string('source')->default('professionnel');
            $t->string('model')->nullable();
            $t->string('prompt_version')->nullable();
            $t->longText('input_snapshot')->nullable();
            $t->longText('published_content')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });
        Schema::create('clinical_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('client_id')->constrained();
            $t->foreignId('author_id')->constrained('users');
            $t->longText('body');
            $t->timestamps();
        });
        Schema::create('appointments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('client_id')->constrained();
            $t->string('title');
            $t->dateTime('starts_at');
            $t->unsignedInteger('duration')->default(60);
            $t->string('location')->nullable();
            $t->string('status')->default('planifie');
            $t->timestamps();
        });
        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('sender_id')->constrained('users');
            $t->foreignId('recipient_id')->constrained('users');
            $t->longText('body');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('client_id')->nullable()->constrained();
            $t->foreignId('organization_id')->nullable()->constrained();
            $t->foreignId('uploaded_by')->constrained('users');
            $t->string('name');
            $t->string('path');
            $t->string('mime');
            $t->unsignedBigInteger('size');
            $t->boolean('shared')->default(false);
            $t->timestamps();
        });
        Schema::create('letters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('client_id')->constrained();
            $t->string('subject');
            $t->longText('body');
            $t->timestamps();
        });
        Schema::create('comparisons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('first_assessment_id')->constrained('assessments');
            $t->foreignId('second_assessment_id')->constrained('assessments');
            $t->longText('snapshot');
            $t->longText('analysis')->nullable();
            $t->timestamps();
        });
        Schema::create('workspace_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('author_id')->constrained('users');
            $t->string('title');
            $t->longText('body');
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('action');
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'workspace_documents', 'comparisons', 'letters', 'documents', 'messages', 'appointments', 'clinical_notes', 'interpretations', 'assessments', 'assessment_definitions', 'consents', 'clients'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('organization_id');
            $t->dropConstrainedForeignId('tenant_id');
            $t->dropColumn(['role', 'active']);
        });
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('tenants');
    }
};
