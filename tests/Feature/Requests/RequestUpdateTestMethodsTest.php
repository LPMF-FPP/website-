<?php

namespace Tests\Feature\Requests;

use App\Models\Investigator;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestUpdateTestMethodsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, array<string, mixed>>  $samples
     * @param  array<int, array<string, mixed>>  $suspects
     * @return array<string, mixed>
     */
    private function makeUpdatePayload(Investigator $investigator, array $samples, array $suspects, array $overrides = []): array
    {
        return $overrides + [
            'case_number' => 'CASE-123',
            'to_office' => 'Pusdokkes Polri',
            'suspect_address' => 'Somewhere',
            'investigator_rank' => $investigator->rank,
            'investigator_name' => $investigator->name,
            'investigator_nrp' => $investigator->nrp,
            'investigator_jurisdiction' => $investigator->jurisdiction,
            'investigator_phone' => $investigator->phone,
            'suspects' => $suspects,
            'samples' => $samples,
        ];
    }

    public function test_update_request_updates_existing_sample_test_methods_from_test_types(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $investigator = Investigator::factory()->create(['is_polri' => true]);

        $testRequest = TestRequest::factory()->create([
            'user_id' => $user->id,
            'investigator_id' => $investigator->id,
        ]);

        $sample = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'active_substance' => 'Caffeine',
            'test_methods' => json_encode(['uv_vis']),
            'requested_test_methods' => json_encode(['uv_vis']),
        ]);

        $payload = $this->makeUpdatePayload(
            $investigator,
            [
                [
                    'id' => $sample->id,
                    'short_description' => $sample->short_description,
                    'active_substance' => $sample->active_substance,
                    'package_quantity' => $sample->package_quantity,
                    'unit' => $sample->unit,
                    'test_types' => ['gc_ms'],
                ],
            ],
            [
                ['name' => 'John Doe', 'gender' => 'male', 'age' => 30],
            ]
        );

        $response = $this->actingAs($user)->put(route('requests.update', $testRequest), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $updated = Sample::query()->findOrFail($sample->id);

        $this->assertEqualsCanonicalizing(['gc_ms'], json_decode($updated->test_methods, true) ?? []);
        $this->assertEqualsCanonicalizing(['gc_ms'], json_decode($updated->requested_test_methods, true) ?? []);
    }

    public function test_update_request_creates_new_sample_and_persists_test_methods_from_test_types(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $investigator = Investigator::factory()->create(['is_polri' => true]);

        $testRequest = TestRequest::factory()->create([
            'user_id' => $user->id,
            'investigator_id' => $investigator->id,
        ]);

        $existing = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'active_substance' => 'Caffeine',
            'test_methods' => json_encode(['uv_vis']),
            'requested_test_methods' => json_encode(['uv_vis']),
        ]);

        $payload = $this->makeUpdatePayload(
            $investigator,
            [
                [
                    'id' => $existing->id,
                    'short_description' => $existing->short_description,
                    'active_substance' => $existing->active_substance,
                    'package_quantity' => $existing->package_quantity,
                    'unit' => $existing->unit,
                    'test_types' => ['uv_vis'],
                ],
                [
                    'short_description' => 'Brand New Sample',
                    'active_substance' => 'Nicotine',
                    'package_quantity' => 1,
                    'unit' => 'gram',
                    'test_types' => ['lc_ms'],
                ],
            ],
            [
                ['name' => 'Jane Doe', 'gender' => 'female', 'age' => 28],
            ],
            ['case_number' => 'CASE-456']
        );

        $response = $this->actingAs($user)->put(route('requests.update', $testRequest), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame(2, Sample::where('test_request_id', $testRequest->id)->count());

        $newSample = Sample::query()
            ->where('test_request_id', $testRequest->id)
            ->where('short_description', 'Brand New Sample')
            ->firstOrFail();

        $this->assertEqualsCanonicalizing(['lc_ms'], json_decode($newSample->test_methods, true) ?? []);
        $this->assertEqualsCanonicalizing(['lc_ms'], json_decode($newSample->requested_test_methods, true) ?? []);

        $existing->refresh();
        $this->assertEqualsCanonicalizing(['uv_vis'], json_decode($existing->test_methods, true) ?? []);
        $this->assertEqualsCanonicalizing(['uv_vis'], json_decode($existing->requested_test_methods, true) ?? []);
    }
}
