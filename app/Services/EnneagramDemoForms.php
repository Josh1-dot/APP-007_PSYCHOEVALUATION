<?php

namespace App\Services;

use App\Models\AssessmentDefinition;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnneagramDemoForms
{
    /** @return Collection<int, AssessmentDefinition> */
    public function create(Tenant $tenant, User $author): Collection
    {
        abort_unless(app()->environment(['local', 'testing']) && $author->tenant_id === $tenant->id, 403);

        return DB::transaction(function () use ($tenant, $author): Collection {
            $family = (string) Str::uuid();
            $forms = collect();
            $examples = [
                'A' => ['organiser un atelier', 'préparer un échange', 'achever une activité', 'créer une illustration', 'explorer un sujet', 'vérifier un itinéraire', 'imaginer une sortie', 'présenter une proposition', 'écouter un groupe'],
                'B' => ['planifier une promenade', 'accueillir un visiteur', 'terminer un puzzle', 'inventer une histoire', 'lire un article', 'préparer un trajet', 'découvrir un jeu', 'expliquer une idée', 'partager un moment calme'],
                'C' => ['ranger du matériel', 'aider à préparer une fête', 'finir une recette', 'dessiner une affiche', 'examiner une carte', 'relire une liste', 'proposer une activité', 'animer une discussion', 'observer une conversation'],
            ];
            foreach ($examples as $key => $labels) {
                $questions = [];
                $rules = ['method_version' => EnneagramScoring::ENGINE_VERSION, 'dimensions' => EnneagramScoring::DIMENSIONS, 'items' => []];
                foreach (EnneagramScoring::DIMENSIONS as $index => $dimension) {
                    $id = strtolower($key).'_item'.($index + 1);
                    $questions[] = ['id' => $id, 'item_key' => 'app007.demo.'.$key.'.'.($index + 1), 'item_version' => 1, 'language' => 'fr', 'provenance' => 'Création synthétique originale APP-007 Feature 021 ; démonstration technique, non validée.', 'label' => 'DEMO : imaginez '.$labels[$index].'. Indiquez votre intérêt fictif de 1 à 5.', 'type' => 'scale', 'min' => 1, 'max' => 5, 'required' => true];
                    $map = [];
                    foreach (range(1, 5) as $answer) {
                        $map[$answer] = [$dimension => ($answer - 1) * 25];
                    }
                    $rules['items'][$id] = ['dimension_weights' => [$dimension => 1], 'score_map' => $map, 'reverse' => false];
                }
                $definition = new AssessmentDefinition(['tenant_id' => $tenant->id, 'family' => $family, 'name' => 'Ennéagramme DEMO · forme '.$key, 'kind' => 'enneagramme', 'version' => count($forms) + 1, 'engine_version' => EnneagramScoring::ENGINE_VERSION, 'questions' => $questions, 'form_key' => $key, 'scoring_rules' => $rules, 'content_status' => 'DEMO', 'is_demo' => true, 'licensed' => false, 'source_reference' => 'Exemples synthétiques originaux : app/Services/EnneagramDemoForms.php ; aucune source psychométrique.', 'created_by' => $author->id]);
                app(EnneagramScoring::class)->validateDefinition($definition);
                $definition->save();
                $forms->push($definition);
            }

            return $forms;
        });
    }
}
