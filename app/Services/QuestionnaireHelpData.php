<?php

namespace App\Services;

final readonly class QuestionnaireHelpData
{
    /** @param array<string, string> $vocabulary */
    public function __construct(public bool $available = false, public string $assessmentUuid = '', public string $questionnaireName = '', public int $definitionVersion = 0, public bool $isDemo = false, public string $url = '', public string $guideVersion = '', public string $source = '', public string $objective = '', public string $instructions = '', public string $navigation = '', public array $vocabulary = [], public ?QuestionHelpData $question = null) {}
}
