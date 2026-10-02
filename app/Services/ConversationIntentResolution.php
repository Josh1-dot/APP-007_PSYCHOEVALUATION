<?php

namespace App\Services;

final readonly class ConversationIntentResolution
{
    public const VERSION = 'patientai-conversation-v1.1';

    /** @param array<string, mixed> $parameters */
    public function __construct(public string $intent, public array $parameters = []) {}
}
