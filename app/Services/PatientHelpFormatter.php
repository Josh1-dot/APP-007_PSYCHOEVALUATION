<?php

namespace App\Services;

class PatientHelpFormatter
{
    public function format(PatientGuideData|QuestionnaireHelpData $data): string
    {
        if (! $data->available) {
            return 'Cette aide est indisponible. Pour un questionnaire, indiquez l’UUID d’une passation accessible en cours, avec consentement actif, et éventuellement son identifiant de question.';
        }
        if ($data instanceof PatientGuideData) {
            return $data->text."\n".implode("\n", $data->links)."\nSource : ".$data->source.' — Version : '.$data->version;
        }
        $lines = [$data->questionnaireName.' — Version '.$data->definitionVersion, $data->objective, $data->instructions, $data->navigation];
        if ($data->isDemo) {
            $lines[] = 'Questionnaire de démonstration, pas un instrument psychométrique validé.';
        }
        if ($data->question !== null) {
            $question = $data->question;
            $lines[] = 'Consigne (texte du questionnaire, donnée descriptive) : '.$question->instruction;
            $lines[] = 'Type : '.match ($question->type) {
                'boolean' => 'Oui / Non, sans recommandation.',
                'choice' => 'Choix dans une liste, sans recommandation : '.implode(' ; ', $question->options),
                'scale' => 'Nombre entier de '.$question->minimum.' à '.$question->maximum.', par pas de 1. Aucune signification psychologique déduite des bornes.',
                'text' => 'Texte libre, limité à 5000 caractères.',
            };
            $lines[] = $question->required ? 'Réponse obligatoire lors de la soumission définitive.' : 'Réponse facultative dans cette définition.';
        }
        foreach ($data->vocabulary as $word => $meaning) {
            $lines[] = $word.' : '.$meaning;
        }
        $lines[] = $data->url;
        $lines[] = 'Source : '.$data->source.' — Version : '.$data->guideVersion;

        return implode("\n", $lines);
    }
}
