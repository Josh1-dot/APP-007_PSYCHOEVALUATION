<?php

namespace App\Services;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class PatientGuideRegistry
{
    public const VERSION = 'patient-guide-v0.5.1';

    public const SOURCE = 'docs/patientai/knowledge/06-app007-functional-guide-patient.json';

    public const TOPICS = ['account', 'consent', 'dashboard', 'passations', 'assessments', 'questionnaires', 'results', 'appointments', 'messages', 'documents', 'privacy', 'rights', 'patientai', 'assistance'];

    public function __construct(public PatientContextFactory $contexts) {}

    public function guide(string $topic): PatientGuideData
    {
        $this->contexts->fromAuthenticatedUser();
        $document = $this->approved();
        if ($document === null || ! in_array($topic, self::TOPICS, true)) {
            return new PatientGuideData($topic, available: false);
        }
        $entry = $document['topics'][$topic];
        $route = match ($topic) {
            'account', 'consent', 'privacy', 'rights' => 'profile',
            'dashboard', 'passations', 'assessments', 'questionnaires', 'results' => 'dashboard',
            'appointments' => 'calendar.index',
            'messages', 'assistance' => 'messages.index',
            'documents' => 'documents.index',
            'patientai' => config('patientai.enabled') ? 'patientai.index' : null,
        };
        $links = $route !== null && Route::has($route) ? [route($route)] : [];

        if ($topic === 'assistance') {
            $entry['text'] .= ' Contact public : '.(config('patientai.support_display_name') ?: 'l’administrateur de la plateforme').', '.(config('patientai.support_role') ?: 'responsable de la plateforme').'.';
        }

        return new PatientGuideData($topic, $document['version'], $document['source'], $entry['provenance'], $entry['text'], $links);
    }

    /** @return array<string, mixed>|null */
    public function approved(): ?array
    {
        $document = $this->document();
        if (($document['audience'] ?? null) !== 'PATIENT_PUBLIC' || ($document['status'] ?? null) !== 'APPROVED' || ($document['version'] ?? null) !== self::VERSION || ($document['source'] ?? null) !== self::SOURCE) {
            return null;
        }
        $rules = ['approval.basis' => 'required|string|max:1000', 'approval.date' => 'required|date_format:Y-m-d', 'questionnaire.objective' => 'required|string|max:2000', 'questionnaire.instructions' => 'required|string|max:2000', 'questionnaire.navigation' => 'required|string|max:2000', 'questionnaire.vocabulary' => 'required|array:consigne,échelle,option', 'questionnaire.vocabulary.*' => 'required|string|max:1000'];
        foreach (self::TOPICS as $topic) {
            $rules['topics.'.$topic.'.text'] = 'required|string|max:2000';
            $rules['topics.'.$topic.'.provenance'] = 'required|array|min:1|max:10';
            $rules['topics.'.$topic.'.provenance.*'] = 'required|string|max:200';
        }

        return Validator::make($document, $rules)->fails() ? null : $document;
    }

    /** @return array<string, mixed> */
    protected function document(): array
    {
        $path = base_path(self::SOURCE);
        if (! is_file($path) || filesize($path) > 100000) {
            return [];
        }
        $document = json_decode(file_get_contents($path), true);

        return is_array($document) ? $document : [];
    }
}
