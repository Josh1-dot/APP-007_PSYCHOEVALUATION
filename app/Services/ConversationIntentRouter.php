<?php

namespace App\Services;

use Illuminate\Support\Str;

class ConversationIntentRouter
{
    public function route(string $message): string
    {
        $normalized = $this->normalize($message);

        return match ($normalized) {
            'bonjour', 'bonsoir', 'salut', 'hello', 'hi', 'coucou', 'bjr', 'bonjor', 'bonjou', 'bon jour', 'bon soir', 'slt' => 'greeting',
            'merci', 'merci beaucoup', 'mercii', 'svp', 's il vous plait', 's il te plait' => 'courtesy',
            'au revoir', 'a bientot', 'bonne journee', 'bonne soiree', 'bonne nuit', 'a plus', 'a la prochaine' => 'farewell',
            'qui es tu', 'qui etes vous', 'tu es qui' => 'identity',
            'que peux tu faire', 'que pouvez vous faire', 'aide moi', 'aidez moi' => 'capabilities',
            default => 'unknown',
        };
    }

    /** @return array{tool: string, filters: array<string, string>, uuid: string}|null */
    public function assessmentRequest(string $message): ?array
    {
        $normalized = $this->normalize($message);
        $lists = ['quelles sont mes evaluations', 'montre moi mes evaluations', 'mes evaluations', 'ai je des evaluations en cours'];
        if (in_array($normalized, $lists, true)) {
            return ['tool' => 'list', 'filters' => $normalized === 'ai je des evaluations en cours' ? ['status' => 'en_cours'] : [], 'uuid' => ''];
        }
        if (preg_match('/^(?:quel est le statut de mon evaluation|statut de mon evaluation|statut evaluation)(?:\s+(.+?))?[?.!]*$/iu', Str::ascii(trim($message)), $matches)) {
            return ['tool' => 'status', 'filters' => [], 'uuid' => trim($matches[1] ?? '', ' ?.!')];
        }

        return null;
    }

    public function normalize(string $message): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($message))) ?? '');
    }
}
