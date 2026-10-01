<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentDefinition;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Interpretation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AiInterpretationHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['psycho.ai_enabled' => true, 'psycho.ai_endpoint' => 'https://ai.example.test/generate', 'psycho.ai_key' => 'fake', 'psycho.ai_model' => 'requested-test-model']);
    }

    private function assessment(string $role = 'admin'): array
    {
        $tenant = Tenant::create(['name' => 'Cabinet test IA']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role]);
        $patient = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'patient']);
        $client = Client::create(['tenant_id' => $tenant->id, 'user_id' => $patient->id, 'first_name' => 'Personne', 'last_name' => 'Fictive', 'email' => $patient->email]);
        $definition = AssessmentDefinition::create(['tenant_id' => $tenant->id, 'family' => (string) Str::uuid(), 'name' => 'Définition test', 'kind' => 'personnalise', 'version' => 1, 'questions' => []]);
        $assessment = Assessment::create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'assigned_by' => $admin->id, 'assessment_definition_id' => $definition->id, 'status' => 'termine', 'results' => ['kind' => 'personnalise', 'answers' => ['texte' => 'REPONSE_PRIVEE'], 'engine' => 'raw-v1', 'definition_version' => 1]]);

        return [$assessment, $admin, $patient];
    }

    #[TestWith(['admin'])]
    #[TestWith(['psychologue'])]
    public function test_original_survives_review_publication_and_regeneration(string $role): void
    {
        [$assessment, $admin, $patient] = $this->assessment($role);
        $this->freezeTime();
        Http::fake(['https://ai.example.test/generate' => Http::sequence()
            ->push(['id' => 'response-a', 'model' => 'returned-test-model', 'choices' => [['message' => ['content' => 'ORIGINAL_IA_A']]]])
            ->push(['id' => 'response-c', 'choices' => [['message' => ['content' => 'ORIGINAL_IA_C']]]])]);
        $url = '/evaluations/'.$assessment->id;
        $this->actingAs($admin)->post($url.'/ia', ['authorized' => 1])->assertSessionHasNoErrors();
        $interpretation = $assessment->fresh()->interpretation;
        $first = $interpretation->ai_generations[0];
        $this->assertSame('ORIGINAL_IA_A', $first['content']);
        $this->assertSame('ORIGINAL_IA_A', $interpretation->draft);
        $this->assertNull($interpretation->published_content);
        $this->assertSame('termine', $assessment->fresh()->status);
        $this->assertSame($admin->id, $first['requested_by']);
        $this->assertSame(now()->toIso8601String(), $first['recorded_at']);
        $this->assertSame('response-a', $first['response_id']);
        $this->assertSame('returned-test-model', $first['response_model']);
        $this->assertSame('requested-test-model', $first['requested_model']);
        $this->assertSame('restitution-v1', $first['prompt_version']);
        $this->assertSame('raw-v1', $first['input_snapshot']['results']['engine']);
        $this->assertArrayNotHasKey('answers', $first['input_snapshot']['results']);
        $this->assertArrayNotHasKey('ai_generations', $interpretation->toArray());
        $this->assertStringNotContainsString('ORIGINAL_IA_A', DB::table('interpretations')->where('id', $interpretation->id)->value('ai_generations'));
        $this->actingAs($patient)->get($url)->assertDontSee('ORIGINAL_IA_A')->assertDontSee('Historique des générations IA');

        $this->actingAs($admin)->post($url.'/interpretation', ['draft' => 'REVISION_HUMAINE_B', 'ai_generations' => [['content' => 'FAUX_ORIGINAL']]])->assertSessionHasNoErrors();
        $this->assertSame([$first], $interpretation->fresh()->ai_generations);
        $this->assertSame('REVISION_HUMAINE_B', $interpretation->fresh()->draft);
        $this->post($url.'/publier', ['reviewed' => 1])->assertSessionHasNoErrors();
        $publishedAt = $interpretation->fresh()->published_at->toIso8601String();
        $this->assertSame('REVISION_HUMAINE_B', $interpretation->fresh()->published_content);
        $this->assertSame([$first], $interpretation->fresh()->ai_generations);
        $this->actingAs($patient)->get($url)->assertSee('REVISION_HUMAINE_B')->assertDontSee('ORIGINAL_IA_A');

        $this->travel(1)->minutes();
        $this->actingAs($admin)->post($url.'/ia', ['authorized' => 1])->assertSessionHasNoErrors();
        $updated = $interpretation->fresh();
        $this->assertCount(2, $updated->ai_generations);
        $this->assertSame($first, $updated->ai_generations[0]);
        $this->assertSame('ORIGINAL_IA_C', $updated->ai_generations[1]['content']);
        $this->assertNotSame($first['id'], $updated->ai_generations[1]['id']);
        $this->assertNull($updated->ai_generations[1]['response_model']);
        $this->assertSame('ORIGINAL_IA_C', $updated->draft);
        $this->assertSame('REVISION_HUMAINE_B', $updated->published_content);
        $this->assertSame($publishedAt, $updated->published_at->toIso8601String());
        $this->assertSame($admin->id, $updated->reviewed_by);
        $this->assertSame('publie', $assessment->fresh()->status);
        $this->get($url)->assertSee('ORIGINAL_IA_A')->assertSee('ORIGINAL_IA_C');
        $this->actingAs($patient)->get($url)->assertSee('REVISION_HUMAINE_B')->assertDontSee('ORIGINAL_IA_A')->assertDontSee('ORIGINAL_IA_C');
        $export = $this->get('/droits/clients/'.$assessment->client_id.'/export')->streamedContent();
        $this->assertStringNotContainsString('ORIGINAL_IA_A', $export);
        $this->assertStringNotContainsString('ORIGINAL_IA_C', $export);
        $this->assertSame(2, AuditLog::where('action', 'interpretation.generee')->where('entity_id', $interpretation->id)->count());
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['messages'] === $first['messages'] && ! str_contains($request->body(), 'REPONSE_PRIVEE'));
    }

    public function test_full_original_is_kept_even_when_draft_is_limited_and_history_is_escaped(): void
    {
        [$assessment, $admin] = $this->assessment();
        $original = '<script>test</script>'.str_repeat('A', 50001).'FIN_ORIGINALE';
        Http::fake(['https://ai.example.test/generate' => Http::response(['choices' => [['message' => ['content' => $original]]]])]);
        $url = '/evaluations/'.$assessment->id;
        $this->actingAs($admin)->post($url.'/ia', ['authorized' => 1])->assertSessionHasNoErrors();
        $interpretation = $assessment->fresh()->interpretation;
        $this->assertSame($original, $interpretation->ai_generations[0]['content']);
        $this->assertSame(50000, mb_strlen($interpretation->draft));
        $this->assertNull($interpretation->ai_generations[0]['response_id']);
        $this->get($url)->assertSee('FIN_ORIGINALE')->assertDontSee('<script>test</script>', false);
        Http::assertSentCount(1);
    }

    #[TestWith([503, 'Erreur fournisseur'])]
    #[TestWith([200, ''])]
    #[TestWith([200, null])]
    public function test_failed_generation_preserves_all_existing_content(int $status, ?string $content): void
    {
        [$assessment, $admin] = $this->assessment();
        Http::fake(['https://ai.example.test/generate' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => 'ORIGINAL_CONSERVE']]]])
            ->push(['choices' => [['message' => ['content' => $content]]]], $status)]);
        $url = '/evaluations/'.$assessment->id;
        $this->actingAs($admin)->post($url.'/ia', ['authorized' => 1]);
        $this->post($url.'/publier', ['reviewed' => 1]);
        $before = $assessment->fresh()->interpretation->getRawOriginal();
        $this->post($url.'/ia', ['authorized' => 1])->assertSessionHasErrors('ai');
        $this->assertSame($before, $assessment->fresh()->interpretation->getRawOriginal());
        $this->assertSame(1, AuditLog::where('action', 'interpretation.generee')->count());
        Http::assertSentCount(2);
    }

    #[TestWith(['patient'])]
    #[TestWith(['conseiller'])]
    #[TestWith(['entreprise'])]
    public function test_unauthorized_roles_cannot_generate_or_read_originals(string $role): void
    {
        [$assessment, $admin] = $this->assessment();
        $interpretation = Interpretation::create(['tenant_id' => $admin->tenant_id, 'assessment_id' => $assessment->id, 'draft' => 'Brouillon', 'ai_generations' => [['content' => 'HISTORIQUE_INTERDIT']]]);
        $user = User::factory()->create(['tenant_id' => $admin->tenant_id, 'role' => $role]);
        $this->actingAs($user)->post('/evaluations/'.$assessment->id.'/ia', ['authorized' => 1])->assertForbidden();
        $this->get('/evaluations/'.$assessment->id)->assertDontSee('HISTORIQUE_INTERDIT');
        $this->assertSame([['content' => 'HISTORIQUE_INTERDIT']], $interpretation->fresh()->ai_generations);
        Http::assertNothingSent();
    }

    public function test_connection_failure_does_not_create_an_interpretation(): void
    {
        [$assessment, $admin] = $this->assessment();
        Http::fake(['https://ai.example.test/generate' => Http::failedConnection()]);
        $this->actingAs($admin)->post('/evaluations/'.$assessment->id.'/ia', ['authorized' => 1])->assertSessionHasErrors('ai');
        $this->assertDatabaseCount('interpretations', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame('termine', $assessment->fresh()->status);
    }

    public function test_other_tenant_cannot_access_history_or_generate(): void
    {
        [$assessment, $admin] = $this->assessment();
        Interpretation::create(['tenant_id' => $admin->tenant_id, 'assessment_id' => $assessment->id, 'draft' => 'Privé']);
        $otherTenant = Tenant::create(['name' => 'Autre cabinet']);
        $other = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'admin']);
        $this->actingAs($other)->post('/evaluations/'.$assessment->id.'/ia', ['authorized' => 1])->assertNotFound();
        $this->get('/evaluations/'.$assessment->id)->assertNotFound();
        $this->assertDatabaseCount('interpretations', 1);
        $this->assertSame(0, Interpretation::count());
        Http::assertNothingSent();
    }

    public function test_additive_migration_does_not_invent_a_legacy_original(): void
    {
        [$assessment, $admin] = $this->assessment();
        $migration = require database_path('migrations/2026_10_01_160120_add_ai_generations_to_interpretations_table.php');
        $migration->down();
        try {
            $legacy = Interpretation::create(['tenant_id' => $admin->tenant_id, 'assessment_id' => $assessment->id, 'source' => 'ia', 'model' => 'ancien-modele', 'draft' => 'Ancien brouillon peut-être révisé', 'published_content' => 'Ancienne publication', 'published_at' => now()]);
            $before = $legacy->fresh()->getRawOriginal();
        } finally {
            $migration->up();
        }
        $after = $legacy->fresh()->getRawOriginal();
        $this->assertNull($after['ai_generations']);
        unset($after['ai_generations']);
        $this->assertSame($before, $after);
        Http::fake(['https://ai.example.test/generate' => Http::response(['choices' => [['message' => ['content' => 'Première sortie réellement conservée']]]])]);
        $this->actingAs($admin)->post('/evaluations/'.$assessment->id.'/ia', ['authorized' => 1])->assertSessionHasNoErrors();
        $this->assertCount(1, $legacy->fresh()->ai_generations);
        $this->assertSame('Première sortie réellement conservée', $legacy->fresh()->ai_generations[0]['content']);
        $this->assertSame('Ancienne publication', $legacy->fresh()->published_content);
        Http::assertSentCount(1);
    }
}
