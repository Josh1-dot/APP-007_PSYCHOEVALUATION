<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentDefinition;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EnneagramFormRotation;
use App\Services\EnneagramScoring;
use App\Services\Scoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnneagramAssessmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *     0: AssessmentDefinition,
     *     1: list<array<string, mixed>>
     * }
     */
    private function createDemoForm(
        Tenant $tenant,
        string $family,
        string $formKey,
        int $version
    ): array {
        $dimensions = EnneagramScoring::DIMENSIONS;

        $questions = [];

        $rules = [
            'method_version' => EnneagramScoring::ENGINE_VERSION,
            'dimensions' => $dimensions,
            'items' => [],
        ];

        foreach ($dimensions as $index => $dimension) {
            $questionId = strtolower($formKey).'_item'.($index + 1);

            $questions[] = [
                'id' => $questionId,
                'item_key' => 'demo_'.$formKey.'_'.$dimension,
                'item_version' => $version,
                'language' => 'fr',
                'provenance' => 'Fixture synthétique de test, non validée',
                'label' => 'Question '.$formKey.' '.$dimension,
                'type' => 'scale',
                'min' => 1,
                'max' => 5,
                'required' => true,
            ];

            $rules['items'][$questionId] = [
                'dimension_weights' => [
                    $dimension => 1,
                ],
                'score_map' => [
                    '1' => [$dimension => 0],
                    '2' => [$dimension => 25],
                    '3' => [$dimension => 50],
                    '4' => [$dimension => 75],
                    '5' => [$dimension => 100],
                ],
                'reverse' => false,
            ];
        }

        $definition = AssessmentDefinition::create([
            'tenant_id' => $tenant->id,
            'family' => $family,
            'name' => 'Ennéagramme DEMO forme '.$formKey,
            'kind' => 'enneagramme',
            'version' => $version,
            'engine_version' => EnneagramScoring::ENGINE_VERSION,
            'questions' => $questions,
            'form_key' => $formKey,
            'scoring_rules' => $rules,
            'content_status' => 'DEMO',
            'is_demo' => true,
        ]);

        return [$definition, $questions];
    }

    private function createTenant(string $suffix): Tenant
    {
        return Tenant::create([
            'name' => 'Cabinet Ennéagramme '.$suffix,
            'slug' => 'cabinet-enneagramme-'.Str::lower($suffix).'-'.Str::random(8),
        ]);
    }

    private function createClient(Tenant $tenant, string $suffix): Client
    {
        return Client::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Patient',
            'last_name' => $suffix,
            'email' => 'patient-'
                .Str::lower(Str::slug($suffix))
                .'-'
                .Str::lower(Str::random(8))
                .'@example.test',
        ]);
    }

    private function createAssessment(
        Tenant $tenant,
        Client $client,
        AssessmentDefinition $definition,
        string $status = 'termine'
    ): Assessment {
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        return Assessment::create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'assigned_by' => $admin->id,
            'assessment_definition_id' => $definition->id,
            'status' => $status,
        ]);
    }

    public function test_weighted_engine_is_reproducible_and_reports_all_tied_dimensions(): void
    {
        $dimensions = array_map(
            fn (int $number): string => 'type'.$number,
            range(1, 9)
        );

        $questions = [];

        $rules = [
            'method_version' => EnneagramScoring::ENGINE_VERSION,
            'dimensions' => $dimensions,
            'items' => [],
        ];

        $answers = [];

        foreach ($dimensions as $index => $dimension) {
            $questionId = 'item'.($index + 1);

            $questions[] = [
                'id' => $questionId,
                'item_key' => 'stable-'.$questionId,
                'item_version' => 1,
                'language' => 'fr',
                'provenance' => 'Fixture de test',
                'label' => 'Item '.$questionId,
                'type' => 'scale',
                'min' => 1,
                'max' => 5,
                'required' => true,
            ];

            $rules['items'][$questionId] = [
                'dimension_weights' => [
                    $dimension => 1,
                ],
                'score_map' => [
                    '1' => [$dimension => 0],
                    '2' => [$dimension => 25],
                    '3' => [$dimension => 50],
                    '4' => [$dimension => 75],
                    '5' => [$dimension => 100],
                ],
                'reverse' => false,
            ];

            $answers[$questionId] = 3;
        }

        $definition = new AssessmentDefinition([
            'kind' => 'enneagramme',
            'engine_version' => EnneagramScoring::ENGINE_VERSION,
            'version' => 2,
            'form_key' => 'A',
            'content_status' => 'DEMO',
            'questions' => $questions,
            'scoring_rules' => $rules,
        ]);

        $first = app(Scoring::class)->calculate(
            $definition,
            $answers
        );

        $second = app(Scoring::class)->calculate(
            $definition,
            $answers
        );

        $this->assertSame($first, $second);

        $this->assertSame(
            array_fill_keys($dimensions, 50.0),
            $first['scores']
        );

        $this->assertSame(
            $dimensions,
            $first['top_dimensions']
        );

        $this->assertTrue($first['is_tie']);
        $this->assertSame(100, $first['maximum']);

        $this->assertSame(
            EnneagramScoring::ENGINE_VERSION,
            $first['engine']
        );

        $this->assertSame(
            2,
            $first['definition_version']
        );

        $this->assertSame(
            'A',
            $first['form_key']
        );

        $this->assertSame(
            'DEMO',
            $first['content_status']
        );
    }

    public function test_rotation_uses_unused_forms_before_reusing_a_form(): void
    {
        $tenant = $this->createTenant('rotation');

        $client = $this->createClient(
            $tenant,
            'Rotation'
        );

        $family = (string) Str::uuid();

        [$formA] = $this->createDemoForm(
            $tenant,
            $family,
            'A',
            1
        );

        [$formB] = $this->createDemoForm(
            $tenant,
            $family,
            'B',
            2
        );

        [$formC] = $this->createDemoForm(
            $tenant,
            $family,
            'C',
            3
        );

        $rotation = app(EnneagramFormRotation::class);

        /*
         * Aucune passation :
         * la sélection déterministe commence par A.
         */
        $selected = $rotation->selectForAssignment(
            $client,
            $formA
        );

        $this->assertSame(
            $formA->id,
            $selected->id
        );

        /*
         * A a déjà été utilisée.
         * La prochaine forme disponible doit être B.
         */
        $this->createAssessment(
            $tenant,
            $client,
            $formA
        );

        $selected = $rotation->selectForAssignment(
            $client,
            $formA
        );

        $this->assertSame(
            $formB->id,
            $selected->id
        );

        /*
         * A et B ont été utilisées.
         * C doit maintenant être choisie.
         */
        $this->createAssessment(
            $tenant,
            $client,
            $formB
        );

        $selected = $rotation->selectForAssignment(
            $client,
            $formA
        );

        $this->assertSame(
            $formC->id,
            $selected->id
        );
    }

    public function test_rotation_reuses_least_recently_used_form_after_pool_exhaustion(): void
    {
        $tenant = $this->createTenant('lru');

        $client = $this->createClient(
            $tenant,
            'LRU'
        );

        $family = (string) Str::uuid();

        [$formA] = $this->createDemoForm(
            $tenant,
            $family,
            'A',
            1
        );

        [$formB] = $this->createDemoForm(
            $tenant,
            $family,
            'B',
            2
        );

        [$formC] = $this->createDemoForm(
            $tenant,
            $family,
            'C',
            3
        );

        /*
         * On crée volontairement l'historique dans cet ordre :
         *
         * A = la plus ancienne utilisation
         * B = utilisation intermédiaire
         * C = la plus récente
         */
        $assessmentA = $this->createAssessment(
            $tenant,
            $client,
            $formA
        );

        $assessmentA->forceFill([
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ])->saveQuietly();

        $assessmentB = $this->createAssessment(
            $tenant,
            $client,
            $formB
        );

        $assessmentB->forceFill([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ])->saveQuietly();

        $assessmentC = $this->createAssessment(
            $tenant,
            $client,
            $formC
        );

        $assessmentC->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ])->saveQuietly();

        $selected = app(
            EnneagramFormRotation::class
        )->selectForAssignment(
            $client,
            $formA
        );

        /*
         * Toutes les formes ayant déjà été utilisées,
         * la moins récemment utilisée doit être A.
         */
        $this->assertSame(
            $formA->id,
            $selected->id
        );
    }

    public function test_rotation_history_is_independent_for_each_patient(): void
    {
        $tenant = $this->createTenant('patients');

        $clientOne = $this->createClient(
            $tenant,
            'Patient-One'
        );

        $clientTwo = $this->createClient(
            $tenant,
            'Patient-Two'
        );

        $family = (string) Str::uuid();

        [$formA] = $this->createDemoForm(
            $tenant,
            $family,
            'A',
            1
        );

        [$formB] = $this->createDemoForm(
            $tenant,
            $family,
            'B',
            2
        );

        $this->createDemoForm(
            $tenant,
            $family,
            'C',
            3
        );

        /*
         * Seul le patient 1 a déjà passé A.
         */
        $this->createAssessment(
            $tenant,
            $clientOne,
            $formA
        );

        $rotation = app(
            EnneagramFormRotation::class
        );

        $forClientOne = $rotation->selectForAssignment(
            $clientOne,
            $formA
        );

        $forClientTwo = $rotation->selectForAssignment(
            $clientTwo,
            $formA
        );

        /*
         * Patient 1 -> B
         * Patient 2 -> A
         *
         * L'historique d'un patient ne doit donc jamais
         * influencer celui d'un autre patient.
         */
        $this->assertSame(
            $formB->id,
            $forClientOne->id
        );

        $this->assertSame(
            $formA->id,
            $forClientTwo->id
        );
    }

    public function test_rotation_rejects_a_definition_from_another_tenant(): void
    {
        $tenantOne = $this->createTenant(
            'tenant-one'
        );

        $tenantTwo = $this->createTenant(
            'tenant-two'
        );

        $client = $this->createClient(
            $tenantOne,
            'Tenant-Isolation'
        );

        $family = (string) Str::uuid();

        [$foreignDefinition] = $this->createDemoForm(
            $tenantTwo,
            $family,
            'A',
            1
        );

        try {
            app(
                EnneagramFormRotation::class
            )->selectForAssignment(
                $client,
                $foreignDefinition
            );

            $this->fail(
                'Une définition appartenant à un autre tenant aurait dû être refusée.'
            );
        } catch (HttpException $exception) {
            $this->assertSame(
                404,
                $exception->getStatusCode()
            );
        }
    }

    public function test_rotation_rejects_draft_weighted_form(): void
    {
        $tenant = $this->createTenant('draft');

        $client = $this->createClient(
            $tenant,
            'Draft'
        );

        $family = (string) Str::uuid();

        [$definition] = $this->createDemoForm(
            $tenant,
            $family,
            'A',
            1
        );

        /*
         * On transforme uniquement le statut de la fixture.
         * Le contenu psychométrique lui-même n'est pas modifié.
         */
        $definition->forceFill([
            'content_status' => 'DRAFT',
        ])->saveQuietly();

        try {
            app(
                EnneagramFormRotation::class
            )->selectForAssignment(
                $client,
                $definition
            );

            $this->fail(
                'Une forme DRAFT ne doit jamais être assignable.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'assessment_definition_id',
                $exception->errors()
            );

            $this->assertSame(
                'Cette forme Ennéagramme n’est pas disponible à l’assignation.',
                $exception->errors()['assessment_definition_id'][0]
            );
        }
    }

    public function test_real_assignment_route_rotates_forms_and_records_authenticated_professional(): void
    {
        $tenant = $this->createTenant('controller');

        $professional = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $patient = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'patient',
        ]);

        $client = Client::create([
            'tenant_id' => $tenant->id,
            'user_id' => $patient->id,
            'first_name' => 'Patient',
            'last_name' => 'Controller',
            'email' => $patient->email,
        ]);

        $family = (string) Str::uuid();

        [$formA] = $this->createDemoForm(
            $tenant,
            $family,
            'A',
            1
        );

        [$formB] = $this->createDemoForm(
            $tenant,
            $family,
            'B',
            2
        );

        [$formC] = $this->createDemoForm(
            $tenant,
            $family,
            'C',
            3
        );

        /*
         * 1re assignation :
         * aucune forme utilisée -> A.
         */
        $response = $this
            ->actingAs($professional)
            ->post('/evaluations', [
                'client_id' => $client->id,
                'assessment_definition_id' => $formA->id,
            ]);

        $first = Assessment::query()
            ->where('client_id', $client->id)
            ->latest('id')
            ->firstOrFail();

        $response->assertRedirect(
            route('evaluations.show', $first)
        );

        $this->assertSame(
            $formA->id,
            $first->assessment_definition_id
        );

        $this->assertSame(
            $professional->id,
            $first->assigned_by
        );

        $this->assertSame(
            $tenant->id,
            $first->tenant_id
        );

        /*
         * 2e assignation :
         * A est encore envoyé comme ancre,
         * mais le serveur doit sélectionner B.
         */
        $response = $this
            ->actingAs($professional)
            ->post('/evaluations', [
                'client_id' => $client->id,
                'assessment_definition_id' => $formA->id,
            ]);

        $second = Assessment::query()
            ->where('client_id', $client->id)
            ->latest('id')
            ->firstOrFail();

        $response->assertRedirect(
            route('evaluations.show', $second)
        );

        $this->assertSame(
            $formB->id,
            $second->assessment_definition_id
        );

        $this->assertSame(
            $professional->id,
            $second->assigned_by
        );

        /*
         * 3e assignation :
         * A et B utilisées -> C.
         */
        $response = $this
            ->actingAs($professional)
            ->post('/evaluations', [
                'client_id' => $client->id,
                'assessment_definition_id' => $formA->id,
            ]);

        $third = Assessment::query()
            ->where('client_id', $client->id)
            ->latest('id')
            ->firstOrFail();

        $response->assertRedirect(
            route('evaluations.show', $third)
        );

        $this->assertSame(
            $formC->id,
            $third->assessment_definition_id
        );

        $this->assertSame(
            $professional->id,
            $third->assigned_by
        );

        /*
         * Historique réel du patient :
         * A -> B -> C.
         */
        $assignedDefinitionIds = Assessment::query()
            ->where('client_id', $client->id)
            ->orderBy('id')
            ->pluck('assessment_definition_id')
            ->all();

        $this->assertSame(
            [
                $formA->id,
                $formB->id,
                $formC->id,
            ],
            $assignedDefinitionIds
        );
    }

    public function test_feature021_demo_form_cannot_enter_approval_workflow(): void
    {
        $tenant = $this->createTenant('demo-workflow');

        $publisher = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        [$definition] = $this->createDemoForm(
            $tenant,
            (string) Str::uuid(),
            'A',
            1
        );

        $this->actingAs($publisher)
            ->post(
                route('questionnaires.enneagram.review', $definition),
                ['reviewed' => '1']
            )
            ->assertStatus(422);

        $definition->refresh();

        $this->assertSame('DEMO', $definition->content_status);
        $this->assertNull($definition->reviewed_by);
        $this->assertNull($definition->approved_by);
    }

    public function test_feature021_definition_workflow_requires_review_source_license_and_preserves_immutability(): void
    {
        $tenant = $this->createTenant('approval-workflow');

        $publisher = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        [$template] = $this->createDemoForm(
            $tenant,
            (string) Str::uuid(),
            'A',
            1
        );

        $definition = AssessmentDefinition::create([
            'tenant_id' => $tenant->id,
            'family' => (string) Str::uuid(),
            'name' => 'Ennéagramme source contrôlée',
            'kind' => 'enneagramme',
            'version' => 1,
            'engine_version' => EnneagramScoring::ENGINE_VERSION,
            'questions' => $template->questions,
            'scoring_rules' => $template->scoring_rules,
            'form_key' => 'A',
            'content_status' => 'DRAFT',
            'is_demo' => false,
            'licensed' => true,
            'source_reference' => 'SOURCE-TEST-021',
            'created_by' => $publisher->id,
        ]);

        /*
         * APPROVED ne peut jamais être atteint directement depuis DRAFT.
         */
        $this->actingAs($publisher)
            ->post(
                route('questionnaires.enneagram.approve', $definition),
                ['approved' => '1']
            )
            ->assertStatus(409);

        $definition->refresh();
        $this->assertSame('DRAFT', $definition->content_status);
        $this->assertNull($definition->approved_by);

        /*
         * DRAFT -> REVIEWED.
         */
        $this->post(
            route('questionnaires.enneagram.review', $definition),
            ['reviewed' => '1']
        )->assertRedirect();

        $definition->refresh();

        $this->assertSame('REVIEWED', $definition->content_status);
        $this->assertSame($publisher->id, $definition->reviewed_by);
        $this->assertNotNull($definition->reviewed_at);

        /*
         * REVIEWED -> APPROVED seulement avec provenance/licence.
         */
        $this->post(
            route('questionnaires.enneagram.approve', $definition),
            ['approved' => '1']
        )->assertRedirect();

        $definition->refresh();

        $this->assertSame('APPROVED', $definition->content_status);
        $this->assertSame($publisher->id, $definition->approved_by);
        $this->assertNotNull($definition->approved_at);

        /*
         * Le contenu d'une définition versionnée est immuable.
         */
        $response = $this
            ->actingAs($publisher)
            ->from('/questionnaires')
            ->put('/__feature021-immutability-probe', []);

        try {
            $definition->name = 'Modification interdite';
            $definition->save();

            $this->fail(
                'Une définition Ennéagramme versionnée ne doit pas pouvoir être modifiée.'
            );
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $definition->refresh();
        $this->assertSame('Ennéagramme source contrôlée', $definition->name);
    }

    public function test_feature021_approval_rejects_missing_license_or_source(): void
    {
        $tenant = $this->createTenant('missing-source');

        $publisher = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        [$template] = $this->createDemoForm(
            $tenant,
            (string) Str::uuid(),
            'A',
            1
        );

        $definition = AssessmentDefinition::create([
            'tenant_id' => $tenant->id,
            'family' => (string) Str::uuid(),
            'name' => 'Forme sans provenance',
            'kind' => 'enneagramme',
            'version' => 1,
            'engine_version' => EnneagramScoring::ENGINE_VERSION,
            'questions' => $template->questions,
            'scoring_rules' => $template->scoring_rules,
            'form_key' => 'A',
            'content_status' => 'REVIEWED',
            'is_demo' => false,
            'licensed' => false,
            'source_reference' => null,
            'created_by' => $publisher->id,
            'reviewed_by' => $publisher->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($publisher)
            ->post(
                route('questionnaires.enneagram.approve', $definition),
                ['approved' => '1']
            )
            ->assertStatus(422);

        $definition->refresh();

        $this->assertSame('REVIEWED', $definition->content_status);
        $this->assertNull($definition->approved_by);
        $this->assertNull($definition->approved_at);
    }

    public function test_feature021_review_and_approval_are_audited(): void
    {
        $tenant = $this->createTenant('audit-workflow');

        $publisher = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        [$template] = $this->createDemoForm(
            $tenant,
            (string) Str::uuid(),
            'A',
            1
        );

        $definition = AssessmentDefinition::create([
            'tenant_id' => $tenant->id,
            'family' => (string) Str::uuid(),
            'name' => 'Forme auditée',
            'kind' => 'enneagramme',
            'version' => 1,
            'engine_version' => EnneagramScoring::ENGINE_VERSION,
            'questions' => $template->questions,
            'scoring_rules' => $template->scoring_rules,
            'form_key' => 'A',
            'content_status' => 'DRAFT',
            'is_demo' => false,
            'licensed' => true,
            'source_reference' => 'SOURCE-AUDIT-021',
            'created_by' => $publisher->id,
        ]);

        $this->actingAs($publisher)
            ->post(
                route('questionnaires.enneagram.review', $definition),
                ['reviewed' => '1']
            )
            ->assertRedirect();

        $this->post(
            route('questionnaires.enneagram.approve', $definition),
            ['approved' => '1']
        )->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $publisher->id,
            'action' => 'enneagramme.forme_revuee',
            'entity_type' => 'AssessmentDefinition',
            'entity_id' => $definition->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'user_id' => $publisher->id,
            'action' => 'enneagramme.forme_approuvee',
            'entity_type' => 'AssessmentDefinition',
            'entity_id' => $definition->id,
        ]);
    }
}
