<?php

namespace Tests\Feature\Delivery;

use App\Models\Delivery;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateSuratPengantarTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_update_surat_pengantar(): void
    {
        $request = TestRequest::factory()->create();
        $delivery = Delivery::factory()->create(['request_id' => $request->id]);

        $response = $this->patch(route('delivery.update-surat-pengantar', $delivery), [
            'surat_pengantar_number' => 'SP/001/2026',
            'surat_pengantar_date' => '2026-01-15',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_update_surat_pengantar(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create();
        $delivery = Delivery::factory()->create(['request_id' => $request->id]);

        $response = $this->actingAs($user)
            ->patch(route('delivery.update-surat-pengantar', $delivery), [
                'surat_pengantar_number' => 'SP/001/2026',
                'surat_pengantar_date' => '2026-01-15',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'has_surat_pengantar' => true,
            'surat_pengantar_number' => 'SP/001/2026',
            'surat_pengantar_date' => '2026-01-15',
        ]);
    }

    public function test_surat_pengantar_number_is_required(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create();
        $delivery = Delivery::factory()->create(['request_id' => $request->id]);

        $response = $this->actingAs($user)
            ->patch(route('delivery.update-surat-pengantar', $delivery), [
                'surat_pengantar_number' => '',
                'surat_pengantar_date' => '2026-01-15',
            ]);

        $response->assertSessionHasErrors('surat_pengantar_number');
    }

    public function test_surat_pengantar_date_is_required(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create();
        $delivery = Delivery::factory()->create(['request_id' => $request->id]);

        $response = $this->actingAs($user)
            ->patch(route('delivery.update-surat-pengantar', $delivery), [
                'surat_pengantar_number' => 'SP/001/2026',
                'surat_pengantar_date' => '',
            ]);

        $response->assertSessionHasErrors('surat_pengantar_date');
    }
}
