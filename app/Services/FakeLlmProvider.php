<?php

namespace App\Services;

class FakeLlmProvider implements LlmProvider
{
    public function __construct(public PromptRegistry $prompts = new PromptRegistry) {}

    public function reply(string $intent): string
    {
        return $this->prompts->response($intent);
    }
}
