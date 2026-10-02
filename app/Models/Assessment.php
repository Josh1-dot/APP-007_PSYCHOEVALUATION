<?php

namespace App\Models;

use Illuminate\Support\Str;

class Assessment extends TenantModel
{
    protected static function booted(): void
    {
        parent::booted();
        static::creating(function (Assessment $assessment): void {
            $assessment->uuid = (string) Str::uuid();
        });
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function definition()
    {
        return $this->belongsTo(AssessmentDefinition::class, 'assessment_definition_id');
    }

    public function interpretation()
    {
        return $this->hasOne(Interpretation::class);
    }

    protected function casts(): array
    {
        return ['answers' => 'encrypted:array', 'results' => 'encrypted:array', 'submitted_at' => 'datetime', 'due_at' => 'date'];
    }
}
