<?php

namespace App\Services;

interface LlmProvider
{
    public function reply(string $intent): string;
}
