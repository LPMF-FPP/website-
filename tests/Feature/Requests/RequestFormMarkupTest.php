<?php

namespace Tests\Feature\Requests;

use App\Models\Investigator;
use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RequestFormMarkupTest extends TestCase
{
    use DatabaseTransactions;

    public function test_create_form_contains_sample_fields(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('requests.create'));

        $response->assertOk();
        $this->assertElementIsInsideForm($response, 'request-create-form', 'samples-container');
    }

    public function test_create_form_orders_existing_polri_investigators_by_rank_then_name(): void
    {
        Investigator::factory()->create([
            'name' => 'Zara Akp',
            'rank' => 'AKP',
            'is_polri' => true,
        ]);
        Investigator::factory()->create([
            'name' => 'Andi Bripda',
            'rank' => 'BRIPDA',
            'is_polri' => true,
        ]);
        Investigator::factory()->create([
            'name' => 'Budi Bripka',
            'rank' => 'BRIPKA',
            'is_polri' => true,
        ]);
        Investigator::factory()->create([
            'name' => 'Citra Ipda',
            'rank' => 'IPDA',
            'is_polri' => true,
        ]);
        Investigator::factory()->create([
            'name' => 'Dedi Kompol',
            'rank' => 'KOMPOL',
            'is_polri' => true,
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('requests.create'));

        $response->assertOk();
        $html = $response->getContent();
        $positions = array_map(
            static fn (string $label): int|false => strpos($html, $label),
            [
                'BRIPDA Andi Bripda',
                'BRIPKA Budi Bripka',
                'IPDA Citra Ipda',
                'AKP Zara Akp',
                'KOMPOL Dedi Kompol',
            ]
        );

        foreach ($positions as $position) {
            $this->assertNotFalse($position);
        }

        $this->assertLessThan($positions[1], $positions[0]);
        $this->assertLessThan($positions[2], $positions[1]);
        $this->assertLessThan($positions[3], $positions[2]);
        $this->assertLessThan($positions[4], $positions[3]);
    }

    public function test_edit_form_contains_sample_fields(): void
    {
        $user = User::factory()->create();
        $testRequest = TestRequest::factory()->create(['user_id' => $user->id]);
        Sample::factory()->create(['test_request_id' => $testRequest->id]);

        $response = $this->actingAs($user)
            ->get(route('requests.edit', $testRequest));

        $response->assertOk();
        $this->assertElementIsInsideForm($response, 'request-edit-form', 'samples-container');
    }

    private function assertElementIsInsideForm(
        TestResponse $response,
        string $formId,
        string $elementId
    ): void {
        $document = new DOMDocument;

        libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();

        $nodes = (new DOMXPath($document))->query(
            sprintf('//*[@id="%s"]//*[@id="%s"]', $formId, $elementId)
        );

        $this->assertSame(
            1,
            $nodes?->length,
            "Element #{$elementId} harus berada di dalam form #{$formId}."
        );
    }
}
