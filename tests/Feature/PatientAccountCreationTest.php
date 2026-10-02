<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PatientAccountCreationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): array
    {
        $tenant = Tenant::create(['name' => 'Cabinet test', 'retention_days' => 30]);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        return [$tenant, $admin];
    }

    private function patientPayload(Client $client, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Patient Test',
            'email' => 'patient@example.test',
            'password' => 'Patient-Password-2026!',
            'role' => 'patient',
            'client_id' => $client->id,
        ], $overrides);
    }

    public function test_admin_creates_a_patient_account_and_can_open_patientai_after_login(): void
    {
        config(['patientai.enabled' => true]);
        [$tenant, $admin] = $this->admin();
        $client = Client::create(['tenant_id' => $tenant->id, 'first_name' => 'Patient', 'last_name' => 'Test', 'email' => 'record@example.test']);

        $this->actingAs($admin)->get('/administration')->assertOk()
            ->assertSee('action="'.route('administration.users.store').'"', false)
            ->assertSee('value="'.$client->id.'"', false);
        $this->actingAs($admin)
            ->from('/administration')
            ->post('/administration/utilisateurs', $this->patientPayload($client))
            ->assertRedirect('/administration')
            ->assertSessionHas('success')
            ->assertSessionHas('success', fn (string $message): bool => ! str_contains($message, 'Patient-Password-2026!'))
            ->assertSessionMissing('_old_input.password');
        $this->get('/administration')->assertOk();

        $patient = User::where('email', 'patient@example.test')->firstOrFail();
        $this->assertSame('patient', $patient->role);
        $this->assertTrue($patient->active);
        $this->assertSame($tenant->id, $patient->tenant_id);
        $this->assertSame($patient->id, $client->fresh()->user_id);
        $this->assertTrue(Hash::check('Patient-Password-2026!', $patient->password));
        $this->assertStringNotContainsString('Patient-Password-2026!', AuditLog::query()->get()->toJson());

        $this->post('/deconnexion')->assertRedirect();
        $this->post('/connexion', ['email' => 'patient@example.test', 'password' => 'Patient-Password-2026!'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('✨ PatientAI');
        $this->get('/patient/assistant')->assertOk();
    }

    public function test_patient_account_requires_a_valid_unassigned_non_anonymized_client_in_admin_tenant(): void
    {
        [$tenant, $admin] = $this->admin();
        $otherTenant = Tenant::create(['name' => 'Autre cabinet', 'retention_days' => 30]);
        $otherTenantClient = Client::create(['tenant_id' => $otherTenant->id, 'first_name' => 'Autre', 'last_name' => 'Tenant', 'email' => 'other@example.test']);
        $alreadyAssigned = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'patient']);
        $assignedClient = Client::create(['tenant_id' => $tenant->id, 'user_id' => $alreadyAssigned->id, 'first_name' => 'Déjà', 'last_name' => 'Associé', 'email' => 'assigned@example.test']);
        $anonymizedClient = Client::create(['tenant_id' => $tenant->id, 'first_name' => 'Dossier', 'last_name' => 'Anonymisé', 'email' => 'anonymized@example.test', 'anonymized_at' => now()]);

        foreach ([$otherTenantClient, $assignedClient, $anonymizedClient] as $index => $client) {
            $email = 'rejected-'.$index.'@example.test';
            $this->actingAs($admin)->postJson('/administration/utilisateurs', $this->patientPayload($client, ['email' => $email]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('client_id');
            $this->assertDatabaseMissing('users', ['email' => $email]);
        }

        $this->actingAs($admin)->postJson('/administration/utilisateurs', [
            'name' => 'Patient sans dossier',
            'email' => 'missing-client@example.test',
            'password' => 'Patient-Password-2026!',
            'role' => 'patient',
        ])->assertUnprocessable()->assertJsonValidationErrors('client_id');
        $this->assertDatabaseMissing('users', ['email' => 'missing-client@example.test']);
    }

    public function test_email_and_password_are_validated_before_creating_an_account(): void
    {
        [$tenant, $admin] = $this->admin();
        $client = Client::create(['tenant_id' => $tenant->id, 'first_name' => 'Patient', 'last_name' => 'Test', 'email' => 'record@example.test']);

        $this->actingAs($admin)->from('/administration')->post('/administration/utilisateurs', $this->patientPayload($client, [
            'email' => 'not-an-email',
            'password' => 'short',
        ]))->assertRedirect('/administration')->assertSessionHasErrors(['email', 'password'])->assertSessionMissing('_old_input.password');

        $this->assertDatabaseMissing('users', ['email' => 'not-an-email']);

        User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'existing@example.test']);
        $this->postJson('/administration/utilisateurs', $this->patientPayload($client, ['email' => ' EXISTING@EXAMPLE.TEST ']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_non_admin_cannot_create_accounts_and_admin_cannot_use_patientai_as_patient(): void
    {
        config(['patientai.enabled' => true]);
        [$tenant, $admin] = $this->admin();
        $patient = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'patient']);
        $client = Client::create(['tenant_id' => $tenant->id, 'user_id' => $patient->id, 'first_name' => 'Patient', 'last_name' => 'Test', 'email' => 'patient-record@example.test']);

        $this->actingAs($patient)->post('/administration/utilisateurs', $this->patientPayload($client, ['email' => 'unauthorized@example.test']))->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'unauthorized@example.test']);

        $this->actingAs($admin)->get('/')->assertOk()->assertDontSee('PatientAI');
        $this->get('/patient/assistant')->assertForbidden();
    }
}
