<?php

namespace Tests\Feature\Api;

use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GroupedSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $connection = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?? null;
        if ($connection !== 'pgsql') {
            $this->markTestSkipped('Grouped search tests require PostgreSQL (ILIKE).');
        }

        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Grouped search tests require PostgreSQL (ILIKE).');
        }

        config([
            'filesystems.disks.documents' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/disks/documents'),
            ],
            'search.documents_disk' => 'documents',
            'search.photos_disk' => 'public',
        ]);
        Storage::fake('documents');

        $this->ensureTables();
        $this->seedDomainData();
    }

    private function ensureTables(): void
    {
        if (!Schema::hasTable('people')) {
            Schema::create('people', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->text('name');
                $table->text('role')->nullable();
                $table->text('photo_path')->nullable();
                $table->timestampTz('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('cases')) {
            Schema::create('cases', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->text('title');
                $table->text('lp_no');
                $table->timestampTz('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('case_people')) {
            Schema::create('case_people', function (Blueprint $table) {
                $table->unsignedBigInteger('case_id');
                $table->unsignedBigInteger('person_id');
                $table->text('role_in_case')->nullable();
            });
        }

        if (!Schema::hasColumn('documents', 'doc_type')) {
            Schema::table('documents', function (Blueprint $table) {
                if (!Schema::hasColumn('documents', 'doc_type')) {
                    $table->string('doc_type')->nullable();
                }
                if (!Schema::hasColumn('documents', 'ba_no')) {
                    $table->string('ba_no')->nullable();
                }
                if (!Schema::hasColumn('documents', 'title')) {
                    $table->string('title')->nullable();
                }
                if (!Schema::hasColumn('documents', 'lp_no')) {
                    $table->string('lp_no')->nullable();
                }
                if (!Schema::hasColumn('documents', 'doc_date')) {
                    $table->date('doc_date')->nullable();
                }
                if (!Schema::hasColumn('documents', 'file_path')) {
                    $table->string('file_path')->nullable();
                }
            });
        }
    }

    private function seedDomainData(): void
    {
        // Create investigator with name that matches search
        $investigator = \App\Models\Investigator::create([
            'nrp' => '12345678',
            'name' => 'Andi Pratama',
            'rank' => 'IPTU',
            'jurisdiction' => 'Polda Metro Jaya',
            'phone' => '081234567890',
            'folder_key' => '12345678-andi',
        ]);
        
        $user = User::factory()->create();

        // Create test request with suspect name that matches search  
        $testRequest = TestRequest::factory()->create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'request_number' => 'REQ-2025-001',
            'suspect_name' => 'Andi Suspect',
        ]);

        DB::table('documents')->insert([
            [
                'id' => 100,
                'test_request_id' => $testRequest->id,
                'investigator_id' => $testRequest->investigator_id,
                'document_type' => 'lab_report',
                'source' => 'generated',
                'filename' => 'BA-001.pdf',
                'original_filename' => 'BA-001.pdf',
                'path' => 'docs/ba_001.pdf',
                'doc_type' => 'bap',
                'ba_no' => 'BA-001',
                'title' => 'Berita Acara Pemeriksaan Andi',
                'lp_no' => 'LP-001',
                'doc_date' => '2025-01-01',
                'file_path' => 'docs/ba_001.pdf',
                'mime_type' => 'application/pdf',
                'generated_by' => $testRequest->user_id,
                'generated_at' => now()->subDays(3),
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ],
            [
                'id' => 101,
                'test_request_id' => $testRequest->id,
                'investigator_id' => $testRequest->investigator_id,
                'document_type' => 'lab_report',
                'source' => 'generated',
                'filename' => 'BA-002.pdf',
                'original_filename' => 'BA-002.pdf',
                'path' => 'docs/ba_002.pdf',
                'doc_type' => 'ba',
                'ba_no' => 'BA-002',
                'title' => 'Berita Acara Penangkapan',
                'lp_no' => 'LP-002',
                'doc_date' => '2025-02-01',
                'file_path' => 'docs/ba_002.pdf',
                'mime_type' => 'application/pdf',
                'generated_by' => $testRequest->user_id,
                'generated_at' => now()->subDays(2),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
        ]);

        Storage::disk('documents')->put('docs/ba_001.pdf', 'dummy');
        Storage::disk('documents')->put('docs/ba_002.pdf', 'dummy');
    }

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/search?q=an')->assertStatus(401);
    }

    public function test_422_for_invalid_q_too_short(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);

        $this->getJson('/api/search?q=a')->assertStatus(422);
    }

    public function test_422_for_invalid_q_too_long(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);

        $q = str_repeat('A', 81);
        $this->getJson('/api/search?q=' . $q)->assertStatus(422);
    }

    public function test_422_for_invalid_doc_type(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);

        $this->getJson('/api/search?q=ba&doc_type=invalid')->assertStatus(422);
    }

    public function test_422_for_per_page_overflow(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);

        $this->getJson('/api/search?q=ba&per_page_people=26')->assertStatus(422);
        $this->getJson('/api/search?q=ba&per_page_docs=26')->assertStatus(422);
    }

    public function test_200_shape_and_data_present_for_valid_query(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);

        $res = $this->getJson('/api/search?q=Andi');
        $res->assertOk()
            ->assertJsonStructure([
                'query',
                'doc_type',
                'sort',
                'summary' => ['people_total', 'documents_total'],
                'people' => [
                    'pagination' => ['page', 'per_page', 'total', 'last_page'],
                    'data',
                ],
                'documents' => [
                    'pagination' => ['page', 'per_page', 'total', 'last_page'],
                    'data',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, (int) $res->json('summary.people_total'));
    }

    public function test_filter_doc_type_works(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);

        // Search for documents with 'BA' in filename/title - should find 2 documents
        $all = $this->getJson('/api/search?q=BA-&doc_type=all')->assertOk();
        $this->assertSame(2, (int) $all->json('summary.documents_total'));

        // Search with doc_type filter matching document_type 'ba_penerimaan' 
        // First update one document to have ba_penerimaan type
        DB::table('documents')->where('id', 100)->update(['document_type' => 'ba_penerimaan']);
        
        $baFilter = $this->getJson('/api/search?q=BA-&doc_type=ba_penerimaan')->assertOk();
        $this->assertSame(1, (int) $baFilter->json('summary.documents_total'));
    }

    public function test_people_and_docs_pagination_independent(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);

        for ($i = 3; $i <= 25; $i++) {
            DB::table('people')->insert([
                'id' => $i,
                'name' => 'Andi ' . $i,
                'role' => 'tersangka',
                'photo_path' => null,
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $res = $this->getJson('/api/search?q=Andi&per_page_people=10&page_people=2&per_page_docs=10&page_docs=1');
        $res->assertOk()
            ->assertJsonPath('people.pagination.page', 2)
            ->assertJsonPath('documents.pagination.page', 1);
    }

    public function test_input_percent_underscore_does_not_error(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);

        $this->getJson('/api/search?q=%%')->assertOk();
        $this->getJson('/api/search?q=__')->assertOk();
    }

    public function test_403_when_search_policy_enforced_and_denied(): void
    {
        config(['search.enforce_search_policy' => true]);

        Gate::define('search', fn ($user) => false);

        $u = User::factory()->create();
        $this->actingAs($u);

        $this->getJson('/api/search?q=Andi')->assertStatus(403);
    }

    public function test_download_route_requires_auth_and_policy(): void
    {
        $this->getJson('/api/documents/100/download')->assertStatus(401);

        $u = User::factory()->create(['email' => 'staff@example.com']);
        $this->actingAs($u);

        $this->getJson('/api/documents/100/download')->assertOk();

        config(['search.enforce_download_policy' => true]);
        Gate::define('documents.download', fn ($user) => $user->email === 'admin@example.com');

        $this->getJson('/api/documents/100/download')->assertStatus(403);

        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->actingAs($admin);

        $this->getJson('/api/documents/100/download')->assertOk();
    }
}
