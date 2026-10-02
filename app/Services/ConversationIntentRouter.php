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

    /** @return array{topic: string}|array{uuid: string, question_id: ?string}|null */
    public function helpRequest(string $message): ?array
    {
        $topic = match ($this->normalize($message)) {
            'comment fonctionne mon compte', 'comment modifier mon mot de passe', 'mon compte' => 'account',
            'comment fonctionne le consentement', 'comment retirer mon consentement', 'mon consentement' => 'consent',
            'comment fonctionne mon tableau de bord', 'comment utiliser ce site', 'mon tableau de bord' => 'dashboard',
            'ou sont mes evaluations', 'comment acceder a mes evaluations' => 'assessments',
            'comment fonctionne une passation', 'comment sauvegarder mes reponses', 'est ce que je peux revenir a la question precedente', 'comment dois je utiliser cette page', 'comment soumettre mon questionnaire' => 'passations',
            'comment fonctionnent les questionnaires' => 'questionnaires',
            'comment voir mes resultats', 'ou voir mes resultats publies' => 'results',
            'comment voir mes rendez vous', 'comment fonctionne le calendrier' => 'appointments',
            'comment fonctionne la messagerie', 'comment envoyer un message' => 'messages',
            'comment acceder a mes documents', 'comment telecharger mes documents' => 'documents',
            'comment mes donnees sont elles protegees', 'confidentialite' => 'privacy',
            'quels sont mes droits', 'comment exporter mes donnees', 'comment demander un effacement' => 'rights',
            'que fait patientai', 'comment fonctionne patientai' => 'patientai',
            'comment contacter l assistance', 'comment contacter le cabinet' => 'assistance',
            default => null,
        };
        if ($topic !== null) {
            return ['topic' => $topic];
        }
        $text = str_replace(["'", '’'], ' ', Str::ascii(trim($message)));
        if (preg_match('/^(?:aide questionnaire|aide pour mon questionnaire|explique la consigne|explique l echelle|que signifie l echelle|explique le vocabulaire|que signifie ce mot|explique la navigation)(?:\s+([^\s]+))?(?:\s+question\s+([^\s]+))?\s*[?.!]*$/iD', $text, $matches)) {
            return ['uuid' => trim($matches[1] ?? '', '?.!'), 'question_id' => isset($matches[2]) ? trim($matches[2], '?.!') : null];
        }

        return null;
    }

    public function appointmentRequest(string $message): ?string
    {
        return match ($this->normalize($message)) {
            'quels sont mes prochains rendez vous', 'montre moi mes prochains rendez vous', 'mes prochains rendez vous' => 'list',
            'ai je un rendez vous prochainement', 'quand est mon prochain rendez vous', 'quel est mon prochain rendez vous', 'mon prochain rendez vous' => 'next',
            'prends moi un rendez vous demain', 'prends moi un rendez vous', 'annule mon rendez vous', 'deplace mon rendez vous a vendredi', 'deplace mon rendez vous', 'modifie mon rendez vous' => 'read_only',
            default => null,
        };
    }

    public function publishedResultRequest(string $message): ?string
    {
        if (preg_match('/^(?:quel est mon resultat|montre[ -]moi mon resultat publie|explique mon resultat|quel est le resultat de cette evaluation)(?:\s+([^\s]+))?\s*[?.!]*$/iD', Str::ascii(trim($message)), $matches)) {
            return trim($matches[1] ?? '', '?.!');
        }

        return null;
    }

    public function normalize(string $message): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($message))) ?? '');
    }
}
