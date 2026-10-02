<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use App\Services\ConversationIntentRouter;
use App\Services\FakeLlmProvider;
use App\Services\LlmProvider;
use App\Services\PatientAiLifecycle;
use App\Services\Retention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PatientAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['patientai.enabled' => true]);
        Http::preventStrayRequests();
        Http::fake();
    }

    private function conversation(): AiConversation
    {
        return AiConversation::factory()->create();
    }

    private function owner(AiConversation $conversation): User
    {
        return User::findOrFail($conversation->user_id);
    }

    private function url(AiConversation $conversation): string
    {
        return '/patient/assistant/'.$conversation->uuid;
    }

    public function test_auth_role_inactive_missing_client_and_feature_flag(): void
    {
        $this->get('/patient/assistant')->assertRedirect('/connexion');
        $conversation = $this->conversation();
        $patient = $this->owner($conversation);
        $this->actingAs($patient)->get('/patient/assistant')->assertOk();
        $this->get('/')->assertSee(route('patientai.index'));
        foreach (['admin', 'psychologue', 'conseiller', 'entreprise'] as $role) {
            $user = User::factory()->create(['tenant_id' => $patient->tenant_id, 'role' => $role]);
            $this->actingAs($user)->get('/patient/assistant')->assertForbidden();
            $this->post('/patient/assistant', ['accepted' => 1])->assertForbidden();
            $this->post($this->url($conversation).'/messages', ['content' => 'Bonjour'])->assertForbidden();
        }
        $missing = User::factory()->create(['tenant_id' => $patient->tenant_id, 'role' => 'patient']);
        $this->actingAs($missing)->get('/patient/assistant')->assertForbidden();
        $patient->update(['active' => false]);
        $this->actingAs($patient)->get('/patient/assistant')->assertForbidden();
        $patient->update(['active' => true]);
        config(['patientai.enabled' => false]);
        $this->actingAs($patient)->get('/patient/assistant')->assertNotFound();
        $this->post('/patient/assistant', ['accepted' => 1])->assertNotFound();
        $this->get($this->url($conversation))->assertNotFound();
        $this->post($this->url($conversation).'/messages', ['content' => 'Bonjour'])->assertNotFound();
        $this->delete($this->url($conversation))->assertNotFound();
        $this->get('/')->assertDontSee(route('patientai.index'));
        $this->assertSame(1, AiConversation::count());
        Http::assertNothingSent();
    }

    public function test_activation_is_explicit_and_ownership_cannot_be_supplied(): void
    {
        $conversation = $this->conversation();
        $patient = $this->owner($conversation);
        $this->actingAs($patient)->post('/patient/assistant')->assertSessionHasErrors('accepted');
        $this->post('/patient/assistant', ['accepted' => 1, 'user_id' => 999, 'client_id' => 999, 'tenant_id' => 999])->assertRedirect();
        $created = AiConversation::latest('id')->firstOrFail();
        $this->assertSame($patient->id, $created->user_id);
        $this->assertSame($conversation->client_id, $created->client_id);
        $this->assertSame($patient->tenant_id, $created->tenant_id);
        $this->assertSame('patientai-v0.1', $created->consent_version);
        $this->assertNotNull($created->consented_at);
        $this->assertFalse($patient->client->hasConsent());
    }

    public function test_same_tenant_and_cross_tenant_isolation_for_all_operations(): void
    {
        $conversation = $this->conversation();
        $patient = $this->owner($conversation);
        $sameTenant = AiConversation::factory()->create(['tenant_id' => $patient->tenant_id]);
        $otherTenant = AiConversation::factory()->create();
        foreach ([$sameTenant, $otherTenant] as $other) {
            $this->actingAs($this->owner($other))->get($this->url($conversation))->assertNotFound();
            $this->post($this->url($conversation).'/messages', ['content' => 'Bonjour'])->assertNotFound();
            $this->delete($this->url($conversation))->assertNotFound();
            $this->get('/patient/assistant')->assertDontSee($conversation->uuid);
        }
        $this->assertSame(0, DB::table('ai_messages')->count());
        $this->assertSame(3, DB::table('ai_conversations')->count());
        $this->actingAs($patient);
        Client::findOrFail($conversation->client_id)->update(['user_id' => null]);
        $this->get($this->url($conversation))->assertForbidden();
    }

    public function test_messages_are_encrypted_escaped_audited_without_content_and_exported(): void
    {
        $conversation = $this->conversation();
        $patient = $this->owner($conversation);
        $content = '<script>alert("PRIVATE_TEXT")</script>';
        Log::spy();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->actingAs($patient)->post($this->url($conversation).'/messages', ['content' => $content])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, AiMessage::count());
        $this->assertSame($content, AiMessage::orderBy('id')->first()->content);
        $this->assertStringNotContainsString('PRIVATE_TEXT', DB::table('ai_messages')->orderBy('id')->value('content'));
        $this->get($this->url($conversation))->assertSee($content)->assertDontSee('<script>alert', false);
        $this->assertStringNotContainsString('PRIVATE_TEXT', json_encode(AuditLog::all()->toArray()));
        $this->assertSame(1, AuditLog::where('action', 'patientai.message_envoye')->count());
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/(?:from|join) ["`]*(assessments|interpretations|appointments|clinical_notes|assessment_definitions|documents)/i', $query);
        }
        config(['patientai.enabled' => false]);
        $export = $this->get('/droits/clients/'.$conversation->client_id.'/export')->assertOk()->streamedContent();
        $data = json_decode($export, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($content, $data['patientai_conversations'][0]['messages'][0]['content']);
        foreach (['debug', 'info', 'warning', 'error', 'critical', 'log'] as $method) {
            Log::shouldNotHaveReceived($method);
        }
        Http::assertNothingSent();
    }

    public function test_provider_receives_only_intent_and_failure_is_atomic_without_leaks(): void
    {
        $conversation = $this->conversation();
        $provider = $this->mock(LlmProvider::class);
        $provider->shouldReceive('reply')->once()->with('unknown')->andThrow(new RuntimeException('PRIVATE_FAILURE'));
        Log::spy();
        $this->actingAs($this->owner($conversation))->from($this->url($conversation))->post($this->url($conversation).'/messages', ['content' => 'PRIVATE_INPUT'])->assertRedirect()->assertSessionHasErrors('patientai')->assertSessionMissing('_old_input.content');
        $this->assertSame(0, AiMessage::count());
        $this->assertSame(0, AuditLog::count());
        $this->get($this->url($conversation))->assertDontSee('PRIVATE_FAILURE')->assertDontSee('PRIVATE_INPUT');
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');
        Http::assertNothingSent();
    }

    public function test_message_limits_and_rate_limiting(): void
    {
        $conversation = $this->conversation();
        config(['patientai.max_message_length' => 8, 'patientai.messages_per_minute' => 2]);
        $this->actingAs($this->owner($conversation))->post($this->url($conversation).'/messages', ['content' => 'PRIVATE_TOO_LONG'])->assertSessionHasErrors('content')->assertSessionMissing('_old_input.content');
        $this->post($this->url($conversation).'/messages', ['content' => 'Bonjour'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post($this->url($conversation).'/messages', ['content' => 'Bonjour'])->assertStatus(429);
        $this->assertSame(2, AiMessage::count());
        $this->travel(61)->seconds();
        $this->post($this->url($conversation).'/messages', ['content' => 'Merci'])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_csrf_is_enforced_when_testing_bypass_is_disabled(): void
    {
        $conversation = $this->conversation();
        $this->actingAs($this->owner($conversation));
        $this->app['env'] = 'local';
        $this->post($this->url($conversation).'/messages', ['content' => 'Bonjour'])->assertStatus(419);
        $this->post('/patient/assistant', ['accepted' => 1])->assertStatus(419);
        $this->delete($this->url($conversation))->assertStatus(419);
        $this->withSession(['_token' => 'test-csrf-token'])->post($this->url($conversation).'/messages', ['content' => 'Bonjour', '_token' => 'test-csrf-token'])->assertRedirect();
        $this->assertSame(2, AiMessage::count());
    }

    public static function greetings(): array
    {
        return [
            ['bonjour', 'greeting'], ['BONSOIR!!!', 'greeting'], ['salut', 'greeting'], ['hello', 'greeting'], ['hi', 'greeting'], ['coucou', 'greeting'],
            ['merci', 'courtesy'], ['merci beaucoup', 'courtesy'], ['au revoir', 'farewell'], ['À bientôt !', 'farewell'], ['bonne journée', 'farewell'], ['bonne soirée', 'farewell'], ['bonne nuit', 'farewell'],
            ['Qui es-tu ?', 'identity'], ['Que peux-tu faire ?', 'capabilities'], ['Aide-moi', 'capabilities'], ['Bjr', 'greeting'], ['bonjor', 'greeting'], ['slt', 'greeting'], ['mercii', 'courtesy'], ['s’il vous plaît', 'courtesy'],
        ];
    }

    #[DataProvider('greetings')]
    public function test_social_intents_are_deterministic_without_network_or_database(string $message, string $intent): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $router = new ConversationIntentRouter;
        $provider = new FakeLlmProvider;
        $this->assertSame($intent, $router->route($message));
        $this->assertSame($provider->reply($intent), $provider->reply($router->route($message)));
        $this->assertSame([], $queries);
        Http::assertNothingSent();
    }

    public function test_expiry_purge_tenant_context_hold_and_cascade(): void
    {
        $this->travelTo(now()->startOfDay());
        $expired = $this->conversation();
        $held = AiConversation::factory()->create(['tenant_id' => $expired->tenant_id]);
        $other = $this->conversation();
        foreach ([$expired, $held, $other] as $conversation) {
            AiMessage::factory()->create(['tenant_id' => $conversation->tenant_id, 'ai_conversation_id' => $conversation->id]);
        }
        Client::findOrFail($held->client_id)->update(['retention_hold' => true]);
        $this->travel(30)->days();
        $this->actingAs($this->owner($expired))->get($this->url($expired))->assertOk();
        $this->travel(1)->seconds();
        $this->get($this->url($expired))->assertStatus(410);
        $this->post($this->url($expired).'/messages', ['content' => 'Bonjour'])->assertStatus(410);
        $this->assertSame(1, app(PatientAiLifecycle::class)->purgeTenant($expired->tenant_id));
        $this->assertDatabaseMissing('ai_conversations', ['id' => $expired->id]);
        $this->assertDatabaseMissing('ai_messages', ['ai_conversation_id' => $expired->id]);
        $this->assertDatabaseHas('ai_conversations', ['id' => $held->id]);
        $this->assertDatabaseHas('ai_conversations', ['id' => $other->id]);
        $this->artisan('patientai:purge')->assertSuccessful();
        $this->assertDatabaseMissing('ai_conversations', ['id' => $other->id]);
        $this->assertDatabaseHas('ai_conversations', ['id' => $held->id]);
    }

    public function test_patient_deletion_and_dossier_erasure_remove_messages_even_when_flag_off(): void
    {
        $conversation = $this->conversation();
        AiMessage::factory()->create(['tenant_id' => $conversation->tenant_id, 'ai_conversation_id' => $conversation->id]);
        $this->actingAs($this->owner($conversation))->delete($this->url($conversation))->assertRedirect('/patient/assistant');
        $this->assertSame(0, AiMessage::count());
        $this->assertSame(1, AuditLog::where('action', 'patientai.conversation_effacee')->count());
        auth()->logout();
        $this->travelTo(now()->subYears(10));
        $conversation = $this->conversation();
        AiMessage::factory()->create(['tenant_id' => $conversation->tenant_id, 'ai_conversation_id' => $conversation->id]);
        $client = Client::findOrFail($conversation->client_id);
        $client->delete();
        $admin = User::factory()->create(['tenant_id' => $conversation->tenant_id, 'role' => 'admin']);
        $this->travel(11)->years();
        config(['patientai.enabled' => false]);
        $this->actingAs($admin);
        $this->assertTrue(app(Retention::class)->eligible($client));
        $this->post('/droits/clients/'.$client->id.'/effacer', ['confirmation' => 'ANONYMISER #'.$client->id, 'current_password' => 'password'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, AiConversation::count());
        $this->assertSame(0, AiMessage::count());
    }

    public function test_unsupported_provider_and_blank_reply_do_not_persist(): void
    {
        $conversation = $this->conversation();
        $this->actingAs($this->owner($conversation));
        config(['patientai.provider' => 'remote']);
        $this->post($this->url($conversation).'/messages', ['content' => 'Bonjour'])->assertSessionHasErrors('patientai');
        config(['patientai.provider' => 'fake']);
        $this->mock(LlmProvider::class)->shouldReceive('reply')->once()->with('greeting')->andReturn('');
        $this->post($this->url($conversation).'/messages', ['content' => 'Bonjour'])->assertSessionHasErrors('patientai');
        $this->assertSame(0, AiMessage::count());
        Http::assertNothingSent();
    }

    public function test_withdrawing_under_hold_preserves_export_but_blocks_messages(): void
    {
        $conversation = $this->conversation();
        $patient = $this->owner($conversation);
        Client::findOrFail($conversation->client_id)->update(['retention_hold' => true]);
        AiMessage::factory()->create(['tenant_id' => $conversation->tenant_id, 'ai_conversation_id' => $conversation->id, 'content' => 'Contenu conservé']);
        $this->actingAs($patient)->delete($this->url($conversation))->assertRedirect();
        $this->assertSame('withdrawn', $conversation->fresh()->status);
        $this->assertSame(1, AiMessage::count());
        $this->post($this->url($conversation).'/messages', ['content' => 'Bonjour'])->assertStatus(409);
        $this->get($this->url($conversation))->assertSee('Accord retiré');
        $this->assertSame(1, AuditLog::where('action', 'patientai.accord_retire')->count());
        $this->assertSame('Contenu conservé', app(PatientAiLifecycle::class)->export($patient->client)[0]['messages'][0]['content']);
        Client::findOrFail($conversation->client_id)->update(['retention_hold' => false]);
        $this->delete($this->url($conversation))->assertRedirect();
        $this->assertSame(0, AiMessage::count());
    }

    public function test_limits_are_scoped_per_patient_and_malformed_messages_are_rejected(): void
    {
        $first = $this->conversation();
        $second = AiConversation::factory()->create(['tenant_id' => $first->tenant_id]);
        config(['patientai.messages_per_minute' => 1]);
        $this->actingAs($this->owner($first))->post($this->url($first).'/messages', ['content' => 'bonjour'])->assertRedirect();
        $this->post($this->url($first).'/messages', ['content' => 'bonjour'])->assertStatus(429);
        $this->actingAs($this->owner($second))->post($this->url($second).'/messages', ['content' => 'bonjour'])->assertRedirect();
        $this->travel(61)->seconds();
        $this->post($this->url($second).'/messages', ['content' => ['bad']])->assertSessionHasErrors('content');
        $this->assertSame(4, AiMessage::count());
    }
}
