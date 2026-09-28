<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\AssessmentDefinition;
use App\Models\Client;
use App\Models\ClinicalNote;
use App\Models\Comparison;
use App\Models\Consent;
use App\Models\Document;
use App\Models\Letter;
use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Scoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $patient;

    private Client $client;

    private AssessmentDefinition $definition;

    private Assessment $assessment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Cabinet Test']);
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->patient = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'patient']);
        $this->client = Client::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->patient->id, 'first_name' => 'Alice', 'last_name' => 'Exemple', 'email' => $this->patient->email]);
        $this->definition = AssessmentDefinition::create(['tenant_id' => $this->tenant->id, 'family' => (string) Str::uuid(), 'name' => 'Bilan test', 'kind' => 'personnalise', 'version' => 1, 'questions' => [['id' => 'ressenti', 'label' => 'Votre ressenti', 'type' => 'text', 'required' => true], ['id' => 'energie', 'label' => 'Énergie', 'type' => 'scale', 'min' => 0, 'max' => 10, 'required' => true]]]);
        $this->assessment = Assessment::create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id, 'assigned_by' => $this->admin->id, 'assessment_definition_id' => $this->definition->id]);
    }

    private function consent(): void
    {
        Consent::create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id, 'version' => config('psycho.consent_version'), 'text' => 'Test', 'accepted_at' => now()]);
    }

    public function test_guest_and_login_and_disabled_account(): void
    {
        $this->get('/')->assertRedirect('/connexion');
        $this->get('/connexion')->assertOk();
        $this->post('/connexion', ['email' => $this->admin->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->post('/connexion', ['email' => $this->admin->email, 'password' => 'password'])->assertRedirect('/');
        $this->get('/')->assertOk();
        $this->admin->update(['active' => false]);
        $this->get('/')->assertForbidden();
    }

    public function test_all_professional_pages_render(): void
    {
        $this->actingAs($this->admin);
        foreach (['/', '/clients', '/clients/'.$this->client->id, '/evaluations', '/evaluations/'.$this->assessment->id, '/organisations', '/questionnaires', '/calendrier', '/messagerie', '/documents', '/courriers', '/comparateur', '/espace-travail', '/guide', '/corbeille', '/administration', '/profil'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_patient_consent_submission_lock_and_publication(): void
    {
        $this->actingAs($this->patient);
        $url = '/evaluations/'.$this->assessment->id;
        $this->putJson($url.'/reponses', ['answers' => ['ressenti' => 'Privé', 'energie' => 5]])->assertUnprocessable();
        $this->post('/consentement', ['accepted' => '1'])->assertRedirect();
        $this->get($url)->assertOk()->assertSee('Votre questionnaire')->assertSee('Sauvegarde automatique active');
        $this->putJson($url.'/reponses', ['answers' => ['ressenti' => 'Privé']])->assertOk();
        $this->assertStringNotContainsString('Privé', DB::table('assessments')->value('answers'));
        $this->putJson($url.'/reponses', ['answers' => ['ressenti' => 'Privé'], 'submit' => 1])->assertUnprocessable();
        $this->putJson($url.'/reponses', ['answers' => ['ressenti' => 'Privé', 'energie' => 5], 'submit' => 1])->assertOk();
        $this->assertSame('termine', $this->assessment->fresh()->status);
        $this->putJson($url.'/reponses', ['answers' => ['ressenti' => 'Modifié', 'energie' => 2]])->assertStatus(409);
        $this->get($url.'/pdf')->assertForbidden();
        $this->post($url.'/publier', ['reviewed' => 1])->assertForbidden();
        $this->actingAs($this->admin)->post($url.'/interpretation', ['draft' => 'SECRET_BROUILLON'])->assertRedirect();
        $this->actingAs($this->patient)->get($url)->assertOk()->assertDontSee('SECRET_BROUILLON');
        $this->actingAs($this->admin)->post($url.'/publier', ['reviewed' => 1])->assertRedirect();
        $this->actingAs($this->patient)->get($url)->assertSee('SECRET_BROUILLON');
        $this->get($url.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->admin)->post($url.'/interpretation', ['draft' => 'NOUVEAU_BROUILLON'])->assertRedirect();
        $this->actingAs($this->patient)->get($url)->assertSee('SECRET_BROUILLON')->assertDontSee('NOUVEAU_BROUILLON');
        $this->actingAs($this->admin)->post($url.'/depublier')->assertRedirect();
        $this->actingAs($this->patient)->get($url)->assertDontSee('SECRET_BROUILLON');
        $this->get($url.'/pdf')->assertForbidden();
    }

    public function test_cross_tenant_and_cross_patient_access_are_denied(): void
    {
        $t = Tenant::create(['name' => 'Autre cabinet']);
        $other = User::factory()->create(['tenant_id' => $t->id, 'role' => 'admin']);
        $this->actingAs($other)->get('/clients/'.$this->client->id)->assertNotFound();
        $this->get('/evaluations/'.$this->assessment->id)->assertNotFound();
        $this->post('/evaluations', ['client_id' => $this->client->id, 'assessment_definition_id' => $this->definition->id])->assertNotFound();
        $patient = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'patient']);
        $this->actingAs($patient)->get('/evaluations/'.$this->assessment->id)->assertForbidden();
        $this->get('/clients/'.$this->client->id)->assertForbidden();
        $this->get('/clients')->assertForbidden();
        $this->get('/administration')->assertForbidden();
    }

    public function test_revocation_blocks_autosave(): void
    {
        $this->consent();
        $this->actingAs($this->patient)->post('/consentement/retrait')->assertRedirect();
        $this->putJson('/evaluations/'.$this->assessment->id.'/reponses', ['answers' => ['ressenti' => 'x']])->assertUnprocessable();
    }

    public function test_counsellor_cannot_see_notes_or_publish(): void
    {
        ClinicalNote::create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id, 'author_id' => $this->admin->id, 'body' => 'NOTE_CLINIQUE_SECRETE']);
        $u = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'conseiller']);
        $this->actingAs($u)->get('/clients/'.$this->client->id)->assertOk()->assertDontSee('NOTE_CLINIQUE_SECRETE');
        $this->post('/clients/'.$this->client->id.'/notes', ['body' => 'note'])->assertForbidden();
        $this->post('/evaluations/'.$this->assessment->id.'/publier', ['reviewed' => 1])->assertForbidden();
    }

    public function test_document_is_encrypted_private_and_signature_does_not_bypass_access(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin);
        $this->post('/documents', ['file' => UploadedFile::fake()->createWithContent('note.txt', 'CONTENU_CONFIDENTIEL'), 'client_id' => $this->client->id, 'shared' => 1])->assertRedirect();
        $doc = Document::firstOrFail();
        $this->assertStringNotContainsString('CONTENU_CONFIDENTIEL', Storage::disk('local')->get($doc->path));
        $signed = URL::temporarySignedRoute('documents.download', now()->addMinutes(5), ['document' => $doc->id]);
        $this->actingAs($this->patient)->get($signed)->assertOk()->assertStreamedContent('CONTENU_CONFIDENTIEL');
        $this->get('/documents/'.$doc->id.'/telecharger')->assertForbidden();
        $this->get(URL::temporarySignedRoute('documents.download', now()->subMinute(), ['document' => $doc->id]))->assertForbidden();
        $other = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'patient']);
        $this->actingAs($other)->get($signed)->assertForbidden();
    }

    public function test_company_has_only_organization_documents(): void
    {
        $o = Organization::create(['tenant_id' => $this->tenant->id, 'name' => 'Entreprise']);
        $this->client->update(['organization_id' => $o->id]);
        $u = User::factory()->create(['tenant_id' => $this->tenant->id, 'organization_id' => $o->id, 'role' => 'entreprise']);
        $d = Document::create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id, 'organization_id' => $o->id, 'uploaded_by' => $this->admin->id, 'name' => 'DOCUMENT_PATIENT', 'path' => 'test', 'size' => 1, 'mime' => 'text/plain', 'shared' => true]);
        $this->actingAs($u)->get('/')->assertOk();
        $this->get('/documents')->assertOk()->assertDontSee('DOCUMENT_PATIENT');
        $this->get('/documents/'.$d->id.'/lien')->assertForbidden();
        $this->get('/evaluations/'.$this->assessment->id)->assertForbidden();
        $this->post('/messagerie', ['recipient_id' => $this->patient->id, 'body' => 'test'])->assertForbidden();
    }

    public function test_gordon_grid_scores_and_boolean_normalization(): void
    {
        $questions = [];
        foreach (['A', 'B', 'C', 'D'] as $dim) {
            for ($i = 1; $i <= 15; $i++) {
                $questions[] = ['id' => $dim.$i, 'label' => 'Test '.$dim.$i, 'type' => 'boolean', 'dimension' => $dim];
            }
        }
        $d = new AssessmentDefinition(['kind' => 'gordon', 'engine_version' => 'gordon-v1', 'version' => 2, 'questions' => $questions]);
        $s = new Scoring;
        $answers = array_fill_keys(array_column($questions, 'id'), '0');
        foreach (range(1, 15) as $i) {
            $answers['A'.$i] = '1';
        }$answers['B1'] = true;
        $result = $s->calculate($d, $s->validate($d, $answers, true));
        $this->assertSame(['A' => 15, 'B' => 1, 'C' => 0, 'D' => 0], $result['scores']);
        $this->assertSame(2, $result['definition_version']);
    }

    public function test_questionnaire_new_version_does_not_modify_existing_assessments(): void
    {
        $this->actingAs($this->admin)->post('/questionnaires', ['name' => 'Nouvelle version', 'kind' => 'personnalise', 'previous_id' => $this->definition->id, 'questions' => json_encode([['id' => 'nouveau', 'label' => 'Nouvelle question', 'type' => 'text']])])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $this->assessment->fresh()->definition->version);
        $this->assertSame(2, AssessmentDefinition::max('version'));
        $this->post('/questionnaires', ['name' => 'Grille invalide', 'kind' => 'gordon', 'is_demo' => true, 'questions' => json_encode([['id' => 'a', 'label' => 'Question', 'type' => 'boolean', 'dimension' => 'A']])])->assertSessionHasErrors('definition');
    }

    public function test_archiving_disables_patient_and_pages_keep_rendering(): void
    {
        $this->actingAs($this->admin)->delete('/clients/'.$this->client->id)->assertRedirect('/clients');
        $this->assertFalse($this->patient->fresh()->active);
        $this->get('/')->assertOk();
        $this->get('/evaluations')->assertOk();
        $this->get('/corbeille')->assertOk();
        $this->post('/corbeille/'.$this->client->id.'/restaurer')->assertRedirect();
        $this->assertNotNull(Client::find($this->client->id));
    }

    public function test_modules_mutations_and_pdfs(): void
    {
        $this->actingAs($this->admin);
        $this->post('/organisations', ['name' => 'Nouveau partenaire'])->assertRedirect();
        $this->post('/calendrier', ['client_id' => $this->client->id, 'title' => 'Suivi', 'starts_at' => '2027-01-01 10:00', 'duration' => 45])->assertRedirect();
        $this->post('/calendrier/'.Appointment::first()->id.'/annuler')->assertRedirect();
        $this->post('/messagerie', ['recipient_id' => $this->patient->id, 'body' => 'Message test'])->assertRedirect();
        $this->get('/messagerie')->assertSee('Message test');
        $this->post('/courriers', ['client_id' => $this->client->id, 'subject' => 'Objet', 'body' => 'Contenu'])->assertRedirect();
        $this->get('/courriers/'.Letter::first()->id.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->post('/espace-travail', ['title' => 'Préparation', 'body' => 'Mes notes'])->assertRedirect();
        $this->get('/espace-travail')->assertSee('Mes notes');
    }

    public function test_comparison_export_and_account_creation(): void
    {
        $this->assessment->update(['status' => 'termine', 'results' => ['kind' => 'personnalise', 'answers' => ['ressenti' => 'Test']]]);
        $second = Assessment::create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id, 'assigned_by' => $this->admin->id, 'assessment_definition_id' => $this->definition->id, 'status' => 'termine', 'results' => ['kind' => 'personnalise', 'answers' => ['ressenti' => 'Autre']]]);
        $this->actingAs($this->admin)->post('/comparateur', ['first_assessment_id' => $this->assessment->id, 'second_assessment_id' => $second->id, 'analysis' => 'Analyse comparative'])->assertRedirect()->assertSessionHasNoErrors();
        $comparison = Comparison::firstOrFail();
        $this->get('/comparateur')->assertOk()->assertSee('Analyse comparative');
        $this->get('/comparateur/'.$comparison->id.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->post('/administration/utilisateurs', ['name' => 'Nouveau psy', 'email' => 'new@example.test', 'role' => 'psychologue', 'password' => 'Password-Test-123!'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('psychologue', User::where('email', 'new@example.test')->firstOrFail()->role);
        $this->actingAs($this->patient)->get('/comparateur/'.$comparison->id.'/pdf')->assertForbidden();
    }

    public function test_patient_cannot_inject_html_and_score_stays_hidden_until_publication(): void
    {
        $this->assessment->update(['status' => 'termine', 'results' => ['kind' => 'gordon', 'scores' => ['A' => 15], 'maximum' => 15]]);
        $this->actingAs($this->patient)->get('/evaluations/'.$this->assessment->id)->assertDontSee('Résultats de la passation');
        $this->actingAs($this->admin)->post('/evaluations/'.$this->assessment->id.'/interpretation', ['draft' => "<script>alert('xss')</script>\n\nTexte relu"]);
        $this->post('/evaluations/'.$this->assessment->id.'/publier', ['reviewed' => 1]);
        $this->actingAs($this->patient)->get('/evaluations/'.$this->assessment->id)->assertSee('Résultats de la passation')->assertDontSee('<script>alert', false)->assertSee('Texte relu');
    }

    public function test_ai_is_opt_in_and_never_publishes_or_sends_identity(): void
    {
        $this->assessment->update(['status' => 'termine', 'results' => ['kind' => 'personnalise', 'answers' => ['free' => 'SECRET_LIBRE']]]);
        $this->actingAs($this->admin)->post('/evaluations/'.$this->assessment->id.'/ia', ['authorized' => 1])->assertSessionHasErrors('ai');
        config(['psycho.ai_enabled' => true, 'psycho.ai_endpoint' => 'https://example.test/chat/completions', 'psycho.ai_key' => 'fake', 'psycho.ai_model' => 'test']);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'Brouillon IA']]]])]);
        $this->post('/evaluations/'.$this->assessment->id.'/ia', ['authorized' => 1])->assertSessionHasNoErrors();
        $this->assertSame('termine', $this->assessment->fresh()->status);
        $this->assertNull($this->assessment->fresh()->interpretation->published_at);
        Http::assertSent(fn ($request) => ! str_contains($request->body(), 'SECRET_LIBRE') && ! str_contains($request->body(), $this->client->full_name));
    }
}
