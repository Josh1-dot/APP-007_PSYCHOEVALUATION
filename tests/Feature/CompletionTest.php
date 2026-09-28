<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\AssessmentDefinition;
use App\Models\Client;
use App\Models\ClinicalNote;
use App\Models\Document;
use App\Models\Interpretation;
use App\Models\Letter;
use App\Models\LocalMail;
use App\Models\PrivacyRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\Backups;
use App\Services\Charts;
use App\Services\Retention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompletionTest extends TestCase
{
    use RefreshDatabase;

    private function cabinet(): array
    {
        $tenant = Tenant::create(['name' => 'Cabinet test', 'retention_days' => 30]);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
        $patient = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'patient']);
        $client = Client::create(['tenant_id' => $tenant->id, 'user_id' => $patient->id, 'first_name' => 'Personne', 'last_name' => 'Test', 'email' => $patient->email]);

        return [$tenant, $admin, $patient, $client];
    }

    public function test_invitation_activates_only_once_with_an_encrypted_local_message(): void
    {
        [$tenant,$admin] = $this->cabinet();
        $client = Client::create(['tenant_id' => $tenant->id, 'first_name' => 'Invite', 'last_name' => 'Test', 'email' => 'invite@example.test']);
        $this->actingAs($admin)->post('/administration/invitations', ['name' => 'Invite Test', 'email' => 'invite@example.test', 'role' => 'patient', 'client_id' => $client->id])->assertRedirect()->assertSessionHasNoErrors();
        $user = User::where('email', 'invite@example.test')->firstOrFail();
        $this->assertFalse($user->active);
        $this->assertSame($user->id, $client->fresh()->user_id);
        $mail = LocalMail::firstOrFail();
        preg_match('~/invitation/([a-zA-Z0-9]+)~', $mail->body, $match);
        $token = $match[1];
        $this->assertStringNotContainsString($token, DB::table('local_mails')->value('body'));
        $this->assertNotSame($token, UserInvitation::first()->token_hash);
        $this->post('/deconnexion');
        $this->get('/invitation/'.$token)->assertOk();
        $client->delete();
        $this->post('/invitation/'.$token, ['password' => 'New-Password-2026!', 'password_confirmation' => 'New-Password-2026!'])->assertForbidden();
        $client->restore();
        $this->post('/invitation/'.$token, ['password' => 'New-Password-2026!', 'password_confirmation' => 'New-Password-2026!'])->assertRedirect('/connexion');
        $this->assertTrue($user->fresh()->active);
        $this->assertTrue(Hash::check('New-Password-2026!', $user->fresh()->password));
        $this->post('/invitation/'.$token, ['password' => 'Another-Password-2026!', 'password_confirmation' => 'Another-Password-2026!'])->assertNotFound();
    }

    public function test_resending_and_expiring_invitation_invalidates_old_links(): void
    {
        [$tenant,$admin] = $this->cabinet();
        $this->actingAs($admin)->post('/administration/invitations', ['name' => 'Invite', 'email' => 'new@example.test', 'role' => 'conseiller']);
        preg_match('~/invitation/([a-zA-Z0-9]+)~', LocalMail::first()->body, $match);
        $old = $match[1];
        $invite = UserInvitation::first();
        $this->post('/administration/invitations/'.$invite->id.'/renvoyer')->assertRedirect();
        preg_match('~/invitation/([a-zA-Z0-9]+)~', LocalMail::latest('id')->first()->body, $match);
        $new = $match[1];
        $this->post('/deconnexion');
        $this->get('/invitation/'.$old)->assertNotFound();
        $this->get('/invitation/'.$new)->assertOk();
        $this->travel(49)->hours();
        $this->get('/invitation/'.$new)->assertNotFound();
    }

    public function test_reset_password_is_generic_one_use_and_closes_old_sessions(): void
    {
        [$tenant,$admin,$patient] = $this->cabinet();
        $this->post('/mot-de-passe-oublie', ['email' => 'missing@example.test'])->assertSessionHas('success');
        $this->assertSame(0, LocalMail::count());
        $this->post('/mot-de-passe-oublie', ['email' => $patient->email])->assertSessionHas('success');
        $mail = LocalMail::firstOrFail();
        preg_match('~/reinitialiser/([^?\s]+)~', $mail->body, $match);
        $token = $match[1];
        $this->get('/reinitialiser/'.$token.'?email='.urlencode($patient->email))->assertOk();
        $payload = ['email' => $patient->email, 'token' => $token, 'password' => 'Reset-Password-2026!', 'password_confirmation' => 'Reset-Password-2026!'];
        $this->post('/reinitialiser', $payload)->assertRedirect('/connexion');
        $this->assertTrue(Hash::check('Reset-Password-2026!', $patient->fresh()->password));
        $this->assertSame(1, $patient->fresh()->auth_version);
        $this->post('/reinitialiser', $payload)->assertSessionHasErrors('email');
    }

    public function test_role_changes_revoke_sessions_and_are_tenant_scoped(): void
    {
        [$tenant,$admin,$patient,$client] = $this->cabinet();
        $otherTenant = Tenant::create(['name' => 'Autre']);
        $other = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'patient']);
        $this->actingAs($admin)->put('/administration/utilisateurs/'.$other->id.'/role', ['role' => 'admin'])->assertNotFound();
        $this->put('/administration/utilisateurs/'.$admin->id.'/role', ['role' => 'conseiller'])->assertUnprocessable();
        $this->put('/administration/utilisateurs/'.$patient->id.'/role', ['role' => 'conseiller'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('conseiller', $patient->fresh()->role);
        $this->assertNull($client->fresh()->user_id);
        $this->assertSame(1, $patient->fresh()->auth_version);
        $this->actingAs($patient->fresh())->withSession(['auth_version' => 0])->get('/')->assertForbidden();
    }

    public function test_non_admin_cannot_manage_access_or_read_local_mail(): void
    {
        [$tenant,$admin,$patient] = $this->cabinet();
        $this->actingAs($patient)->get('/administration/boite-test')->assertForbidden();
        $this->post('/administration/invitations', ['name' => 'X', 'email' => 'x@example.test', 'role' => 'admin'])->assertForbidden();
        $this->put('/administration/utilisateurs/'.$admin->id.'/role', ['role' => 'patient'])->assertForbidden();
        $this->get('/droits')->assertForbidden();
    }

    public function test_letters_reject_another_patients_documents_and_produce_a_bundle(): void
    {
        Storage::fake('local');
        [$tenant,$admin,$patient,$client] = $this->cabinet();
        $other = Client::create(['tenant_id' => $tenant->id, 'first_name' => 'Autre', 'last_name' => 'Test', 'email' => 'other@example.test']);
        $this->actingAs($admin)->post('/documents', ['file' => UploadedFile::fake()->createWithContent('annexe.txt', 'Document de test'), 'client_id' => $client->id]);
        $document = Document::firstOrFail();
        $this->post('/courriers', ['client_id' => $other->id, 'subject' => 'Objet', 'body' => 'Texte', 'attachments' => [$document->id]])->assertUnprocessable();
        $this->assertSame(0, Letter::count());
        $this->post('/courriers', ['client_id' => $client->id, 'subject' => 'Objet', 'body' => 'Texte', 'attachments' => [$document->id]])->assertRedirect()->assertSessionHasNoErrors();
        $letter = Letter::firstOrFail();
        $this->assertSame(1, $letter->attachments()->count());
        $this->get('/courriers/'.$letter->id.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $response = $this->get('/courriers/'.$letter->id.'/archive')->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertStringStartsWith('%PDF-', $zip->getFromName('courrier.pdf'));
        $this->assertSame('Document de test', $zip->getFromName('piece-'.$document->id.'-annexe.txt'));
        $zip->close();
        @unlink($path);
        $this->actingAs($patient)->get('/courriers/'.$letter->id.'/archive')->assertForbidden();
    }

    public function test_logo_is_private_and_included_in_pdf(): void
    {
        [$tenant,$admin,$patient,$client] = $this->cabinet();
        $jpeg = file_get_contents(base_path('tests/Fixtures/logo.jpg'));
        $this->actingAs($admin)->post('/administration/logo', ['logo' => UploadedFile::fake()->createWithContent('logo.jpg', $jpeg)])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($tenant->fresh()->logo);
        $letter = Letter::create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'subject' => 'Avec logo', 'body' => 'Contenu']);
        $this->get('/courriers/'.$letter->id.'/pdf')->assertOk();
        $this->actingAs($patient)->delete('/administration/logo')->assertForbidden();
        $this->actingAs($admin)->delete('/administration/logo')->assertRedirect();
        $this->assertNull($tenant->fresh()->logo);
    }

    public function test_privacy_requests_and_export_keep_unpublished_scores_private(): void
    {
        [$tenant,$admin,$patient,$client] = $this->cabinet();
        ClinicalNote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'author_id' => $admin->id, 'body' => 'NOTE_SECRETE']);
        $this->actingAs($patient)->post('/profil/demandes', ['kind' => 'rectification', 'details' => 'Corriger mon prénom'])->assertRedirect();
        $request = PrivacyRequest::firstOrFail();
        $this->assertSame('rectification', $request->kind);
        $response = $this->get('/droits/clients/'.$client->id.'/export')->assertOk();
        $content = $response->streamedContent();
        $this->assertStringNotContainsString('NOTE_SECRETE', $content);
        $this->actingAs($admin)->post('/droits/demandes/'.$request->id, ['status' => 'traitee', 'response' => 'Prénom corrigé'])->assertRedirect();
        $this->actingAs($patient)->get('/profil')->assertSee('Prénom corrigé');
        $this->actingAs($admin)->get('/droits')->assertOk();
    }

    public function test_anonymization_requires_expired_archived_dossier_and_correct_confirmation(): void
    {
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2020-01-01'));
        [$tenant,$admin,$patient,$client] = $this->cabinet();
        $client->delete();
        ClinicalNote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'author_id' => $admin->id, 'body' => 'Note à supprimer']);
        $this->travelTo(Carbon::parse('2026-09-25'));
        $this->actingAs($admin);
        $this->assertTrue(app(Retention::class)->eligible($client));
        $this->post('/droits/clients/'.$client->id.'/effacer', ['confirmation' => 'oui', 'current_password' => 'password'])->assertSessionHasErrors('confirmation');
        $this->assertSame(1, ClinicalNote::count());
        $this->post('/droits/clients/'.$client->id.'/effacer', ['confirmation' => 'ANONYMISER #'.$client->id, 'current_password' => 'password'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, ClinicalNote::count());
        $this->assertNotNull($client->fresh()->anonymized_at);
        $this->assertFalse($patient->fresh()->active);
        $this->assertNull($client->fresh()->user_id);
        $this->post('/corbeille/'.$client->id.'/restaurer')->assertNotFound();
    }

    public function test_retention_hold_and_recent_appointment_prevent_erasure(): void
    {
        $this->travelTo(Carbon::parse('2020-01-01'));
        [$tenant,$admin,$patient,$client] = $this->cabinet();
        $client->delete();
        $this->travelTo(Carbon::parse('2026-09-25'));
        Appointment::create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'title' => 'À venir', 'starts_at' => now()->addDay(), 'duration' => 30]);
        $this->assertFalse(app(Retention::class)->eligible($client));
        $this->actingAs($admin)->post('/droits/clients/'.$client->id.'/conservation', ['retention_hold' => true, 'retention_note' => 'Conservation justifiée'])->assertRedirect();
        $this->assertTrue($client->fresh()->retention_hold);
    }

    public function test_official_definitions_require_source_and_authorization_and_export_roundtrips(): void
    {
        [$tenant,$admin] = $this->cabinet();
        $questions = [];
        for ($i = 1; $i <= 9; $i++) {
            $questions[] = ['id' => 'type'.$i, 'label' => 'Type '.$i, 'type' => 'scale', 'min' => 0, 'max' => 100];
        }
        $this->actingAs($admin)->post('/questionnaires', ['name' => 'Test officiel', 'kind' => 'enneagramme', 'questions' => json_encode($questions)])->assertSessionHasErrors('source_reference');
        $this->post('/questionnaires', ['name' => 'Référentiel autorisé', 'kind' => 'enneagramme', 'questions' => json_encode($questions), 'licensed' => 1, 'source_reference' => 'Manuel fourni par le cabinet'])->assertRedirect()->assertSessionHasNoErrors();
        $definition = AssessmentDefinition::firstOrFail();
        $this->assertSame('Manuel fourni par le cabinet', $definition->source_reference);
        $response = $this->get('/questionnaires/'.$definition->id.'/export')->assertOk();
        $exported = json_decode($response->streamedContent(), true);
        $normalize = static function (array $question): array {
            ksort($question);

            return $question;
        };
        $this->assertSame(array_map($normalize, $questions), array_map($normalize, $exported));
    }

    public function test_charts_escape_labels_and_history_only_includes_published_assessments(): void
    {
        $svg = Charts::radar(['<script>' => ['A' => 10, 'B' => 2, 'C' => 5, 'D' => 8]], 15);
        $this->assertStringNotContainsString('<script>', $svg);
        $this->assertStringContainsString('&lt;script&gt;', $svg);
        $this->assertNotNull(Charts::line([['date' => '01/01', 'scores' => ['A' => 2]], ['date' => '02/01', 'scores' => ['A' => 4]]], 15));
        [$tenant,$admin,$patient,$client] = $this->cabinet();
        $definition = AssessmentDefinition::create(['tenant_id' => $tenant->id, 'family' => (string) Str::uuid(), 'name' => 'Scores', 'kind' => 'gordon', 'version' => 1, 'engine_version' => 'gordon-v1', 'questions' => []]);
        foreach (['publie', 'termine', 'publie'] as $index => $status) {
            $a = Assessment::create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'assigned_by' => $admin->id, 'assessment_definition_id' => $definition->id, 'status' => $status, 'submitted_at' => now()->addDays($index), 'results' => ['kind' => 'gordon', 'scores' => ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4], 'maximum' => 15]]);
            if ($status === 'publie') {
                Interpretation::create(['tenant_id' => $tenant->id, 'assessment_id' => $a->id, 'draft' => 'Texte', 'published_content' => 'Texte', 'published_at' => now()]);
            }
        }
        $this->actingAs($patient)->get('/evaluations/'.$a->id)->assertOk()->assertViewHas('history', fn ($rows) => count($rows) === 2)->assertSee('Évolution entre les passations');
        $this->get('/evaluations/'.$a->id.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_backup_authentication_rejects_tampering(): void
    {
        $directory = sys_get_temp_dir().'/psycho-crypt-'.Str::uuid();
        mkdir($directory, 0700);
        $service = new Backups;
        $key = random_bytes(32);
        file_put_contents($directory.'/input', 'Données confidentielles');
        try {
            $service->encrypt($directory.'/input', $directory.'/backup', $key);
            $this->assertStringNotContainsString('Données confidentielles', file_get_contents($directory.'/backup'));
            $service->decrypt($directory.'/backup', $directory.'/restored', $key);
            $this->assertSame('Données confidentielles', file_get_contents($directory.'/restored'));
            $data = file_get_contents($directory.'/backup');
            $data[strlen($data) - 1] = chr(ord($data[strlen($data) - 1]) ^ 1);
            file_put_contents($directory.'/backup', $data);
            try {
                $service->decrypt($directory.'/backup', $directory.'/invalid', $key);
                $this->fail('Une sauvegarde altérée doit être refusée.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('altérée', $exception->getMessage());
                $this->assertFileDoesNotExist($directory.'/invalid');
            }
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
