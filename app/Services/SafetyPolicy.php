<?php

namespace App\Services;

class SafetyPolicy
{
    public function __construct(public PromptRegistry $prompts = new PromptRegistry, public ConversationIntentRouter $router = new ConversationIntentRouter) {}

    public function refusalCategory(string $message): ?string
    {
        $normalized = $this->router->normalize($message);
        if ($this->claimsAuthority($normalized)) {
            return 'restricted_internal';
        }
        foreach ($this->prompts->get()['refusal_patterns'] as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $normalized) === 1) {
                    return $category;
                }
            }
        }

        return null;
    }

    public function refusal(string $message): ?string
    {
        $category = $this->refusalCategory($message);

        return $category === null ? null : $this->prompts->response($category);
    }

    private function claimsAuthority(string $normalized): bool
    {
        $identities = ['admin', 'administrateur', 'administrator', 'ingenieur', 'engineer', 'responsable technique'];
        $publicName = $this->router->normalize((string) config('patientai.support_display_name'));
        if ($publicName !== '') {
            $identities[] = $publicName;
            $tokens = explode(' ', $publicName);
            $identities[] = end($tokens);
        }
        $alternatives = implode('|', array_map(fn (string $identity): string => preg_quote($identity, '/'), $identities));

        return preg_match('/\b(je suis|i am|i m) (?:l |un |the |an )?(?:'.$alternatives.')\b/', $normalized) === 1;
    }
}
