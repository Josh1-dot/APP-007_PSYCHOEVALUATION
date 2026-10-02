<?php

namespace App\Services;

class PatientAssessmentFormatter
{
    public function conversationData(PatientAssessmentResult $result): PatientAssessmentConversationResult
    {
        $items = array_map(fn (PatientAssessmentData $item): PatientAssessmentConversationData => new PatientAssessmentConversationData($item->questionnaireName, $item->statusLabel, $item->url), $result->items);

        return new PatientAssessmentConversationResult($items, $result->hasMore, $result->available);
    }

    public function formatConversation(PatientAssessmentConversationResult $result): string
    {
        if (! $result->available) {
            return 'Cette évaluation n’est pas disponible dans votre espace.';
        }
        if ($result->items === []) {
            return 'Aucune évaluation correspondante n’est actuellement disponible dans votre espace.';
        }
        $lines = array_map(fn (PatientAssessmentConversationData $item): string => $item->questionnaireName.' — '.$item->statusLabel.' — '.$item->url, $result->items);
        if ($result->hasMore) {
            $lines[] = 'D’autres évaluations sont disponibles dans votre espace patient.';
        }

        return implode("\n", $lines);
    }

    public function format(PatientAssessmentResult $result): string
    {
        if (! $result->available) {
            return 'Cette évaluation est indisponible. Indiquez un UUID valide d’une évaluation accessible dans votre espace patient.';
        }
        if ($result->items === []) {
            return 'Aucune évaluation accessible ne correspond à cette demande.';
        }
        $lines = [];
        foreach ($result->items as $item) {
            $lines[] = $item->questionnaireName.' — '.$item->statusLabel.' — UUID : '.$item->uuid.' — '.$item->url;
        }
        if ($result->hasMore) {
            $lines[] = 'D’autres évaluations sont disponibles. Consultez votre espace patient pour la liste complète.';
        }

        return implode("\n", $lines);
    }
}
