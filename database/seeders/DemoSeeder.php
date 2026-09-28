<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\AssessmentDefinition;
use App\Models\Client;
use App\Models\Consent;
use App\Models\Interpretation;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Scoring;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Démonstration interdite hors environnement local/test.');
        }
        if (User::where('email', 'admin@demo.test')->exists()) {
            throw new \RuntimeException('La démonstration existe déjà. Aucune donnée modifiée.');
        }
        $password = env('DEMO_PASSWORD');
        if (! is_string($password) || strlen($password) < 12) {
            throw new \RuntimeException('Définissez DEMO_PASSWORD (12 caractères minimum).');
        }
        $tenant = Tenant::create(['name' => 'Cabinet Horizon · Démo', 'email' => 'contact@demo.test', 'address' => 'Espace de démonstration — données fictives']);
        $admin = User::create(['tenant_id' => $tenant->id, 'name' => 'Camille Martin', 'email' => 'admin@demo.test', 'password' => $password, 'role' => 'admin']);
        $org = Organization::create(['tenant_id' => $tenant->id, 'name' => 'Atelier Collectif · Démo', 'email' => 'entreprise@demo.test']);
        User::create(['tenant_id' => $tenant->id, 'name' => 'Organisation Démo', 'email' => 'entreprise@demo.test', 'password' => $password, 'role' => 'entreprise', 'organization_id' => $org->id]);
        User::create(['tenant_id' => $tenant->id, 'name' => 'Conseiller Démo', 'email' => 'conseiller@demo.test', 'password' => $password, 'role' => 'conseiller']);
        $needs = [];
        foreach (['Vie quotidienne', 'Relations', 'Activité professionnelle', 'Ressources personnelles', 'Attentes du suivi'] as $i => $label) {
            $needs[] = ['id' => 'domaine'.($i + 1), 'label' => $label.' : quels sont vos besoins ?', 'type' => 'text', 'required' => true];
        }
        foreach (['Clarifier mes priorités', 'Exprimer mes besoins', 'Prendre du temps pour moi', 'Préparer un changement', 'Trouver un rythme adapté', 'Organiser mon quotidien', 'Poser mes limites', 'Demander du soutien', 'Échanger avec mes proches', 'Préparer une discussion', 'Identifier mes ressources', 'Définir mes objectifs', 'Faire une pause', 'Prendre une décision', 'Comprendre mon ressenti', 'Planifier une activité', 'Valoriser mes progrès', 'Exprimer un désaccord', 'Améliorer ma concentration', 'Trouver une activité ressourçante', 'Préparer ma prochaine étape', 'Faire le point sur mon parcours'] as $i => $label) {
            $needs[] = ['id' => 'situation'.($i + 1), 'label' => $label.' : niveau de besoin (0 = aucun, 3 = important)', 'type' => 'scale', 'min' => 0, 'max' => 3, 'required' => true];
        }
        $besoins = AssessmentDefinition::create(['tenant_id' => $tenant->id, 'family' => (string) Str::uuid(), 'name' => 'Besoins & accompagnement', 'kind' => 'besoins', 'version' => 1, 'engine_version' => 'raw-v1', 'questions' => $needs, 'is_demo' => true]);
        $questions = [];
        for ($i = 1; $i <= 9; $i++) {
            $questions[] = ['id' => 'type'.$i, 'label' => 'Type '.$i.' : votre pourcentage auto-déclaré après échange avec le professionnel', 'type' => 'scale', 'min' => 0, 'max' => 100, 'required' => true];
        }
        $ennea = AssessmentDefinition::create(['tenant_id' => $tenant->id, 'family' => (string) Str::uuid(), 'name' => 'Ennéagramme · auto-évaluation', 'kind' => 'enneagramme', 'version' => 1, 'engine_version' => 'self-report-v1', 'questions' => $questions, 'is_demo' => true]);
        $custom = AssessmentDefinition::create(['tenant_id' => $tenant->id, 'family' => (string) Str::uuid(), 'name' => 'Point de parcours', 'kind' => 'personnalise', 'version' => 1, 'engine_version' => 'raw-v1', 'questions' => [['id' => 'ressenti', 'label' => 'Comment abordez-vous cette nouvelle étape ?', 'type' => 'text', 'required' => true], ['id' => 'energie', 'label' => 'Votre niveau d’énergie aujourd’hui', 'type' => 'scale', 'min' => 0, 'max' => 10, 'required' => true], ['id' => 'soutien', 'label' => 'Quel format vous conviendrait ?', 'type' => 'choice', 'options' => ['Échange individuel', 'Atelier collectif', 'Temps de réflexion'], 'required' => true]], 'is_demo' => true]);
        $names = [['Léa', 'Bernard'], ['Thomas', 'Petit'], ['Inès', 'Robert'], ['Lucas', 'Moreau'], ['Emma', 'Laurent'], ['Hugo', 'Simon'], ['Jade', 'Michel'], ['Louis', 'Roux']];
        foreach ($names as $i => [$first,$last]) {
            $u = $i === 0 ? User::create(['tenant_id' => $tenant->id, 'name' => 'Léa Bernard', 'email' => 'patient@demo.test', 'password' => $password, 'role' => 'patient']) : null;
            $c = Client::create(['tenant_id' => $tenant->id, 'user_id' => $u?->id, 'organization_id' => $i % 2 === 0 ? $org->id : null, 'first_name' => $first, 'last_name' => $last, 'email' => $u?->email ?? 'patient'.($i + 1).'@demo.test', 'reason' => 'Dossier fictif de démonstration.']);
            if ($i !== 0) {
                Consent::create(['tenant_id' => $tenant->id, 'client_id' => $c->id, 'version' => config('psycho.consent_version'), 'text' => 'Consentement fictif de démonstration.', 'accepted_at' => now()->subDays(4)]);
            }
            $d = [$besoins, $ennea, $custom][$i % 3];
            $status = ['en_cours', 'termine', 'publie', 'en_cours', 'publie', 'termine', 'en_cours', 'publie'][$i];
            $answers = null;
            $results = null;
            if ($status !== 'en_cours') {
                $answers = [];
                foreach ($d->questions as $q) {
                    $answers[$q['id']] = match ($q['type']) {
                        'scale' => min($q['max'], ($i + 1) * 2),'choice' => $q['options'][0],default => 'Réponse fictive pour explorer le parcours.'
                    };
                }$results = app(Scoring::class)->calculate($d, $answers);
            }
            $a = Assessment::create(['tenant_id' => $tenant->id, 'client_id' => $c->id, 'assessment_definition_id' => $d->id, 'assigned_by' => $admin->id, 'status' => $status, 'answers' => $answers, 'results' => $results, 'due_at' => now()->addDays($i + 3), 'submitted_at' => $status === 'en_cours' ? null : now()->subDays(1), 'created_at' => now()->subDays($i + 1)]);
            if ($status === 'publie') {
                Interpretation::create(['tenant_id' => $tenant->id, 'assessment_id' => $a->id, 'draft' => '## Exemple de restitution\nCe contenu fictif permet de découvrir le parcours de publication. Il ne constitue pas une interprétation clinique.', 'published_content' => "## Exemple de restitution\nCe contenu fictif permet de découvrir le parcours de publication. Il ne constitue pas une interprétation clinique.", 'reviewed_by' => $admin->id, 'published_at' => now()]);
            }
            if ($i < 4) {
                Appointment::create(['tenant_id' => $tenant->id, 'client_id' => $c->id, 'title' => ['Entretien de suivi', 'Première rencontre', 'Restitution de parcours', 'Séance de suivi'][$i], 'starts_at' => now()->addDays($i)->setTime(14 + $i, 0), 'duration' => 45, 'location' => 'Cabinet']);
            }
        }
    }
}
