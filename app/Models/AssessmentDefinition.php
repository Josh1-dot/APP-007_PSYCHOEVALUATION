<?php

namespace App\Models;

class AssessmentDefinition extends TenantModel
{
    protected function casts(): array
    {
        return ['questions' => 'array', 'is_demo' => 'boolean'];
    }
}
