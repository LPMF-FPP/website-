<?php

namespace Tests\Browser\Labels;

use App\Models\Sample;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LabelManagementTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_user_can_view_labels_section_on_request_page(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create(['user_id' => $user->id]);
        Sample::factory()->create(['test_request_id' => $request->id]);

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->waitForText('Permintaan Pengujian')
                ->assertSee('Label Barang Bukti');
        });
    }

    public function test_request_show_page_has_samples_section(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create(['user_id' => $user->id]);
        $sample = Sample::factory()->create([
            'test_request_id' => $request->id,
            'sample_code' => 'SAMP-TEST-001',
        ]);

        $this->browse(function (Browser $browser) use ($user, $request, $sample) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->waitForText('Permintaan Pengujian')
                ->assertSee('Daftar Sampel')
                ->assertSee($sample->sample_code);
        });
    }

    public function test_request_show_displays_investigator_info(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create(['user_id' => $user->id]);

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->waitForText('Permintaan Pengujian')
                ->assertSee('Data Penyidik & Tersangka')
                ->assertSee($request->investigator->name);
        });
    }

    public function test_empty_samples_shows_placeholder_text(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create(['user_id' => $user->id]);
        // Don't create any samples

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->waitForText('Permintaan Pengujian')
                ->assertSee('Belum ada sampel terdaftar');
        });
    }
}
