<?php

namespace App\Models;

class AssessmentDefinition extends TenantModel
{
    protected static function booted(): void
    {
        parent::booted();
        static::creating(function (AssessmentDefinition $definition): void {
            $definition->version_scope = $definition->kind === 'enneagramme' && $definition->engine_version === 'enneagramme-weighted-v1' ? $definition->form_key : '';
        });
        static::updating(function (AssessmentDefinition $definition): void {
            if ($definition->getOriginal('kind') === 'enneagramme' && $definition->isDirty(['version_scope', 'tenant_id', 'family', 'name', 'kind', 'version', 'engine_version', 'questions', 'scoring_rules', 'form_key', 'source_reference', 'licensed', 'is_demo', 'created_by'])) {
                abort(409, 'Une définition Ennéagramme versionnée est immuable ; créez une nouvelle version.');
            }
        });
    }

    protected function casts(): array
    {
        return ['questions' => 'array', 'scoring_rules' => 'array', 'is_demo' => 'boolean', 'licensed' => 'boolean', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime'];
    }
}
