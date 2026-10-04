<?php

namespace App\Services;

use App\Models\AssessmentDefinition;
use Illuminate\Validation\ValidationException;

class EnneagramScoring
{
    public const ENGINE_VERSION = 'enneagramme-weighted-v1';

    public const DIMENSIONS = ['type1', 'type2', 'type3', 'type4', 'type5', 'type6', 'type7', 'type8', 'type9'];

    public function validateDefinition(AssessmentDefinition $definition): void
    {
        $questions = $definition->questions;
        $rules = $definition->scoring_rules;
        if (! is_array($questions) || ! array_is_list($questions) || count($questions) < count(self::DIMENSIONS) || count($questions) > 250) {
            throw ValidationException::withMessages(['questions' => 'La forme doit contenir entre 9 et 250 items versionnés.']);
        }
        if ($definition->engine_version !== self::ENGINE_VERSION || ! is_array($rules)
            || ($rules['method_version'] ?? null) !== self::ENGINE_VERSION
            || ! is_array($rules['dimensions'] ?? null)
            || array_values($rules['dimensions']) !== self::DIMENSIONS
            || ! is_array($rules['items'] ?? null)
            || array_diff(array_keys($rules), ['method_version', 'dimensions', 'items']) !== []) {
            throw ValidationException::withMessages(['scoring_rules' => 'La version des règles et les neuf dimensions sont obligatoires.']);
        }
        if (! is_string($definition->form_key) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,39}$/D', $definition->form_key)) {
            throw ValidationException::withMessages(['form_key' => 'Indiquez une clé de forme stable.']);
        }

