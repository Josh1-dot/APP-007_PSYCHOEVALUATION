<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RenderHttpsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['name' => 'Cabinet HTTPS']);
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']));
    }

    public function test_dashboard_assets_use_https_behind_the_proxy(): void
    {
        $this->behindHttpsProxy();

        $this->get('http://cabinet.example.test/')
            ->assertOk()
            ->assertSee('href="https://cabinet.example.test/assets/app.css"', false)
            ->assertSee('src="https://cabinet.example.test/assets/app.js"', false)
            ->assertDontSee('http://cabinet.example.test/assets/', false);
    }

    public function test_local_http_assets_still_work_without_forwarded_headers(): void
    {
        $this->get('http://localhost/')
            ->assertOk()
            ->assertSee('href="http://localhost/assets/app.css"', false)
            ->assertSee('src="http://localhost/assets/app.js"', false);
    }

    public function test_signed_document_download_works_behind_the_https_proxy(): void
    {
        Storage::fake('local');
        $this->behindHttpsProxy();
        $this->post('http://cabinet.example.test/documents', [
            'file' => UploadedFile::fake()->createWithContent('note.txt', 'DOCUMENT_TEST_HTTPS'),
        ])->assertRedirect();

        $document = Document::firstOrFail();
        $response = $this->get('http://cabinet.example.test/documents/'.$document->id.'/lien');
        $response->assertRedirect();
        $signedUrl = $response->headers->get('Location');
        $this->assertStringStartsWith('https://cabinet.example.test/', $signedUrl);

        $internalUrl = 'http://'.substr($signedUrl, strlen('https://'));
        $this->get($internalUrl)->assertOk()->assertStreamedContent('DOCUMENT_TEST_HTTPS');
        $this->get($internalUrl.'&modified=1')->assertForbidden();
    }

    private function behindHttpsProxy(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.2',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ]);
    }
}
