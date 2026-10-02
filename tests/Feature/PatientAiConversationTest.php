<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\AssessmentDefinition;
use App\Models\Client;
use App\Models\Consent;
use App\Models\Interpretation;
use App\Models\User;
use App\Services\ConversationIntentRouter;
use App\Services\PatientAiChat;
use App\Services\PatientAiLifecycle;
use App\Services\PatientContextFactory;
use App\Services\PatientConversationReferences;
use App\Services\PatientMemoryService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PatientAiConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['patientai.enabled' => true, 'app.timezone' => 'Africa/Kampala']);
        $this->travelTo(CarbonImmutable::parse('2026-10-02T09:00:00Z'));
        Http::preventStrayRequests();
        Http::fake();
    }

    private function assessment(AiConversation $conversation, string $name, string $status = 'en_cours'): Assessment
    {
        $definition = AssessmentDefinition::create([
            'tenant_id' => $conversation->tenant_id,
            'family' => (string) Str::uuid(),
            'name' => $name,
            'kind' => 'personnalise',
            'version' => 1,
            'questions' => [['id' => 'Q1', 'label' => 'Consigne test', 'type' => 'scale', 'min' => 1, 'max' => 5]],
        ]);

        return Assessment::create([
            'tenant_id' => $conversation->tenant_id,
            'client_id' => $conversation->client_id,
            'assessment_definition_id' => $definition->id,
            'assigned_by' => $conversation->user_id,
            'status' => $status,
            'answers' => ['Q1' => 'ANSWER_PRIVATE'],
            'results' => [],
        ]);
    }

    private function consent(AiConversation $conversation): void
    {
        Consent::create([
            'tenant_id' => $conversation->tenant_id,
            'client_id' => $conversation->client_id,
            'version' => config('psycho.consent_version'),
            'text' => config('psycho.consent_text'),
            'accepted_at' => now(),
        ]);
    }

    private function appointment(AiConversation $conversation, string $title = 'Consultation test'): Appointment
    {
        return Appointment::create([
            'tenant_id' => $conversation->tenant_id,
            'client_id' => $conversation->client_id,
            'starts_at' => '2026-10-03 12:00:00',
            'title' => $title,
            'status' => 'planifie',
            'duration' => 45,
            'location' => 'Cabinet',
        ]);
    }

    private function login(AiConversation $conversation): void
    {
        $this->actingAs(User::findOrFail($conversation->user_id));
    }

    private function send(AiConversation $conversation, string $message): string
    {
        app(PatientAiChat::class)->send($conversation, $message);

        return AiMessage::where('ai_conversation_id', $conversation->id)->where('role', 'assistant')->latest('id')->firstOrFail()->content;
    }

    public function test_render_phrases_resolve_to_feature_020_intents(): void
    {
        $router = new ConversationIntentRouter;
        $cases = [
            'Bonjour' => 'greeting',
            'Bonjour PatientAI' => 'greeting',
            'bonjour PatientsAI' => 'greeting',
            'HI' => 'greeting',
            'QUI ES-TU?' => 'identity',
            'Who are you?' => 'identity',
            'What can you help me with?' => 'capabilities',
            'Quelles sont mes évaluations ?' => 'assessments_list',
            'Quel est le statut de mon évaluation ?' => 'assessment_status',
            'Et son statut ?' => 'assessment_status',
            'Explique-moi mon questionnaire' => 'questionnaire_help',
            'Explique-moi ce questionnaire' => 'questionnaire_help',
            'Que sais-tu de moi ?' => 'about_my_data',
            'Quel est mon prochain rendez-vous ?' => 'appointment_next',
            'Quels sont mes rendez-vous ?' => 'appointments_list',
            'Explique-moi mon résultat publié' => 'published_result',
            'As-tu mémorisé ma préférence ?' => 'memory_status',
            'Comment utiliser l’espace patient ?' => 'documentation',
            'Demande inconnue' => 'unknown',
        ];

        foreach ($cases as $message => $expected) {
            $this->assertSame($expected, $router->route($message), $message);
        }
        $this->assertSame('greeting', $router->route('bonjor'));
        $this->assertSame('greeting', $router->route('Bonjour, PatientAI !'));
        $this->assertSame('unknown', $router->route('PatientAI, bonjour'));
        $this->assertSame('unknown', $router->route('Explique mon questionnnaire'));
        $this->assertSame('questionnaire_help', $router->route('Explique-le-moi'));
        $this->assertSame('assessment_status', $router->route("Quel est le statut de mon e\u{0301}valuation ?"));
    }

    public function test_documentary_search_wrapper_preserves_business_intent_precedence(): void
    {
        $router = new ConversationIntentRouter;
        $cases = [
            'Recherche documentaire : Quand est mon prochain rendez-vous ?' => 'appointment_next',
            'Recherche documentaire : Quelles sont mes évaluations ?' => 'assessments_list',
            'Recherche documentaire : Explique mon résultat '.Str::uuid() => 'published_result',
            'Recherche documentaire : Aide questionnaire '.Str::uuid() => 'questionnaire_help',
            'Recherche documentaire : Comment fonctionne la messagerie ?' => 'documentation',
        ];

        foreach ($cases as $message => $expected) {
            $this->assertSame($expected, $router->route($message), $message);
        }
    }

    public function test_unique_assessment_is_reused_for_status_and_questionnaire_help_without_uuid(): void
    {
        $conversation = AiConversation::factory()->create();
        $assessment = $this->assessment($conversation, 'TEST IA DEMO');
        $this->consent($conversation);
        $this->login($conversation);

        $listed = $this->send($conversation, 'Quelles sont mes évaluations ?');
        $this->assertStringContainsString('TEST IA DEMO', $listed);
        $this->assertStringContainsString('En cours', $listed);
        $this->assertStringNotContainsString($assessment->uuid, $listed);

        $context = $conversation->fresh()->conversation_context;
        $this->assertSame($assessment->uuid, $context['assessment']['uuid']);
        $this->assertStringNotContainsString('ANSWER_PRIVATE', json_encode($context));
        $rawContext = DB::table('ai_conversations')->where('id', $conversation->id)->value('conversation_context');
        $this->assertNotSame(json_encode($context), $rawContext);
        $this->assertStringNotContainsString('ANSWER_PRIVATE', $rawContext);
        $this->assertArrayNotHasKey('conversation_context', $conversation->fresh()->toArray());
        $this->assertNull($conversation->fresh()->memory);

        $status = $this->send($conversation, 'Quel est le statut de mon évaluation ?');
        $this->assertStringContainsString('En cours', $status);
        $this->assertStringNotContainsString($assessment->uuid, $status);

        $help = $this->send($conversation, 'Explique-moi mon questionnaire');
        $this->assertStringContainsString('TEST IA DEMO', $help);
        $this->assertStringContainsString('Lisez la consigne affichée', $help);
        $this->assertStringNotContainsString($assessment->uuid, $help);
        $this->assertStringNotContainsString('ANSWER_PRIVATE', $help);
        Http::assertNothingSent();
    }

    public function test_status_auto_resolves_one_assessment_and_clarifies_multiple_without_uuids(): void
    {
        $conversation = AiConversation::factory()->create();
        $only = $this->assessment($conversation, 'Évaluation unique');
        $this->login($conversation);

        $status = $this->send($conversation, 'Quel est le statut de mon évaluation ?');
        $this->assertStringContainsString('Évaluation unique', $status);
        $this->assertStringContainsString('En cours', $status);
        $this->assertStringNotContainsString($only->uuid, $status);

        $this->assessment($conversation, 'Évaluation B');
        $otherConversation = AiConversation::create([
            'tenant_id' => $conversation->tenant_id,
            'user_id' => $conversation->user_id,
            'client_id' => $conversation->client_id,
            'consent_version' => config('patientai.consent_version'),
            'consent_text' => config('patientai.consent_text'),
            'consented_at' => now(),
        ]);
        $clarification = $this->send($otherConversation, 'Quel est le statut de mon évaluation ?');
        $this->assertStringContainsString('Plusieurs évaluations sont accessibles', $clarification);
        $this->assertStringContainsString('Évaluation unique', $clarification);
        $this->assertStringContainsString('Évaluation B', $clarification);
        $this->assertStringNotContainsString($only->uuid, $clarification);
        $this->assertStringNotContainsString('UUID', $clarification);
    }

    public function test_no_assessment_or_foreign_assessment_returns_non_enumerable_message(): void
    {
        $conversation = AiConversation::factory()->create();
        $foreign = AiConversation::factory()->create();
        $foreignAssessment = $this->assessment($foreign, 'FOREIGN PRIVATE NAME');
        $this->login($conversation);

        $empty = $this->send($conversation, 'Quel est le statut de mon évaluation ?');
        $this->assertStringContainsString('Aucune évaluation correspondante', $empty);
        $crossTenant = $this->send($conversation, 'Quel est le statut de mon évaluation '.$foreignAssessment->uuid.' ?');
        $this->assertStringNotContainsString('FOREIGN PRIVATE NAME', $crossTenant);
        $this->assertStringNotContainsString($foreignAssessment->uuid, $crossTenant);
        Http::assertNothingSent();
    }

    public function test_reference_is_reauthorized_and_forgotten_after_assessment_moves_to_another_client(): void
    {
        $conversation = AiConversation::factory()->create();
        $other = AiConversation::factory()->create(['tenant_id' => $conversation->tenant_id]);
        $assessment = $this->assessment($conversation, 'Réassignée');
        $this->login($conversation);
        $this->send($conversation, 'Quelles sont mes évaluations ?');

        DB::table('assessments')->where('id', $assessment->id)->update(['client_id' => $other->client_id]);
        $reply = $this->send($conversation, 'Quel est le statut de mon évaluation ?');
        $this->assertStringContainsString('Aucune évaluation correspondante', $reply);
        $context = app(PatientContextFactory::class)->fromAuthenticatedUser();
        $this->assertNull(app(PatientConversationReferences::class)->assessmentUuid($conversation->fresh(), $context));
        $this->assertStringNotContainsString('Réassignée', $reply);
        Http::assertNothingSent();
    }

    public function test_appointment_follow_up_reuses_only_current_conversation_reference(): void
    {
        $conversation = AiConversation::factory()->create();
        $appointment = $this->appointment($conversation);
        $this->login($conversation);

        $first = $this->send($conversation, 'J’ai un rendez-vous ?');
        $this->assertStringContainsString('Consultation test', $first);
        $this->assertSame($appointment->id, $conversation->fresh()->conversation_context['appointment']['id']);
        $when = $this->send($conversation, 'Quand ?');
        $this->assertStringContainsString('03/10/2026 à 12:00:00', $when);
        $this->assertStringNotContainsString('/calendrier/'.$appointment->id, $when);

        $this->assessment($conversation, 'Évaluation hors du contexte courant');
        $followUp = $this->send($conversation, 'Et son statut ?');
        $this->assertStringContainsString('De quelle évaluation souhaitez-vous connaître le statut ?', $followUp);
        $this->assertStringNotContainsString('Évaluation hors du contexte courant', $followUp);

        $otherConversation = AiConversation::create([
            'tenant_id' => $conversation->tenant_id,
            'user_id' => $conversation->user_id,
            'client_id' => $conversation->client_id,
            'consent_version' => config('patientai.consent_version'),
            'consent_text' => config('patientai.consent_text'),
            'consented_at' => now(),
        ]);
        $context = app(PatientContextFactory::class)->fromAuthenticatedUser();
        $this->assertNull(app(PatientConversationReferences::class)->appointmentId($otherConversation, $context));
        $bareWhen = $this->send($otherConversation, 'Quand ?');
        $this->assertStringContainsString('De quel rendez-vous', $bareWhen);
    }

    public function test_about_my_data_is_static_and_does_not_query_business_data_or_memory(): void
    {
        $conversation = AiConversation::factory()->create(['memory_enabled' => true, 'memory' => ['response_style' => 'concise']]);
        $this->assessment($conversation, 'PRIVATE ASSESSMENT NAME');
        $this->login($conversation);
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $reply = $this->send($conversation, 'Que sais-tu de moi ?');
        $this->assertStringContainsString('évaluations qui vous sont accessibles', $reply);
        $this->assertStringContainsString('notes cliniques', $reply);
        $this->assertStringNotContainsString('PRIVATE ASSESSMENT NAME', $reply);
        $this->assertStringNotContainsString('concise', $reply);
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/(?:from|join) ["`]*(assessments|assessment_definitions|appointments|interpretations|clinical_notes|patient_rag_documents)/i', $query);
        }
        Http::assertNothingSent();
    }

    public function test_assessment_reference_is_scoped_deleted_with_conversation_and_does_not_touch_memory(): void
    {
        $conversation = AiConversation::factory()->create(['memory_enabled' => true, 'memory' => ['response_style' => 'concise']]);
        $assessment = $this->assessment($conversation, 'Référence effaçable');
        $this->login($conversation);
        $this->send($conversation, 'Quelles sont mes évaluations ?');

        $context = app(PatientContextFactory::class)->fromAuthenticatedUser();
        $references = app(PatientConversationReferences::class);
        $this->assertSame($assessment->uuid, $references->assessmentUuid($conversation->fresh(), $context));
        $otherConversation = AiConversation::create([
            'tenant_id' => $conversation->tenant_id,
            'user_id' => $conversation->user_id,
            'client_id' => $conversation->client_id,
            'consent_version' => config('patientai.consent_version'),
            'consent_text' => config('patientai.consent_text'),
            'consented_at' => now(),
        ]);
        $this->assertNull($references->assessmentUuid($otherConversation, $context));
        $this->assertSame('concise', $conversation->fresh()->memory['response_style']);

        $conversation->delete();
        $this->assertDatabaseMissing('ai_conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('ai_messages', ['ai_conversation_id' => $conversation->id]);
        Http::assertNothingSent();
    }

    public function test_expired_conversation_cannot_reuse_its_reference(): void
    {
        $conversation = AiConversation::factory()->create();
        $this->assessment($conversation, 'Expirée');
        $this->login($conversation);
        $this->send($conversation, 'Quelles sont mes évaluations ?');
        $this->travel(31)->days();

        $this->assertTrue(app(PatientAiLifecycle::class)->expired($conversation->fresh()));
        try {
            app(PatientAiChat::class)->send($conversation->fresh(), 'Et son statut ?');
            $this->fail('An expired conversation must reject its reference.');
        } catch (HttpException $exception) {
            $this->assertSame(410, $exception->getStatusCode());
        }
        Http::assertNothingSent();
    }

    public function test_published_result_is_resolved_without_uuid_and_only_after_publication(): void
    {
        $conversation = AiConversation::factory()->create();
        $assessment = $this->assessment($conversation, 'Restitution publiée', 'publie');
        Interpretation::create([
            'tenant_id' => $conversation->tenant_id,
            'assessment_id' => $assessment->id,
            'draft' => 'DRAFT_PRIVATE',
            'published_content' => 'Texte effectivement publié.',
            'published_at' => now(),
        ]);
        $this->login($conversation);

        $reply = $this->send($conversation, 'Explique-moi mon résultat publié');
        $this->assertStringContainsString('Texte effectivement publié.', $reply);
        $this->assertStringNotContainsString($assessment->uuid, $reply);
        $this->assertStringNotContainsString('DRAFT_PRIVATE', $reply);
        $this->assertSame($assessment->uuid, $conversation->fresh()->conversation_context['assessment']['uuid']);
        Http::assertNothingSent();
    }

    public function test_memory_status_and_documentation_use_only_their_approved_sources(): void
    {
        $conversation = AiConversation::factory()->create([
            'memory_enabled' => true,
            'memory_consent_version' => PatientMemoryService::CONSENT_VERSION,
            'memory_consented_at' => now(),
            'memory' => ['response_style' => 'concise'],
        ]);
        $this->login($conversation);

        $memory = $this->send($conversation, 'As-tu mémorisé ma préférence ?');
        $this->assertStringContainsString('concise', $memory);
        $this->assertStringContainsString('ne constitue aucune information clinique', $memory);

        $documentation = $this->send($conversation, 'Comment utiliser l’espace patient ?');
        $this->assertStringContainsString('patient-guide-v0.7.1', $documentation);
        $this->assertStringNotContainsString('notes cliniques', $documentation);
        Http::assertNothingSent();
    }

    public function test_reference_export_is_redacted_and_deleted_with_conversation(): void
    {
        $conversation = AiConversation::factory()->create();
        $assessment = $this->assessment($conversation, 'Export référent');
        $this->login($conversation);
        $this->send($conversation, 'Quelles sont mes évaluations ?');

        $export = app(PatientAiLifecycle::class)->export(Client::findOrFail($conversation->client_id));
        $entry = collect($export)->firstWhere('uuid', $conversation->uuid);
        $this->assertSame('assessment', $entry['conversation_context'][0]['type']);
        $this->assertSame('assessments_list', $entry['conversation_context'][0]['source']);
        $this->assertSame(1, $entry['conversation_context'][0]['source_turn']);
        $this->assertStringNotContainsString($assessment->uuid, json_encode($entry['conversation_context']));
        $this->assertStringNotContainsString('Export référent', json_encode($entry['conversation_context']));
        Http::assertNothingSent();
    }

    public function test_context_migration_is_additive_and_has_a_defined_rollback(): void
    {
        $conversation = AiConversation::factory()->create();
        $migration = require database_path('migrations/2026_10_02_120000_add_conversation_context_to_ai_conversations.php');

        $this->assertTrue(Schema::hasColumn('ai_conversations', 'conversation_context'));
        $this->assertTrue(Schema::hasColumn('ai_conversations', 'memory'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('ai_conversations', 'conversation_context'));
        $this->assertTrue(Schema::hasColumn('ai_conversations', 'memory'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('ai_conversations', 'conversation_context'));
        $this->assertNull($conversation->fresh()->conversation_context);
    }
}
