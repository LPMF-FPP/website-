<?php

namespace Tests\Browser\Requests;

use App\Models\TestRequest;
use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CompleteRequestLifecycleTest extends DuskTestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemSettingSeeder::class);
    }

    public function test_request_create_form_loads_with_stepper(): void
    {
        $user = User::factory()->create(['role' => 'analyst']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/requests/create')
                ->waitForText('Formulir Permintaan Pengujian Sampel')
                ->assertSee('Formulir Permintaan Pengujian Sampel')
                ->assertSee('Data Penyidik')
                ->assertSee('Info Surat')
                ->assertSee('Tersangka')
                ->assertSee('Dokumen')
                ->assertSee('Sampel');
        });
    }

    public function test_request_create_has_investigator_type_radio(): void
    {
        $user = User::factory()->create(['role' => 'analyst']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/requests/create')
                ->waitForText('Apakah Anda penyidik')
                ->assertSee('Ya, saya penyidik')
                ->assertSee('Bukan anggota Polri')
                ->assertPresent('input[name="is_investigator"]');
        });
    }

    public function test_user_can_view_request_show_page(): void
    {
        $user = User::factory()->create(['role' => 'analyst']);
        $request = TestRequest::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}")
                ->waitForText('Permintaan Pengujian')
                ->assertSee($request->receipt_number ?? $request->request_number)
                ->assertSee($request->investigator->name);
        });
    }

    public function test_user_can_view_and_access_requests_list(): void
    {
        $user = User::factory()->create(['role' => 'analyst']);
        TestRequest::factory()->count(3)->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/requests')
                ->waitForText('Permintaan')
                ->assertPresent('table');
        });
    }

    public function test_request_edit_form_loads_with_existing_data(): void
    {
        $user = User::factory()->create(['role' => 'analyst']);
        $request = TestRequest::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $request) {
            $browser->loginAs($user)
                ->visit("/requests/{$request->id}/edit")
                ->waitForText('Edit Permintaan Pengujian')
                ->assertSee('Edit Permintaan Pengujian')
                ->assertSee('Simpan Perubahan');
        });
    }
}