        $questionIds = [];
        $itemKeys = [];
        $coveredDimensions = [];
        foreach ($questions as $question) {
            if (! is_array($question)) {
                throw ValidationException::withMessages(['questions' => 'Chaque item doit être un objet de définition.']);
            }
            $questionId = $question['id'] ?? null;
            $itemKey = $question['item_key'] ?? null;
            if (! is_string($questionId) || ! preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,39}$/D', $questionId)
                || ! is_string($itemKey) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,79}$/D', $itemKey)
                || ! is_int($question['item_version'] ?? null) || $question['item_version'] < 1
                || ! in_array($question['language'] ?? null, ['fr', 'en'], true)
                || ! is_string($question['provenance'] ?? null) || trim($question['provenance']) === '' || mb_strlen($question['provenance']) > 500
                || ! is_string($question['label'] ?? null) || trim($question['label']) === '' || mb_strlen($question['label']) > 1000
                || ! in_array($question['type'] ?? null, ['scale', 'choice', 'boolean'], true)) {
                throw ValidationException::withMessages(['questions' => 'Chaque item nécessite ID, item_key, version, langue, provenance, texte et type autorisés.']);
            }
            if (in_array($questionId, $questionIds, true) || in_array($itemKey, $itemKeys, true)) {
                throw ValidationException::withMessages(['questions' => 'Les IDs et item_key doivent être uniques dans la forme.']);
            }
            $questionIds[] = $questionId;
            $itemKeys[] = $itemKey;

            if ($question['type'] === 'scale' && (! is_int($question['min'] ?? null) || ! is_int($question['max'] ?? null) || $question['min'] < 0 || $question['max'] > 100 || $question['min'] >= $question['max'])) {
                throw ValidationException::withMessages(['questions' => 'Les bornes de l’échelle doivent être entières entre 0 et 100.']);
            }
            if ($question['type'] === 'choice' && (! is_array($question['options'] ?? null) || ! array_is_list($question['options']) || count($question['options']) < 2 || count($question['options']) > 30 || count(array_unique($question['options'], SORT_REGULAR)) !== count($question['options']) || collect($question['options'])->contains(fn (mixed $option): bool => ! is_string($option) || trim($option) === '' || mb_strlen($option) > 300))) {
                throw ValidationException::withMessages(['questions' => 'Les options doivent être des textes uniques et bornés.']);
            }
            $possibleAnswers = $this->possibleAnswers($question);
            $rule = $rules['items'][$questionId] ?? null;
            if (! is_array($rule) || array_diff(array_keys($rule), ['dimension_weights', 'score_map', 'reverse']) !== []
                || ! is_array($rule['dimension_weights'] ?? null) || $rule['dimension_weights'] === []
                || ! is_array($rule['score_map'] ?? null)
                || (array_key_exists('reverse', $rule) && ! is_bool($rule['reverse']))
                || (($rule['reverse'] ?? false) && $question['type'] !== 'scale')) {
                throw ValidationException::withMessages(['scoring_rules' => 'Chaque item doit posséder une règle de score valide.']);
            }
            if ($question['type'] === 'scale' && (! is_int($question['min'] ?? null) || ! is_int($question['max'] ?? null) || $question['min'] >= $question['max'])) {
                throw ValidationException::withMessages(['questions' => 'Une échelle doit définir des bornes entières croissantes.']);
            }
            if ($question['type'] === 'choice' && (! is_array($question['options'] ?? null) || count($question['options']) < 2 || count($question['options']) > 30)) {
                throw ValidationException::withMessages(['questions' => 'Un item à choix doit définir de deux à trente options.']);
            }
            $mapAnswers = array_map('strval', array_keys($rule['score_map']));
            sort($possibleAnswers);
            sort($mapAnswers);
            if ($possibleAnswers !== $mapAnswers) {
                throw ValidationException::withMessages(['scoring_rules' => 'La score_map doit couvrir exactement les réponses permises par chaque item.']);
            }
            $weightDimensions = array_keys($rule['dimension_weights']);
            sort($weightDimensions);
            if (array_diff($weightDimensions, self::DIMENSIONS) !== []) {
                throw ValidationException::withMessages(['scoring_rules' => 'Une règle utilise une dimension Ennéagramme inconnue.']);
            }
            foreach ($rule['dimension_weights'] as $dimension => $weight) {
                if (! $this->positiveFinite($weight) || $weight > 100) {
                    throw ValidationException::withMessages(['scoring_rules' => 'Les poids doivent être finis, positifs et au plus égaux à 100.']);
                }
                $coveredDimensions[$dimension] = true;
            }
            foreach ($rule['score_map'] as $points) {
                if (! is_array($points) || array_diff(array_keys($points), $weightDimensions) !== [] || array_diff($weightDimensions, array_keys($points)) !== []) {
                    throw ValidationException::withMessages(['scoring_rules' => 'Chaque réponse doit fournir un score pour chaque dimension pondérée, sans dimension supplémentaire.']);
                }
                foreach ($points as $point) {
                    if (! $this->finite($point) || $point < 0 || $point > 100) {
                        throw ValidationException::withMessages(['scoring_rules' => 'Les points configurés doivent être finis entre 0 et 100.']);
                    }
                }
            }
        }
        if (array_diff(array_keys($rules['items']), $questionIds) !== [] || array_diff($questionIds, array_keys($rules['items'])) !== []) {
            throw ValidationException::withMessages(['scoring_rules' => 'Les règles doivent correspondre exactement aux items du snapshot.']);
        }
        if (array_diff(self::DIMENSIONS, array_keys($coveredDimensions)) !== []) {
            throw ValidationException::withMessages(['scoring_rules' => 'La forme et ses règles doivent couvrir les neuf dimensions.']);
        }
    }

    /** @param array<string, mixed> $answers @return array<string, mixed> */
    public function calculate(AssessmentDefinition $definition, array $answers): array
    {
        $this->validateDefinition($definition);
        $answers = app(Scoring::class)->validate($definition, $answers, true);
        $rules = $definition->scoring_rules;
        $totals = array_fill_keys(self::DIMENSIONS, 0.0);
        $weights = array_fill_keys(self::DIMENSIONS, 0.0);

        foreach ($definition->questions as $question) {
            $questionId = $question['id'];
            if (! array_key_exists($questionId, $answers)) {
                throw ValidationException::withMessages(['answers' => 'Toutes les réponses requises sont nécessaires pour calculer le résultat.']);
            }
            $answerKey = is_bool($answers[$questionId]) ? ($answers[$questionId] ? '1' : '0') : (string) $answers[$questionId];
            $rule = $rules['items'][$questionId];
            $points = $rule['score_map'][$answerKey] ?? null;
            if (! is_array($points)) {
                throw ValidationException::withMessages(['answers' => 'Une réponse ne correspond pas à la configuration de scoring de cette version.']);
            }
            foreach ($rule['dimension_weights'] as $dimension => $weight) {
                $value = (float) $points[$dimension];
                if ($rule['reverse'] ?? false) {
                    $value = 100 - $value;
                }
                $totals[$dimension] += $value * (float) $weight;
                $weights[$dimension] += (float) $weight;
            }
        }
        foreach (self::DIMENSIONS as $dimension) {
            if ($weights[$dimension] <= 0) {
                throw ValidationException::withMessages(['scoring_rules' => 'Une dimension ne possède aucune contribution.']);
            }
        }
        $scores = [];
        foreach (self::DIMENSIONS as $dimension) {
            $scores[$dimension] = round($totals[$dimension] / $weights[$dimension], 2);
        }
        $maximumScore = max($scores);
        $topDimensions = array_values(array_filter(self::DIMENSIONS, fn (string $dimension): bool => $scores[$dimension] === $maximumScore));

        return [
            'kind' => 'enneagramme',
            'scores' => $scores,
            'maximum' => 100,
            'method' => 'Moyenne pondérée configurée, normalisée sur 100 ; résultat non diagnostique.',
            'engine' => self::ENGINE_VERSION,
            'method_version' => $rules['method_version'],
            'definition_version' => (int) $definition->version,
            'form_key' => $definition->form_key,
            'content_status' => $definition->content_status,
            'top_dimensions' => $topDimensions,
            'is_tie' => count($topDimensions) > 1,
        ];
    }

    /** @param array<string, mixed> $question @return list<string> */
    private function possibleAnswers(array $question): array
    {
        return match ($question['type'] ?? null) {
            'scale' => isset($question['min'], $question['max']) && is_int($question['min']) && is_int($question['max']) && $question['min'] <= $question['max']
                ? array_map('strval', range($question['min'], $question['max'])) : [],
            'choice' => is_array($question['options'] ?? null) ? array_map('strval', $question['options']) : [],
            'boolean' => ['0', '1'],
            default => [],
        };
    }

    private function positiveFinite(mixed $value): bool
    {
        return $this->finite($value) && $value > 0;
    }

    private function finite(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value);
    }
}
