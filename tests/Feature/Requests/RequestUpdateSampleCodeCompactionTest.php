<?php

namespace Tests\Feature\Requests;

use App\Models\Investigator;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\Sequence;
use App\Models\TestRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestUpdateSampleCodeCompactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        settings_fake([
            'numbering.sample_code.pattern' => 'LS{SEQ:3}I{YYYY}',
            'numbering.sample_code.reset' => 'yearly',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $samples
     * @return array<string, mixed>
     */
    private function makeUpdatePayload(Investigator $investigator, array $samples): array
    {
        return [
            'case_number' => 'CASE-COMPACT',
            'to_office' => 'Pusdokkes Polri',
            'suspect_address' => 'Somewhere',
            'investigator_rank' => $investigator->rank,
            'investigator_name' => $investigator->name,
            'investigator_nrp' => $investigator->nrp,
            'investigator_jurisdiction' => $investigator->jurisdiction,
            'investigator_phone' => $investigator->phone,
            'suspects' => [
                ['name' => 'John Doe', 'gender' => 'male', 'age' => 30],
            ],
            'samples' => $samples,
        ];
    }

    public function test_update_request_compacts_unlocked_sample_codes_after_deleting_middle_sample(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $investigator = Investigator::factory()->create(['is_polri' => true]);

        $testRequest = TestRequest::factory()->create([
            'user_id' => $user->id,
            'investigator_id' => $investigator->id,
        ]);

        $sample1 = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'sample_code' => null,
            'active_substance' => 'Caffeine',
        ]);
        $sample2 = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'sample_code' => null,
            'active_substance' => 'Caffeine',
        ]);
        $sample3 = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'sample_code' => null,
            'active_substance' => 'Caffeine',
        ]);

        $otherRequest = TestRequest::factory()->create([
            'user_id' => $user->id,
            'investigator_id' => $investigator->id,
        ]);

        $sample4 = Sample::factory()->create([
            'test_request_id' => $otherRequest->id,
            'sample_code' => null,
            'active_substance' => 'Caffeine',
        ]);

        $this->assertSame('LS001I'.now()->year, $sample1->sample_code);
        $this->assertSame('LS002I'.now()->year, $sample2->sample_code);
        $this->assertSame('LS003I'.now()->year, $sample3->sample_code);
        $this->assertSame('LS004I'.now()->year, $sample4->sample_code);

        $payload = $this->makeUpdatePayload($investigator, [
            [
                'id' => $sample1->id,
                'short_description' => $sample1->short_description,
                'active_substance' => $sample1->active_substance,
                'package_quantity' => $sample1->package_quantity,
                'unit' => $sample1->unit,
                'test_types' => ['uv_vis'],
            ],
            [
                'id' => $sample3->id,
                'short_description' => $sample3->short_description,
                'active_substance' => $sample3->active_substance,
                'package_quantity' => $sample3->package_quantity,
                'unit' => $sample3->unit,
                'test_types' => ['uv_vis'],
            ],
        ]);

        $response = $this->actingAs($user)->put(route('requests.update', $testRequest), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertNull(Sample::find($sample2->id), 'Middle sample should be deleted');

        $this->assertSame('LS001I'.now()->year, $sample1->fresh()->sample_code);
        $this->assertSame('LS002I'.now()->year, $sample3->fresh()->sample_code);
        $this->assertSame('LS003I'.now()->year, $sample4->fresh()->sample_code);

        $this->assertSame(
            3,
            (int) Sequence::query()
                ->where('scope', 'sample_code')
                ->where('bucket', (string) now()->year)
                ->value('current_value')
        );
    }

    public function test_update_request_does_not_rename_locked_samples_when_compacting_sample_codes(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $investigator = Investigator::factory()->create(['is_polri' => true]);

        $testRequest = TestRequest::factory()->create([
            'user_id' => $user->id,
            'investigator_id' => $investigator->id,
        ]);

        $sample1 = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'sample_code' => null,
            'active_substance' => 'Caffeine',
        ]);
        $sample2 = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'sample_code' => null,
            'active_substance' => 'Caffeine',
        ]);
        $sample3 = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'sample_code' => null,
            'active_substance' => 'Caffeine',
        ]);

        $otherRequest = TestRequest::factory()->create([
            'user_id' => $user->id,
            'investigator_id' => $investigator->id,
        ]);

        $sample4 = Sample::factory()->create([
            'test_request_id' => $otherRequest->id,
            'sample_code' => null,
            'active_substance' => 'Caffeine',
        ]);

        SampleTestProcess::factory()->create([
            'sample_id' => $sample3->id,
        ]);

        $payload = $this->makeUpdatePayload($investigator, [
            [
                'id' => $sample1->id,
                'short_description' => $sample1->short_description,
                'active_substance' => $sample1->active_substance,
                'package_quantity' => $sample1->package_quantity,
                'unit' => $sample1->unit,
                'test_types' => ['uv_vis'],
            ],
            [
                'id' => $sample3->id,
                'short_description' => $sample3->short_description,
                'active_substance' => $sample3->active_substance,
                'package_quantity' => $sample3->package_quantity,
                'unit' => $sample3->unit,
                'test_types' => ['uv_vis'],
            ],
        ]);

        $response = $this->actingAs($user)->put(route('requests.update', $testRequest), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertNull(Sample::find($sample2->id), 'Middle sample should be deleted');

        $this->assertSame('LS003I'.now()->year, $sample3->fresh()->sample_code);
        $this->assertSame('LS004I'.now()->year, $sample4->fresh()->sample_code);

        $this->assertSame(
            4,
            (int) Sequence::query()
                ->where('scope', 'sample_code')
                ->where('bucket', (string) now()->year)
                ->value('current_value')
        );
    }

    public function test_update_request_compacts_the_correct_bucket_when_editing_old_request(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-02-10 10:00:00'));

        /** @var User $user */
        $user = User::factory()->create();
        $investigator = Investigator::factory()->create(['is_polri' => true]);

        $testRequest = TestRequest::factory()->create([
            'user_id' => $user->id,
            'investigator_id' => $investigator->id,
        ]);

        $sample1 = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'sample_code' => null,
            'active_substance' => 'Caffeine',
        ]);
        $sample2 = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'sample_code' => null,
            'active_substance' => 'Caffeine',
        ]);
        $sample3 = Sample::factory()->create([
            'test_request_id' => $testRequest->id,
            'sample_code' => null,
            'active_substance' => 'Caffeine',
        ]);

        $otherRequest = TestRequest::factory()->create([
            'user_id' => $user->id,
            'investigator_id' => $investigator->id,
        ]);

        $sample4 = Sample::factory()->create([
            'test_request_id' => $otherRequest->id,
            'sample_code' => null,
            'active_substance' => 'Caffeine',
        ]);

        $this->assertSame('LS001I2025', $sample1->sample_code);
        $this->assertSame('LS002I2025', $sample2->sample_code);
        $this->assertSame('LS003I2025', $sample3->sample_code);
        $this->assertSame('LS004I2025', $sample4->sample_code);

        // Now simulate editing in a later year.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-10 10:00:00'));

        $payload = $this->makeUpdatePayload($investigator, [
            [
                'id' => $sample1->id,
                'short_description' => $sample1->short_description,
                'active_substance' => $sample1->active_substance,
                'package_quantity' => $sample1->package_quantity,
                'unit' => $sample1->unit,
                'test_types' => ['uv_vis'],
            ],
            [
                'id' => $sample3->id,
                'short_description' => $sample3->short_description,
                'active_substance' => $sample3->active_substance,
                'package_quantity' => $sample3->package_quantity,
                'unit' => $sample3->unit,
                'test_types' => ['uv_vis'],
            ],
        ]);

        $response = $this->actingAs($user)->put(route('requests.update', $testRequest), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertNull(Sample::find($sample2->id), 'Middle sample should be deleted');

        $this->assertSame('LS001I2025', $sample1->fresh()->sample_code);
        $this->assertSame('LS002I2025', $sample3->fresh()->sample_code);
        $this->assertSame('LS003I2025', $sample4->fresh()->sample_code);

        $this->assertSame(
            3,
            (int) Sequence::query()
                ->where('scope', 'sample_code')
                ->where('bucket', '2025')
                ->value('current_value')
        );
    }
}
