<?php

namespace Tests\Feature\Requests;

use App\Models\Investigator;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestUpdateSampleOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_request_rejects_sample_id_not_belonging_to_request(): void
    {
        $user = User::factory()->create();
        $investigator = Investigator::factory()->create(['is_polri' => true]);

        $requestA = TestRequest::factory()->create([
            'user_id' => $user->id,
            'investigator_id' => $investigator->id,
        ]);

        $sampleA = Sample::factory()->create([
            'test_request_id' => $requestA->id,
            'active_substance' => 'Caffeine',
            'test_methods' => json_encode(['uv_vis']),
            'requested_test_methods' => json_encode(['uv_vis']),
        ]);

        $requestB = TestRequest::factory()->create([
            'user_id' => $user->id,
            'investigator_id' => $investigator->id,
        ]);

        $foreignSample = Sample::factory()->create([
            'test_request_id' => $requestB->id,
            'active_substance' => 'Nicotine',
            'test_methods' => json_encode(['uv_vis']),
            'requested_test_methods' => json_encode(['uv_vis']),
        ]);

        $payload = [
            'case_number' => 'CASE-OWNERSHIP',
            'to_office' => 'Pusdokkes Polri',
            'suspect_address' => 'Somewhere',
            'investigator_rank' => $investigator->rank,
            'investigator_name' => $investigator->name,
            'investigator_nrp' => $investigator->nrp,
            'investigator_jurisdiction' => $investigator->jurisdiction,
            'investigator_phone' => $investigator->phone,
            'suspects' => [
                ['name' => 'Jane Doe', 'gender' => 'female', 'age' => 28],
            ],
            'samples' => [
                [
                    'id' => $foreignSample->id,
                    'short_description' => $foreignSample->short_description,
                    'active_substance' => $foreignSample->active_substance,
                    'package_quantity' => $foreignSample->package_quantity,
                    'unit' => $foreignSample->unit,
                    'test_types' => ['gc_ms'],
                ],
            ],
        ];

        $response = $this->actingAs($user)->put(route('requests.update', $requestA), $payload);

        $response->assertRedirect();
        $response->assertSessionHasErrors('samples.0.id');

        $this->assertSame(1, Sample::where('test_request_id', $requestA->id)->count());
        $this->assertNotNull(Sample::find($sampleA->id));
    }
}
