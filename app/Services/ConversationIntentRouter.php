<?php

namespace App\Services;

use Illuminate\Support\Str;

class ConversationIntentRouter
{
    public function route(string $message): string
    {
        $wrappedQuery = $this->explicitDocumentaryQuery($message);
        $routingMessage = $wrappedQuery ?? $message;
        $normalized = $this->normalize($this->withoutUuid($routingMessage));
        $social = preg_replace('/ (?:patientai|patientsai)$/', '', $normalized) ?? $normalized;

        $greetings = ['bonjour', 'bonsoir', 'salut', 'hello', 'hi', 'coucou', 'bjr', 'bonjor', 'bonjou', 'bon jour', 'bon soir', 'slt'];
        if (in_array($social, $greetings, true)) {
            return 'greeting';
        }

        return match (true) {
            in_array($normalized, ['merci', 'merci beaucoup', 'mercii', 'svp', 's il vous plait', 's il te plait'], true) => 'courtesy',
            in_array($normalized, ['au revoir', 'a bientot', 'bonne journee', 'bonne soiree', 'bonne nuit', 'a plus', 'a la prochaine'], true) => 'farewell',
            in_array($normalized, ['qui es tu', 'qui etes vous', 'tu es qui', 'comment tu t appelles', 'comment vous appelez vous', 'who are you', 'what is your name'], true) => 'identity',
            in_array($normalized, ['que peux tu faire', 'que pouvez vous faire', 'aide moi', 'aidez moi', 'comment peux tu m aider', 'comment pouvez vous m aider', 'what can you do', 'what can you help me with', 'how can you help me'], true) => 'capabilities',
            $this->isAssessmentsList($normalized) => 'assessments_list',
            $this->isAssessmentStatus($normalized) => 'assessment_status',
            $this->isQuestionnaireHelp($normalized) => 'questionnaire_help',
            $this->isAppointmentNext($normalized) => 'appointment_next',
            $this->isAppointmentsList($normalized) => 'appointments_list',
            $this->isPublishedResult($normalized) => 'published_result',
            $this->isMemoryStatus($normalized) => 'memory_status',
            $this->isAboutMyData($normalized) => 'about_my_data',
            $this->appointmentRequest($routingMessage) === 'read_only' || $this->helpRequest($routingMessage) !== null || $this->documentaryRequest($message) !== null || $this->isDocumentationRequest($normalized) => 'documentation',
            $wrappedQuery !== null => 'documentation',
            default => 'unknown',
        };
    }

    public function resolve(string $message): ConversationIntentResolution
    {
        $intent = $this->route($message);
        $routingMessage = $this->explicitDocumentaryQuery($message) ?? $message;
        $parameters = match ($intent) {
            'assessments_list' => $this->assessmentRequest($routingMessage) ?? [],
            'assessment_status' => [...($this->assessmentRequest($routingMessage) ?? []), 'follow_up' => in_array($this->normalize($routingMessage), ['et son statut', 'et le statut'], true)],
            'questionnaire_help' => [...($this->helpRequest($routingMessage) ?? []), 'follow_up' => in_array($this->normalize($routingMessage), ['explique le moi', 'explique le moi ce questionnaire'], true)],
            'appointment_next' => ['tool' => 'next', 'follow_up' => $this->normalize($routingMessage) === 'quand'],
            'appointments_list' => ['tool' => 'list'],
            'published_result' => ['uuid' => $this->publishedResultRequest($routingMessage) ?? ''],
            'documentation' => $this->documentationParameters($message),
            default => [],
        };

        return new ConversationIntentResolution($intent, $parameters);
    }

    /** @return array<string, mixed> */
    private function documentationParameters(string $message): array
    {
        if ($this->appointmentRequest($message) === 'read_only') {
            return ['topic' => 'appointments'];
        }
        $routingMessage = $this->explicitDocumentaryQuery($message) ?? $message;
        $help = $this->helpRequest($routingMessage);
        if (isset($help['topic'])) {
            return ['topic' => $help['topic']];
        }
        $query = $this->documentaryRequest($message);
        if ($query !== null) {
            return ['query' => $query];
        }

        return ['topic' => 'dashboard'];
    }

