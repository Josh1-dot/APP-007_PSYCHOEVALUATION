<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EnneagramDemoForms;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EnneagramMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_migration_roundtrip_preserves_legacy_and_encrypted_history(): void
    {
        $tenant = Tenant::create(['name' => 'Migration locale']);
        $professional = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
        $form = app(EnneagramDemoForms::class)->create($tenant, $professional)->first();
        $client = Client::create(['tenant_id' => $tenant->id, 'first_name' => 'Migration', 'last_name' => 'Test', 'email' => 'migration@example.test']);
        $assessment = Assessment::create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'assigned_by' => $professional->id, 'assessment_definition_id' => $form->id]);
        $assessment->update(['answers' => array_fill_keys(array_column($form->questions, 'id'), 3), 'results' => ['historical' => 'unchanged']]);
        $before = (array) DB::table('assessments')->where('id', $assessment->id)->first();
        $questions = DB::table('assessment_definitions')->where('id', $form->id)->value('questions');
        $migration = require database_path('migrations/2026_10_02_233312_add_enneagram_form_metadata_to_assessment_definitions.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('assessment_definitions', 'form_key'));
        DB::table('assessment_definitions')->where('id', $form->id)->update(['engine_version' => 'self-report-v1']);
        DB::table('assessment_definitions')->where('id', '!=', $form->id)->update(['is_demo' => false]);
        $migration->up();
        $this->assertSame('LEGACY', DB::table('assessment_definitions')->where('id', $form->id)->value('form_key'));
        $this->assertSame('DEMO', DB::table('assessment_definitions')->where('id', $form->id)->value('content_status'));
        $this->assertSame(2, DB::table('assessment_definitions')->where('content_status', 'DRAFT')->count());
        $this->assertSame($questions, DB::table('assessment_definitions')->where('id', $form->id)->value('questions'));
        $this->assertSame($before, (array) DB::table('assessments')->where('id', $assessment->id)->first());
        $this->assertSame(['historical' => 'unchanged'], $assessment->fresh()->results);
    }

    public function test_form_version_migration_preserves_existing_global_versions(): void
    {
        $tenant = Tenant::create(['name' => 'Historical versions']);
        $author = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
        $forms = app(EnneagramDemoForms::class)->create($tenant, $author);
        foreach ($forms as $index => $form) {
            DB::table('assessment_definitions')->where('id', $form->id)->update(['version' => $index + 1]);
        }
        $migration = require database_path('migrations/2026_10_04_165857_scope_definition_versions_to_enneagram_forms.php');
        $migration->down();
        $before = DB::table('assessment_definitions')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $migration->up();
        $after = DB::table('assessment_definitions')->orderBy('id')->get()->map(function ($row) {
            $data = (array) $row;
            unset($data['version_scope']);

            return $data;
        })->all();
        $this->assertSame($before, $after);
        $this->assertSame(['A', 'B', 'C'], DB::table('assessment_definitions')->orderBy('id')->pluck('version_scope')->all());
        $migration->down();
        $this->assertFalse(Schema::hasColumn('assessment_definitions', 'version_scope'));
        $migration->up();
    }

    public function test_rollback_refuses_to_discard_shared_form_versions(): void
    {
        $tenant = Tenant::create(['name' => 'Shared versions']);
        $author = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
        app(EnneagramDemoForms::class)->create($tenant, $author);
        DB::table('assessment_definitions')->update(['version' => 1]);
        $migration = require database_path('migrations/2026_10_04_165857_scope_definition_versions_to_enneagram_forms.php');
        try {
            $migration->down();
            $this->fail('Rollback must preserve all forms.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Rollback impossible', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('assessment_definitions', 'version_scope'));
        $this->assertSame(3, DB::table('assessment_definitions')->count());
        foreach (DB::table('assessment_definitions')->orderBy('id')->pluck('id') as $index => $id) {
            DB::table('assessment_definitions')->where('id', $id)->update(['version' => $index + 1]);
        }
    }
}
