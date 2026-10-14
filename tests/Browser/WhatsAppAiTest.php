<?php

namespace Tests\Browser;

use App\Models\SystemSetting;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class WhatsAppAiTest extends DuskTestCase
{
    private array $originalSettings = [];

    private ?User $actor = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('lpmf_release_20261013_verify', config('database.connections.pgsql.database'));
        $this->assertSame('testing', app()->environment());
        $pairs = [
            'notifications.whatsapp.base_url' => 'http://127.0.0.1:9',
            'notifications.whatsapp.basic_user' => 'neutral-browser',
            'notifications.whatsapp.basic_pass' => encrypt('neutral-browser'),
            'notifications.whatsapp.ai.provider' => 'openai',
            'notifications.whatsapp.ai.base_url' => 'https://api.openai.com/v1',
            'notifications.whatsapp.ai.model' => 'gpt-6-luna',
            'notifications.whatsapp.ai.api_key' => encrypt('neutral-browser-key'),
        ];
        foreach ($pairs as $key => $value) {
            $existing = SystemSetting::where('key', $key)->first();
            $this->originalSettings[$key] = $existing ? ['value' => $existing->value] : null;
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        $this->actor = User::factory()->create(['role' => 'admin', 'name' => 'Uji AI Netral']);
    }

    protected function tearDown(): void
    {
        foreach ($this->originalSettings as $key => $original) {
            if ($original === null) {
                SystemSetting::where('key', $key)->first()?->delete();
            } else {
                SystemSetting::updateOrCreate(['key' => $key], $original);
            }
        }
        $this->actor?->delete();
        parent::tearDown();
    }

    public function test_compose_dialog_consent_preview_application_and_stale_lifecycle_in_both_themes(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->actor)->visit('/whatsapp?tab=settings')->waitFor('div[x-data="whatsappHub()"]')->waitUntil('!!window.Alpine');
            $browser->script('window.__aiCalls = []; window.__aiPending = []; window.axios.post = (url, payload, options = {}) => { window.__aiCalls.push({url, payload}); return new Promise(resolve => window.__aiPending.push({resolve, signal: options.signal})); };');
            foreach ([['light', 1280, 900], ['dark', 390, 844]] as [$theme, $width, $height]) {
                $browser->resize($width, $height);
                $browser->script("window.__setTheme('$theme'); window.dispatchEvent(new CustomEvent('open-task-modal', {detail: {users: []}}));");
                $browser->waitFor('div[x-data="taskForm()"] textarea')->type('#task-description-field', 'Draf netral');
                foreach (['Tebal' => '*', 'Miring' => '_', 'Coret' => '~', 'Kode' => '```'] as $label => $wrapper) {
                    $browser->script("document.querySelector('#task-description-field').setSelectionRange(0, 4);");
                    $browser->press($label)->assertInputValue('#task-description-field', $wrapper.'Draf'.$wrapper.' netral')->type('#task-description-field', 'Draf netral');
                }
                $browser->script("document.querySelector('#task-description-field').setSelectionRange(0, 0);");
                $browser->click('div[x-data="taskForm()"] [data-magic-toolbar] > div > button')
                    ->click('div[x-data="taskForm()"] [data-magic-toolbar] > div > div > button:first-child')
                    ->assertInputValue('#task-description-field', '{greetings}Draf netral')->type('#task-description-field', 'Draf netral');
                $browser->click('div[x-data="taskForm()"] [data-magic-toolbar] > button:last-of-type')
                    ->waitFor('#task-description-field-ai-dialog textarea')
                    ->assertSee('Jangan masukkan data perkara')
                    ->assertSee('Bahasa formal')->assertSee('Perbaiki ejaan')->assertSee('Terjemahkan')
                    ->assertNotChecked('#task-description-field-ai-dialog input[type="checkbox"]')
                    ->click('#task-description-field-ai-dialog div[aria-label="Contoh instruksi"] button:first-child')
                    ->assertInputValue('#task-description-field-ai-dialog textarea', 'Tulis pesan formal mengenai: ')
                    ->type('#task-description-field-ai-dialog textarea', 'Tulis salam netral')
                    ->press('Buat draf')->waitForText('Menyusun...');
                $this->assertTrue($browser->script("return document.activeElement.closest('#task-description-field-ai-dialog') !== null;")[0]);
                $this->assertNull($browser->script('return window.__aiCalls.at(-1).payload.current_text')[0]);
                $browser->script("window.__aiPending.at(-1).resolve({data: {result: 'Salam hasil netral'}});");
                $browser->waitForText('Terapkan ke draf')->assertInputValue('#task-description-field', 'Draf netral')
                    ->press('Terapkan ke draf')->waitUntilMissing('#task-description-field-ai-dialog')
                    ->assertInputValue('#task-description-field', 'Salam hasil netral');
                $browser->click('div[x-data="taskForm()"] [data-magic-toolbar] > button:last-of-type')
                    ->waitFor('#task-description-field-ai-dialog textarea')
                    ->type('#task-description-field-ai-dialog textarea', 'Periksa salam netral')
                    ->check('#task-description-field-ai-dialog input[type="checkbox"]')
                    ->press('Buat draf')->waitForText('Menyusun...');
                $this->assertSame('Salam hasil netral', $browser->script('return window.__aiCalls.at(-1).payload.current_text')[0]);
                $browser->keys('#task-description-field-ai-dialog textarea', '{escape}');
                $browser->waitUntilMissing('#task-description-field-ai-dialog');
                $this->assertTrue($browser->script('return window.__aiPending.at(-1).signal.aborted')[0]);
                $browser->script("window.__aiPending.at(-1).resolve({data: {result: 'Hasil usang'}});");
                $browser->assertInputValue('#task-description-field', 'Salam hasil netral');
                $browser->click('div[x-data="taskForm()"] [data-magic-toolbar] > button:last-of-type')->waitFor('#task-description-field-ai-dialog');
                $this->assertTrue($browser->script("const dialog = document.querySelector('#task-description-field-ai-dialog > div'); return dialog.scrollWidth <= dialog.clientWidth;")[0]);
                $browser->press('Buat draf')->waitForText('Menyusun...');
                $browser->script("window.__aiPending.at(-1).resolve({data: {result: ''}});");
                $browser->waitForText('Respons AI gagal diterima');
                $browser->click('#task-description-field-ai-dialog button:last-child');
                $browser->script("Alpine.\$data(document.querySelector('div[x-data=\"taskForm()\"]')).close();");
            }
        });
    }

    public function test_ai_settings_save_reset_remove_and_obsolete_test_feedback(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(1280, 900)->loginAs($this->actor)->visit('/whatsapp?tab=settings')->waitFor('div[x-data="whatsappHub()"]')->waitUntil('!!window.Alpine');
            $browser->script("const hub = Alpine.\$data(document.querySelector('[x-data=\"whatsappHub()\"]')); hub.activeSettingsTab = 'ai'; window.__aiCalls = []; window.__aiPending = []; window.axios.post = (url, payload, options = {}) => { window.__aiCalls.push({url, payload}); return new Promise(resolve => window.__aiPending.push({resolve, signal: options.signal})); };");
            $browser->waitUntil("Alpine.\$data(document.querySelector('#ai-provider').closest('[x-data]')).aiLoadingSettings === false");
            $browser->waitFor('#ai-api-key')->type('#ai-api-key', 'neutral-unsaved')->press('Simpan Konfigurasi AI')->waitForText('Menyimpan...');
            $browser->type('#ai-api-key', 'neutral-newer');
            $browser->script("window.__aiPending.at(-1).resolve({data: {ai_api_key_configured: true, ai_configured: true, ai_revision: 'saved'}});");
            $browser->waitForText('Konfigurasi AI tersimpan')->assertInputValue('#ai-api-key', 'neutral-newer');
            $browser->press('Simpan Konfigurasi AI');
            $browser->script("window.__aiPending.at(-1).resolve({data: {ai_api_key_configured: true, ai_configured: true, ai_revision: 'saved'}});");
            $browser->waitUntil('document.querySelector("#ai-api-key").value === ""');
            $this->assertSame([false, false, false, true, 'Tulis salam singkat untuk uji koneksi, tanpa data perkara.'], $browser->script("const settings = Alpine.\$data(document.querySelector('#ai-provider').closest('[x-data]')); return [settings.aiSaving, settings.aiLoadingSettings, settings.aiDirty, settings.aiForm.ai_configured, settings.aiTest.prompt];")[0]);
            $browser->press('Jalankan AI Test');
            $this->assertSame([true, '', false, true, 'Tulis salam singkat untuk uji koneksi, tanpa data perkara.'], $browser->script("const settings = Alpine.\$data(document.querySelector('#ai-provider').closest('[x-data]')); return [settings.sendingAiTest, settings.aiTest.error, settings.aiDirty, settings.aiForm.ai_configured, settings.aiTest.prompt];")[0]);
            $browser->waitForText('Menjalankan...');
            $browser->type('#ai-test-prompt', 'Instruksi berubah');
            $browser->script("window.__aiPending.at(-1).resolve({data: {result: 'Respons usang'}});");
            $browser->assertDontSee('Respons usang');
            $browser->press('Reset URL/model')->acceptDialog();
            $browser->waitUntil('window.__aiCalls.at(-1).payload.ai_reset_overrides === true');
            $browser->script("window.__aiPending.at(-1).resolve({data: {ai_base_url: 'https://api.openai.com/v1', ai_model: 'gpt-6-luna', ai_api_key_configured: true, ai_configured: true, ai_revision: 'reset'}});");
            $browser->waitForText('Konfigurasi AI tersimpan')->press('Hapus kunci')->acceptDialog();
            $browser->waitUntil('window.__aiCalls.at(-1).payload.ai_remove_key === true');
            $browser->script('window.__aiPending.at(-1).resolve({data: {ai_api_key_configured: false, ai_configured: false, ai_revision: "removed"}});');
            $browser->waitForText('Kunci belum tersedia')->assertDisabled('button[x-bind\\:disabled="sendingAiTest || aiLoadingSettings || aiSaving || aiDirty || !aiForm.ai_configured"]');
        });
    }

    public function test_broadcast_and_countdown_consumers_invalidate_on_record_replacement(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->actor)->visit('/whatsapp?tab=settings')->waitFor('div[x-data="whatsappHub()"]')->waitUntil('!!window.Alpine');
            $browser->script('window.__aiCalls = []; window.__aiPending = []; window.axios.post = (url, payload, options = {}) => { window.__aiCalls.push({url, payload}); return new Promise(resolve => window.__aiPending.push({resolve, signal: options.signal})); };');
            foreach ([['broadcastForm()', 'open-broadcast-modal', 'broadcast-message-field', 'broadcast'], ['reminderEditor()', 'open-reminder-modal', 'reminder-message-field', 'countdown']] as [$component, $event, $field, $context]) {
                $browser->script("window.dispatchEvent(new CustomEvent('$event', {detail: {broadcast: null, reminder: null}}));");
                $browser->waitFor('#'.$field)->click('div[x-data="'.$component.'"] [data-magic-toolbar] > button:last-of-type')
                    ->waitFor('#'.$field.'-ai-dialog textarea')->type('#'.$field.'-ai-dialog textarea', 'Salam uji netral')->press('Buat draf')->waitForText('Menyusun...');
                $this->assertSame($context, $browser->script('return window.__aiCalls.at(-1).payload.context_type')[0]);
                $browser->script("window.dispatchEvent(new CustomEvent('$event', {detail: {broadcast: null, reminder: null}}));");
                $browser->waitUntilMissing('#'.$field.'-ai-dialog');
                $this->assertTrue($browser->script('return window.__aiPending.at(-1).signal.aborted')[0]);
                $browser->script("window.__aiPending.at(-1).resolve({data: {result: 'Usang'}});");
                $this->assertNotSame('Usang', $browser->value('#'.$field));
                $browser->script("const editor = Alpine.\$data(document.querySelector('div[x-data=\"$component\"]')); editor.isOpen = false; editor.aiEditorSession++;");
            }
        });
    }
}
