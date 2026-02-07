<?php

use App\Models\Reminder;
use App\Models\ReminderRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    Gate::define('manage-settings', fn () => true);
});

it('can create countdown reminder with custom milestones', function () {
    $user = User::factory()->create();

    $payload = [
        'type' => 'countdown',
        'name' => 'Countdown Surveillance Umum',
        'description' => 'Pengingat menuju event surveillance',
        'schedule_time' => '07:00',
        'schedule_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
        'message_template' => "{event_emoji} *{event_name}*\nSisa {days_remaining} hari\n{milestone_message}",
        'target_date' => '2026-08-15',
        'event_name' => 'Surveillance Laboratorium',
        'event_emoji' => '📋',
        'milestones' => [
            ['days' => 60, 'message' => 'Dua bulan lagi, rapatkan persiapan.'],
            ['days' => 30, 'message' => 'Satu bulan lagi, finalisasi dokumen.'],
            ['days' => 0, 'message' => 'Hari ini assessment berlangsung.'],
        ],
        'recipients' => [
            ['type' => 'group', 'value' => '120363000000000000@g.us'],
        ],
        'mention_all' => true,
    ];

    $response = $this->actingAs($user)->postJson(route('whatsapp.reminders.store'), $payload);

    $response->assertCreated()
        ->assertJsonPath('message', 'Reminder created')
        ->assertJsonPath('reminder.type', 'countdown')
        ->assertJsonPath('reminder.name', 'Countdown Surveillance Umum')
        ->assertJsonPath('reminder.mention_all', true);

    $this->assertDatabaseHas('reminders', [
        'name' => 'Countdown Surveillance Umum',
        'type' => 'countdown',
        'mention_all' => true,
    ]);

    $reminder = Reminder::where('name', 'Countdown Surveillance Umum')->firstOrFail();

    expect($reminder->schedule_days)->toBe(['Mon', 'Tue', 'Wed', 'Thu', 'Fri']);
    expect($reminder->metadata['target_date'])->toBe('2026-08-15');
    expect($reminder->metadata['event_name'])->toBe('Surveillance Laboratorium');
    expect($reminder->metadata['event_emoji'])->toBe('📋');
    expect($reminder->metadata['milestones']['60'])->toBe('Dua bulan lagi, rapatkan persiapan.');

    $this->assertDatabaseHas('reminder_recipients', [
        'reminder_id' => $reminder->id,
        'recipient_type' => 'group',
        'recipient_value' => '120363000000000000@g.us',
    ]);
});

it('requires target date and event name for countdown reminder', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('whatsapp.reminders.store'), [
        'type' => 'countdown',
        'name' => 'Reminder Tidak Valid',
        'schedule_time' => '08:00',
        'schedule_days' => ['Mon'],
        'message_template' => 'Template',
        'milestones' => [
            ['days' => 14, 'message' => 'Dua minggu lagi'],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['target_date', 'event_name']);
});

it('can delete reminder and cascade recipients', function () {
    $user = User::factory()->create();

    $reminder = Reminder::create([
        'type' => 'custom',
        'name' => 'Akan Dihapus',
        'is_enabled' => true,
        'schedule_time' => '09:00:00',
        'schedule_days' => ['Mon', 'Tue'],
        'message_template' => 'Testing delete',
    ]);

    $recipient = ReminderRecipient::create([
        'reminder_id' => $reminder->id,
        'recipient_type' => 'group',
        'recipient_value' => '120363111111111111@g.us',
    ]);

    $response = $this->actingAs($user)->deleteJson(route('whatsapp.reminders.destroy', $reminder));

    $response->assertOk()->assertJsonPath('message', 'Reminder deleted');

    $this->assertDatabaseMissing('reminders', ['id' => $reminder->id]);
    $this->assertDatabaseMissing('reminder_recipients', ['id' => $recipient->id]);
});

it('shows professional emoji picker in message template section', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('whatsapp.index', ['tab' => 'reminders']));

    $response->assertOk();
    $response->assertSeeInOrder([
        'Message Template',
        'Pilih Emoji (Profesional)',
        'Emoji terpilih:',
    ], false);
});
