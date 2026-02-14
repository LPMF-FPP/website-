<?php

namespace Tests\Browser\WhatsApp;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Throwable;

class TaskFormTest extends DuskTestCase
{
    use DatabaseTruncation;

    /**
     * Test that the WhatsApp tasks page loads correctly.
     *
     *
     * @throws Throwable
     */
    public function test_whatsapp_tasks_page_loads(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/whatsapp?tab=tasks')
                ->waitForText('WhatsApp Hub')
                ->assertSee('Tugas');
        });
    }

    /**
     * Test that the create task button is visible.
     *
     *
     * @throws Throwable
     */
    public function test_create_task_button_visible(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/whatsapp?tab=tasks')
                ->waitForText('WhatsApp Hub')
                ->script('const tabBtn = Array.from(document.querySelectorAll("button")).find((el) => el.textContent.trim() === "Tugas"); if (tabBtn) tabBtn.click();');

            $browser
                ->waitForText('Tugas Saya')
                ->assertSee('Buat Tugas');
        });
    }
}