    private function isAssessmentsList(string $normalized): bool
    {
        return in_array($normalized, [
            'quelles sont mes evaluations', 'quels sont mes evaluations', 'montre moi mes evaluations', 'liste mes evaluations',
            'j ai un test a faire', 'ai je un test a faire', 'quel questionnaire dois je faire',
            'mes evaluations', 'ai je des evaluations', 'ai je des evaluations en cours', 'what assessments do i have',
            'list my assessments', 'what are my assessments', 'show my assessments', 'list my tests',
        ], true);
    }

    private function isAssessmentStatus(string $normalized): bool
    {
        return in_array($normalized, [
            'quel est le statut de mon evaluation', 'quel est le statut de cette evaluation', 'statut de mon evaluation',
            'statut evaluation', 'ou en est mon evaluation', 'ou en est mon test', 'et son statut', 'et le statut',
            'what is the status of my assessment', 'what is my assessment status', 'how is my assessment going',
        ], true);
    }

    private function isQuestionnaireHelp(string $normalized): bool
    {
        return in_array($normalized, [
            'explique moi mon questionnaire', 'explique moi ce questionnaire', 'je ne comprends pas ce questionnaire',
            'je ne comprends pas mon questionnaire', 'comment fonctionne mon questionnaire', 'comment fonctionne ce questionnaire',
            'explique moi mon test', 'explique le moi', 'explique le moi ce questionnaire', 'que signifie cette question', 'explain my questionnaire', 'explain this questionnaire',
            'i do not understand this questionnaire', 'i dont understand this questionnaire', 'help me understand my questionnaire',
        ], true) || $this->helpRequest($normalized) !== null && preg_match('/\b(?:aide questionnaire|explique la consigne|explique l echelle|explique le vocabulaire|explique la navigation)\b/', $normalized) === 1;
    }

    private function isAppointmentNext(string $normalized): bool
    {
        return in_array($normalized, [
            'ai je un rendez vous', 'ai je un rendez vous prochainement', 'quand est mon prochain rendez vous',
            'quel est mon prochain rendez vous', 'mon prochain rendez vous', 'j ai un rendez vous', 'quand',
            'do i have an appointment', 'when is my next appointment', 'what is my next appointment',
        ], true);
    }

    private function isAppointmentsList(string $normalized): bool
    {
        return in_array($normalized, [
            'quels sont mes rendez vous', 'quels sont mes prochains rendez vous', 'quelles sont mes rendez vous',
            'montre moi mes prochains rendez vous', 'mes prochains rendez vous', 'mes rendez vous', 'mes rendez vous a venir',
            'liste mes rendez vous', 'list my appointments', 'what appointments do i have',
        ], true);
    }

    private function isPublishedResult(string $normalized): bool
    {
        return in_array($normalized, [
            'quel est mon resultat', 'quel est mon resultat publie', 'explique mon resultat', 'explique moi mon resultat',
            'explique mon resultat publie', 'explique moi mon resultat publie', 'quel est le resultat de cette evaluation',
            'que signifie ma restitution', 'what is my published result', 'explain my published result',
        ], true);
    }

    private function isMemoryStatus(string $normalized): bool
    {
        return in_array($normalized, [
            'as tu memorise ma preference', 'quelle preference de presentation ai je choisie',
            'quelle est ma preference de presentation', 'quelle est ma preference de reponse', 'que sais tu de ma preference de presentation',
            'do you remember my display preference', 'what preference did i choose',
        ], true);
    }

    private function isAboutMyData(string $normalized): bool
    {
        return in_array($normalized, [
            'que sais tu de moi', 'quelles informations utilises tu', 'quelles informations as tu sur moi',
            'what do you know about me', 'what information can you see',
        ], true);
    }

    private function isDocumentationRequest(string $normalized): bool
    {
        return in_array($normalized, [
            'comment utiliser l espace patient', 'comment utiliser mon espace patient', 'comment utiliser le portail patient',
            'que dit le guide', 'how do i use the patient portal', 'what does the guide say',
        ], true);
    }

    /** @return array{tool: string, filters: array<string, string>, uuid: string}|null */
    public function assessmentRequest(string $message): ?array
    {
        $normalized = $this->normalize($this->withoutUuid($message));
        if ($this->isAssessmentsList($normalized)) {
            return ['tool' => 'list', 'filters' => in_array($normalized, ['ai je des evaluations en cours', 'j ai un test a faire', 'ai je un test a faire', 'quel questionnaire dois je faire'], true) ? ['status' => 'en_cours'] : [], 'uuid' => ''];
        }
        if ($this->isAssessmentStatus($normalized)) {
            preg_match('/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i', $message, $matches);

            return ['tool' => 'status', 'filters' => [], 'uuid' => strtolower($matches[0] ?? '')];
        }

        return null;
    }

