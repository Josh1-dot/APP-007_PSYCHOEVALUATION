<?php

namespace App\Services;

class PatientPublishedResultFormatter
{
    public function format(PatientPublishedResultData $data): string
    {
        if (! $data->available) {
            return 'Aucun résultat publié accessible ne peut être présenté pour cette demande. Indiquez l’UUID d’une évaluation accessible ; je ne peux pas utiliser une information non publiée.';
        }
        $lines = ['Résultat publié — faits fournis par Laravel :', $data->questionnaireName.' — Version '.$data->definitionVersion, 'Publication : '.$data->publishedAt];
        if ($data->isDemo) {
            $lines[] = 'Questionnaire de démonstration : il ne constitue pas un instrument psychométrique validé.';
        }
        if ($data->scores !== []) {
            $lines[] = $data->method;
            foreach ($data->scores as $dimension => $score) {
                $lines[] = $dimension.' : '.json_encode($score, JSON_PRESERVE_ZERO_FRACTION).' / '.json_encode($data->maximum, JSON_PRESERVE_ZERO_FRACTION);
            }
        } else {
            $lines[] = 'Aucun score automatique présenté.';
        }
        $lines[] = $data->isExcerpt ? 'Restitution publiée — extrait, texte complet sur la page de l’évaluation :' : 'Restitution publiée :';
        $lines[] = $data->publishedText;
        $lines[] = 'Explication PatientAI — descriptive :';
        $lines[] = 'Les valeurs et le texte ci-dessus proviennent de la publication accessible. Une valeur après « / » est le maximum affiché par la plateforme. Cette explication ne recalcule aucun score, ne pose aucun diagnostic et ne constitue ni une nouvelle interprétation clinique ni une validation professionnelle supplémentaire. Pour le sens clinique de la restitution, échangez avec votre professionnel.';
        $lines[] = $data->url;

        return implode("\n", $lines);
    }
}
