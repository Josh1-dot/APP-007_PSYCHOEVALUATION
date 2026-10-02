<?php

namespace App\Services;

final readonly class QuestionHelpData
{
    /** @param list<string> $options */
    public function __construct(public string $id, public string $instruction, public string $type, public bool $required, public ?int $minimum = null, public ?int $maximum = null, public array $options = []) {}
}