    /** @return array{topic: string}|array{uuid: string, question_id: ?string}|null */
    public function helpRequest(string $message): ?array
    {
        $normalized = $this->normalize($this->withoutUuid($message));
        $topic = match ($normalized) {
            'comment fonctionne mon compte', 'comment modifier mon mot de passe', 'mon compte' => 'account',
            'comment fonctionne le consentement', 'comment retirer mon consentement', 'mon consentement' => 'consent',
            'comment fonctionne mon tableau de bord', 'comment utiliser ce site', 'mon tableau de bord' => 'dashboard',
            'ou sont mes evaluations', 'comment acceder a mes evaluations' => 'assessments',
            'comment fonctionne une passation', 'comment sauvegarder mes reponses', 'est ce que je peux revenir a la question precedente', 'comment dois je utiliser cette page', 'comment soumettre mon questionnaire' => 'passations',
            'comment fonctionnent les questionnaires' => 'questionnaires',
            'qu est ce qu une forme', 'qu est ce qu une forme de questionnaire', 'pourquoi les questions sont differentes cette fois', 'je veux refaire mon test', 'je veux refaire mon questionnaire', 'comment refaire mon test' => 'questionnaires',
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
        if (in_array($normalized, [
            'explique moi mon questionnaire', 'explique moi ce questionnaire', 'je ne comprends pas ce questionnaire',
            'je ne comprends pas mon questionnaire', 'comment fonctionne mon questionnaire', 'comment fonctionne ce questionnaire',
            'explique moi mon test', 'que signifie cette question', 'explain my questionnaire', 'explain this questionnaire',
            'i do not understand this questionnaire', 'i dont understand this questionnaire', 'help me understand my questionnaire',
        ], true)) {
            preg_match('/\bquestion\s+([a-zA-Z][a-zA-Z0-9_]{0,39})\b/i', $message, $question);
            preg_match('/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i', $message, $uuid);

            return ['uuid' => strtolower($uuid[0] ?? ''), 'question_id' => $question[1] ?? null];
        }
        $text = str_replace(["'", '’'], ' ', Str::ascii(trim($message)));
        if (preg_match('/^(?:aide questionnaire|aide pour mon questionnaire|explique la consigne|explique l echelle|que signifie l echelle|explique le vocabulaire|que signifie ce mot|explique la navigation)(?:\s+([^\s]+))?(?:\s+question\s+([^\s]+))?\s*[?.!]*$/iD', $text, $matches)) {
            return ['uuid' => trim($matches[1] ?? '', '?.!'), 'question_id' => isset($matches[2]) ? trim($matches[2], '?.!') : null];
        }

        return null;
    }

    public function appointmentRequest(string $message): ?string
    {
        $normalized = $this->normalize($message);

        return match (true) {
            $this->isAppointmentsList($normalized) => 'list',
            $this->isAppointmentNext($normalized) => 'next',
            in_array($normalized, ['prends moi un rendez vous demain', 'prends moi un rendez vous', 'annule mon rendez vous', 'deplace mon rendez vous a vendredi', 'deplace mon rendez vous', 'modifie mon rendez vous'], true) => 'read_only',
            default => null,
        };
    }

    public function publishedResultRequest(string $message): ?string
    {
        if ($this->isPublishedResult($this->normalize($this->withoutUuid($message)))) {
            preg_match('/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i', $message, $matches);

            return strtolower($matches[0] ?? '');
        }

        return null;
    }

    public function documentaryRequest(string $message): ?string
    {
        $query = $this->explicitDocumentaryQuery($message);
        if ($query !== null) {
            return $query;
        }
        if (preg_match('/^(?:que dit le guide sur|what does the guide say about)\s+(.+)$/iuD', trim($message), $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    public function normalize(string $message): string
    {
        $decomposed = class_exists(\Normalizer::class) ? \Normalizer::normalize($message, \Normalizer::FORM_KD) : $message;

        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii(is_string($decomposed) ? $decomposed : $message))) ?? '');
    }

    private function withoutUuid(string $message): string
    {
        return preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i', ' ', $message) ?? $message;
    }

    private function explicitDocumentaryQuery(string $message): ?string
    {
        if (preg_match('/^(?:recherche documentaire|cherche dans la documentation|recherche dans la documentation)\s*:\s*(.+)$/iuD', trim($message), $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
