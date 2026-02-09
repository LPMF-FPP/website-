<?php

namespace Tests\Browser\WhatsApp;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Throwable;

class TaskFormTest extends DuskTestCase
{
    /**
     * Test that the AI Magic button in Task Form works.
     *
     * @return void
     *
     * @throws Throwable
     */
    public function test_ai_magic_button_in_task_form()
    {
        $this->browse(function (Browser $browser) {
            $user = User::first();
            if (! $user) {
                $user = User::factory()->create([
                    'email' => 'tasktest@example.com',
                    'name' => 'Task Tester',
                    'password' => bcrypt('password'),
                ]);
            }

            $browser->loginAs($user)
                ->visit('/whatsapp?tab=tasks')
                ->waitForText('Tugas')
                ->pause(2000)
                ->click('button:contains("Buat Tugas")') // Use simple selector
                ->waitForText('Buat Tugas Baru')
                ->pause(1000)
                // Use JS click for reliability on icons
                ->script("document.querySelector('[data-magic-toolbar] button:nth-child(2)').click();");

            $browser->pause(1000)
                ->assertSee('AI Magic Compose');

            // Check visibility using script
            $isVisible = $browser->driver->executeScript("
                const modal = document.querySelector('.fixed.inset-0.z-\\[100\\]');
                if (!modal) return false;
                const style = window.getComputedStyle(modal);
                return style.display !== 'none' && style.visibility !== 'hidden' && modal.offsetParent !== null;
            ");

            $this->assertTrue($isVisible, 'AI Modal should be visible but is hidden');
        });
    }
}
