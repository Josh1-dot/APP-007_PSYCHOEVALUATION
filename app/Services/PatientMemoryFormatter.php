<?php

namespace App\Services;

class PatientMemoryFormatter
{
    public function __construct(public PromptRegistry $prompts = new PromptRegistry) {}

    public function format(string $intent, PatientMemoryData $memory): string
    {
        $style = in_array($memory->responseStyle, ['standard', 'concise'], true) ? $memory->responseStyle : null;
        if ($intent === 'memory') {
            return $style === null ? 'Aucune préférence de présentation mémorisée n’est disponible dans cette conversation.' : 'Votre préférence de présentation mémorisée est : '.($style === 'concise' ? 'concise' : 'standard').'. Elle ne constitue aucune information clinique.';
        }
        $reply = $this->prompts->response($intent);

        return $intent === 'greeting' && $style === 'concise' ? 'Bonjour ! Comment puis-je vous aider ?' : $reply;
    }
}
