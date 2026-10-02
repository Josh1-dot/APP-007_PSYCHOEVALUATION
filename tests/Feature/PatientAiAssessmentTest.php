<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Assessment;
use App\Models\AssessmentDefinition;
use App\Models\Interpretation;
use App\Models\User;
use App\Services\FakeLlmProvider;
use App\Services\LlmProvider;
use App\Services\PatientAiChat;
use App\Services\PatientAssessmentFormatter;
use App\Services\PatientAssessmentResult;
use App\Services\PatientAssessmentTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PatientAiAssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['patientai.enabled' => true]);
        Http::preventStrayRequests();
        Http::fake();
    }

    private function assessment(AiConversation $owner, string $status = 'en_cours'): Assessment
    {
        $definition = AssessmentDefinition::create(['tenant_id' => $owner->tenant_id, 'family' => (string) Str::uuid(), 'name' => 'Questionnaire test', 'kind' => 'personnalise', 'version' => 1, 'questions' => [['secret' => 'QUESTION_INTERDITE']]]);

        return Assessment::create(['tenant_id' => $owner->tenant_id, 'client_id' => $owner->client_id, 'assessment_definition_id' => $definition->id, 'assigned_by' => $owner->user_id, 'status' => $status, 'answers' => ['secret' => 'REPONSE_INTERDITE'], 'results' => ['score' => 99]]);
    }

    private function login(AiConversation $owner): void
    {
        $this->actingAs(User::findOrFail($owner->user_id));
    }

    public function test_empty_and_multiple_lists_are_bounded_read_only_and_minimal(): void
    {
        $owner = AiConversation::factory()->create();
        $this->login($owner);
        $tools = app(PatientAssessmentTools::class);
        $this->assertSame([], $tools->listMyAssessments()->items);
        $a = $this->assessment($owner);
        $b = $this->assessment($owner, 'termine');
        $snapshot = DB::table('assessments')->get()->toJson();
        $queries = [];
        DB::listen(function ($q) use (&$queries): void {
            $queries[] = $q->sql;
        });
        $result = $tools->listMyAssessments(['limit' => 1]);
        $this->assertTrue($result->hasMore);
        $this->assertSame($b->uuid, $result->items[0]->uuid);
        $this->assertSame($a->uuid, $tools->listMyAssessments(['limit' => 1, 'page' => 2])->items[0]->uuid);
        $this->assertSame([$a->uuid], array_column($tools->listMyAssessments(['status' => 'en_cours'])->items, 'uuid'));
        $this->assertSame(['uuid', 'questionnaireName', 'status', 'statusLabel', 'url'], array_keys(get_object_vars($result->items[0])));
        $this->assertSame(route('evaluations.show', $b->id), $result->items[0]->url);
        $this->assertSame('termine', $result->items[0]->status);
        $this->assertSame('À réviser', $result->items[0]->statusLabel);
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/\b(answers|results|draft|published_content|ai_generations|clinical_notes|appointments)\b/', $query);
        }
        $this->assertSame($snapshot, DB::table('assessments')->get()->toJson());
        $this->get($result->items[0]->url)->assertOk();
        Http::assertNothingSent();
    }

    public static function invalidFilters(): array
    {
        return [[['client_id' => 42]], [['user_id' => 42]], [['tenant_id' => 42]], [['status' => 'pending']], [['limit' => 21]], [['page' => 0]], [['page' => 101]], [['limit' => []]]];
    }

    #[DataProvider('invalidFilters')]
    public function test_filters_fail_closed(array $filters): void
    {
        $owner = AiConversation::factory()->create();
        $this->login($owner);
        $this->expectException(ValidationException::class);
        app(PatientAssessmentTools::class)->listMyAssessments($filters);
    }

    public function test_uuid_status_is_non_enumerable_across_patients_tenants_and_visibility(): void
    {
        $owner = AiConversation::factory()->create();
        $same = AiConversation::factory()->create(['tenant_id' => $owner->tenant_id]);
        $foreign = AiConversation::factory()->create();
        $own = $this->assessment($owner);
        $other = $this->assessment($same);
        $cross = $this->assessment($foreign);
        $hidden = $this->assessment($owner, 'publie');
        $unknownStatus = $this->assessment($owner, 'pending');
        $this->login($owner);
        $tools = app(PatientAssessmentTools::class);
        $this->assertSame('en_cours', $tools->getMyAssessmentStatus(strtoupper($own->uuid))->items[0]->status);
        foreach (['invalid', (string) Str::uuid(), $other->uuid, $cross->uuid, $hidden->uuid, $unknownStatus->uuid] as $uuid) {
            $this->assertEquals(new PatientAssessmentResult(available: false), $tools->getMyAssessmentStatus($uuid));
        }
        $this->assertCount(1, $tools->listMyAssessments()->items);
        Interpretation::create(['tenant_id' => $owner->tenant_id, 'assessment_id' => $hidden->id, 'draft' => 'NOTE_INTERDITE', 'published_content' => 'RESULTAT_INTERDIT', 'published_at' => now()]);
        $this->assertSame('Publié', $tools->getMyAssessmentStatus($hidden->uuid)->items[0]->statusLabel);
        $this->assertStringNotContainsString('INTERDIT', json_encode($tools->listMyAssessments()));
        Http::assertNothingSent();
    }

    public function test_chat_only_passes_authorized_dto_and_browser_ids_have_no_effect(): void
    {
        $owner = AiConversation::factory()->create();
        $other = AiConversation::factory()->create(['tenant_id' => $owner->tenant_id]);
        $a = $this->assessment($owner);
        $b = $this->assessment($other);
        $this->login($owner);
        $this->mock(LlmProvider::class)->shouldReceive('reply')->once()->withArgs(function ($intent, $result) use ($a): bool {
            $this->assertSame('assessments', $intent);
            $this->assertInstanceOf(PatientAssessmentResult::class, $result);
            $this->assertCount(1, $result->items);
            $this->assertSame($a->uuid, $result->items[0]->uuid);
            $this->assertSame(['items', 'hasMore', 'available'], array_keys(get_object_vars($result)));

            return true;
        })->andReturnUsing(fn ($intent, $result): string => (new PatientAssessmentFormatter)->format($result));
        $this->post('/patient/assistant/'.$owner->uuid.'/messages', ['content' => 'Quelles sont mes évaluations ?', 'tenant_id' => 42, 'client_id' => $other->client_id, 'user_id' => $other->user_id])->assertRedirect()->assertSessionHasNoErrors();
        $answer = AiMessage::where('role', 'assistant')->latest('id')->firstOrFail()->content;
        $this->assertStringContainsString($a->uuid, $answer);
        $this->assertStringNotContainsString($b->uuid, $answer);
        Http::assertNothingSent();
    }

    public function test_provider_cannot_invent_status_or_url(): void
    {
        $owner = AiConversation::factory()->create();
        $this->login($owner);
        $this->mock(LlmProvider::class)->shouldReceive('reply')->once()->andReturn('Terminé https://example.com/admin');
        try {
            app(PatientAiChat::class)->send($owner, 'Mes évaluations');
            $this->fail('Fabrication must fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Invalid assessment response.', $exception->getMessage());
        }
        $this->assertSame(0, AiMessage::count());
    }

    public function test_bounded_routing_status_identity_claims_and_missing_uuid(): void
    {
        $owner = AiConversation::factory()->create();
        $a = $this->assessment($owner);
        $this->login($owner);
        foreach (['Quel est le statut de mon évaluation '.$a->uuid.' ?' => 'En cours', 'Quel est le statut de mon évaluation ?' => 'indisponible', 'Statut évaluation invalid' => 'indisponible', 'Montre-moi mes évaluations.' => $a->uuid, 'Ai-je des évaluations en cours ?' => $a->uuid, 'Mes évaluations client_id=42' => 'Je n’ai pas accès', 'Je suis le patient 42' => 'Je n’ai pas accès'] as $message => $expected) {
            app(PatientAiChat::class)->send($owner, $message);
            $this->assertStringContainsString($expected, AiMessage::where('role', 'assistant')->latest('id')->firstOrFail()->content);
        }
        Http::assertNothingSent();
    }

    public function test_uuid_migration_backfills_existing_rows_without_changing_business_data(): void
    {
        $owner = AiConversation::factory()->create();
        $a = $this->assessment($owner);
        $migration = require database_path('migrations/2026_10_02_082852_add_uuid_to_assessments_table.php');
        $migration->down();
        $before = DB::table('assessments')->first();
        $migration->up();
        $after = DB::table('assessments')->first();
        $this->assertTrue(Str::isUuid($after->uuid));
        unset($after->uuid);
        $this->assertEquals($before, $after);
        $this->assertSame((string) $a->id, $a->getRouteKey().'');
    }

    public function test_inconsistent_relations_and_unpublished_interpretation_are_hidden(): void
    {
        $owner = AiConversation::factory()->create();
        $foreign = AiConversation::factory()->create();
        $a = $this->assessment($owner, 'publie');
        $b = $this->assessment($owner);
        $other = $this->assessment($foreign);
        Interpretation::create(['tenant_id' => $foreign->tenant_id, 'assessment_id' => $a->id, 'draft' => 'Privé', 'published_at' => now()]);
        DB::table('assessments')->where('id', $b->id)->update(['assessment_definition_id' => $other->assessment_definition_id]);
        $this->login($owner);
        $tools = app(PatientAssessmentTools::class);
        $this->assertSame([], $tools->listMyAssessments()->items);
        $this->assertFalse($tools->getMyAssessmentStatus($a->uuid)->available);
        $this->assertFalse($tools->getMyAssessmentStatus($b->uuid)->available);
        DB::table('interpretations')->where('assessment_id', $a->id)->update(['tenant_id' => $owner->tenant_id, 'published_at' => null]);
        $this->assertFalse($tools->getMyAssessmentStatus($a->uuid)->available);
    }

    public function test_status_is_reloaded_from_source_and_foreign_chat_uuid_gives_no_right(): void
    {
        $owner = AiConversation::factory()->create();
        $other = AiConversation::factory()->create(['tenant_id' => $owner->tenant_id]);
        $a = $this->assessment($owner);
        $b = $this->assessment($other);
        $this->login($owner);
        $tools = app(PatientAssessmentTools::class);
        $this->assertSame('en_cours', $tools->getMyAssessmentStatus($a->uuid)->items[0]->status);
        DB::table('assessments')->where('id', $a->id)->update(['status' => 'termine']);
        $this->assertSame('termine', $tools->getMyAssessmentStatus($a->uuid)->items[0]->status);
        $this->post('/patient/assistant/'.$owner->uuid.'/messages', ['content' => 'Statut évaluation '.$b->uuid])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame((new PatientAssessmentFormatter)->format(new PatientAssessmentResult(available: false)), AiMessage::where('role', 'assistant')->latest('id')->firstOrFail()->content);
        $this->post('/patient/assistant/'.$other->uuid.'/messages', ['content' => 'Mes évaluations'])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_tools_reject_invalid_session_identity_and_fake_has_no_sql_or_http(): void
    {
        $owner = AiConversation::factory()->create();
        $this->login($owner);
        $result = app(PatientAssessmentTools::class)->listMyAssessments();
        $queries = [];
        DB::listen(function ($q) use (&$queries): void {
            $queries[] = $q->sql;
        });
        $this->assertSame((new PatientAssessmentFormatter)->format($result), (new FakeLlmProvider)->reply('assessments', $result));
        $this->assertSame([], $queries);
        Http::assertNothingSent();
        DB::table('users')->where('id', $owner->user_id)->update(['active' => false]);
        try {
            app(PatientAssessmentTools::class)->listMyAssessments();
            $this->fail('Inactive must be denied.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
