<?php

namespace App\Services;

use App\Models\AssessmentDefinition;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Scoring
{
    public function validate(AssessmentDefinition $definition, array $answers, bool $complete): array
    {
        $rules = [];
        $ids = [];
        foreach ($definition->questions as $q) {
            $id = (string) $q['id'];
            if ($definition->engine_version === EnneagramScoring::ENGINE_VERSION && $q['type'] === 'scale' && isset($answers[$id]) && (is_bool($answers[$id]) || ! (is_int($answers[$id]) || is_string($answers[$id])))) {
                throw ValidationException::withMessages(['answers' => 'Une échelle nécessite une réponse entière.']);
            }
            $ids[] = $id;
            $base = ($complete && (in_array($definition->kind, ['gordon', 'enneagramme']) || ($q['required'] ?? true))) ? 'required' : 'nullable';
            $rules[$id] = match ($q['type']) {
                'boolean' => [$base, 'boolean'],
                'scale' => [$base, 'integer', 'min:'.$q['min'], 'max:'.$q['max']],
                'choice' => [$base, Rule::in($q['options'])],
                default => [$base, 'string', 'max:5000'],
            };
        }
        if (array_diff(array_keys($answers), $ids)) {
            throw ValidationException::withMessages(['answers' => 'Réponse inconnue dans cette version.']);
        }
        $valid = Validator::make($answers, $rules)->validate();
        foreach ($definition->questions as $q) {
            if (isset($valid[$q['id']])) {
                if ($q['type'] === 'boolean') {
                    $valid[$q['id']] = filter_var($valid[$q['id']], FILTER_VALIDATE_BOOLEAN);
                }
                if ($q['type'] === 'scale') {
                    $valid[$q['id']] = (int) $valid[$q['id']];
                }
            }
        }

        return $valid;
    }

    public function calculate(AssessmentDefinition $d, array $answers): array
    {
        if ($d->kind === 'gordon') {
            if ($d->engine_version !== 'gordon-v1') {
                throw ValidationException::withMessages(['definition' => 'Version de moteur non prise en charge.']);
            }
            $scores = array_fill_keys(['A', 'B', 'C', 'D'], 0);
            $counts = $scores;
            foreach ($d->questions as $q) {
                if (! isset($counts[$q['dimension'] ?? '']) || $q['type'] !== 'boolean') {
                    throw ValidationException::withMessages(['definition' => 'Grille Gordon invalide.']);
                }
                $counts[$q['dimension']]++;
                if (($answers[$q['id']] ?? false) === true) {
                    $scores[$q['dimension']]++;
                }
            }
            if (count($d->questions) !== 60 || count(array_filter($counts, fn ($n) => $n === 15)) !== 4) {
                throw ValidationException::withMessages(['definition' => 'Gordon exige 60 questions et 15 questions par dimension.']);
            }

            return ['kind' => 'gordon', 'scores' => $scores, 'maximum' => 15, 'engine' => $d->engine_version, 'definition_version' => $d->version];
        }
        if ($d->kind === 'enneagramme') {
            if ($d->engine_version === 'self-report-v1') {
                return ['kind' => 'enneagramme', 'scores' => $answers, 'maximum' => 100, 'method' => 'Pourcentages auto-déclarés, sans score clinique', 'engine' => 'self-report-v1', 'definition_version' => $d->version];
            }
            if ($d->engine_version !== EnneagramScoring::ENGINE_VERSION) {
                throw ValidationException::withMessages(['definition' => 'Version de moteur Ennéagramme non prise en charge.']);
            }

            return app(EnneagramScoring::class)->calculate($d, $answers);
        }

        return ['kind' => $d->kind, 'answers' => $answers, 'engine' => 'raw-v1', 'definition_version' => $d->version];
    }
}
