<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientRagDocument extends TenantModel
{
    use HasFactory;

    public const AUDIENCES = ['PATIENT_PUBLIC', 'PATIENT_CONTEXTUAL', 'PROFESSIONAL_ONLY', 'ADMIN_INTERNAL', 'SECURITY_SECRET'];

    protected $attributes = ['status' => 'draft', 'review_status' => 'pending', 'approval_status' => 'pending'];

    protected $hidden = ['content', 'source', 'reviewed_by', 'approved_by'];

    protected function casts(): array
    {
        return ['content' => 'encrypted', 'source' => 'encrypted', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'chunk_size' => 'integer'];
    }

    /** @return array<string, mixed> */
    public function getAttributesForRag(): array
    {
        return $this->only(['document_key', 'title', 'version', 'source', 'source_label', 'audience', 'assessment_definition_id', 'content']);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(PatientRagChunk::class);
    }
}
