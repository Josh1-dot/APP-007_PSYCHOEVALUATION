<?php

namespace App\Services;

class PatientAssessmentFormatter
{
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
