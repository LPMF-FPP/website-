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
            'letter_date' => '2026-04-30',
            'investigator_rank' => $investigator->rank,
            'investigator_name' => $investigator->name,
            'investigator_nrp' => $investigator->nrp,
            'investigator_jurisdiction' => $investigator->jurisdiction,
            'investigator_phone' => $investigator->phone,
            'suspects' => $suspects,
            'samples' => $samples,
        ];
    }

    public function test_update_request_preserves_existing_sample_technical_fields(): void
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
                    'package_quantity' => $sample->package_quantity,
                    'unit' => $sample->unit,
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

        $this->assertSame('Caffeine', $updated->active_substance);
        $this->assertEqualsCanonicalizing(['uv_vis'], json_decode($updated->test_methods, true) ?? []);
        $this->assertEqualsCanonicalizing(['uv_vis'], json_decode($updated->requested_test_methods, true) ?? []);

        $testRequest->refresh();
        $this->assertSame('2026-04-30', $testRequest->letter_date?->format('Y-m-d'));
    }

    public function test_update_request_creates_new_sample_without_technical_fields(): void
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
                    'package_quantity' => $existing->package_quantity,
                    'unit' => $existing->unit,
                ],
                [
                    'short_description' => 'Brand New Sample',
                    'package_quantity' => 1,
                    'unit' => 'gram',
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

        $this->assertNull($newSample->active_substance);
        $this->assertNull($newSample->test_methods);
        $this->assertNull($newSample->requested_test_methods);

        $existing->refresh();
        $this->assertEqualsCanonicalizing(['uv_vis'], json_decode($existing->test_methods, true) ?? []);
        $this->assertEqualsCanonicalizing(['uv_vis'], json_decode($existing->requested_test_methods, true) ?? []);
    }
}
