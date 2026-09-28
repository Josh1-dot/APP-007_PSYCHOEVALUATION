<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            $t->longText('logo')->nullable();
            $t->string('logo_mime')->nullable();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->unsignedInteger('auth_version')->default(0);
        });
        Schema::table('clients', function (Blueprint $t) {
            $t->timestamp('last_activity_at')->nullable();
            $t->timestamp('anonymized_at')->nullable();
            $t->boolean('retention_hold')->default(false);
            $t->text('retention_note')->nullable();
        });
        Schema::table('assessment_definitions', function (Blueprint $t) {
            $t->text('source_reference')->nullable();
            $t->boolean('licensed')->default(false);
        });
        Schema::create('user_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('user_id')->unique()->constrained();
            $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('accepted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('local_mails', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->longText('recipient');
            $t->longText('subject');
            $t->longText('body');
            $t->timestamp('expires_at');
            $t->timestamps();
        });
        Schema::create('privacy_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained();
            $t->foreignId('client_id')->constrained();
            $t->string('kind');
            $t->longText('details')->nullable();
            $t->string('status')->default('ouverte');
            $t->longText('response')->nullable();
            $t->foreignId('handled_by')->nullable()->constrained('users');
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
        });
        Schema::create('document_letter', function (Blueprint $t) {
            $t->foreignId('letter_id')->constrained()->cascadeOnDelete();
            $t->foreignId('document_id')->constrained()->restrictOnDelete();
            $t->primary(['letter_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_letter');
        Schema::dropIfExists('privacy_requests');
        Schema::dropIfExists('local_mails');
        Schema::dropIfExists('user_invitations');
        Schema::table('assessment_definitions', fn (Blueprint $t) => $t->dropColumn(['source_reference', 'licensed']));
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn(['last_activity_at', 'anonymized_at', 'retention_hold', 'retention_note']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('auth_version'));
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn(['logo', 'logo_mime']));
    }
};
