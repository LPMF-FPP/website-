<?php

use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('scopeDue respects schedule_days filter', function () {
    // ... (no changes here)
    // Mock time to Monday 10:00
    $now = Carbon::parse('2024-01-22 10:00:00'); // This is a Monday
    Carbon::setTestNow($now);

    // Create 3 Reminders

    // 1. Due (Enabled, Time OK, Monday included)
    $due = Reminder::create([
        'name' => 'Monday Reminder',
        'type' => 'test',
        'is_enabled' => true,
        'schedule_time' => '09:00:00', // Past
        'schedule_days' => ['Mon', 'Tue'],
        'message_template' => 'Hello',
    ]);

    // 2. Not Due (Wrong Day - Tuesday only)
    $notDueDay = Reminder::create([
        'name' => 'Tuesday Reminder',
        'type' => 'test',
        'is_enabled' => true,
        'schedule_time' => '09:00:00', // Time is OK
        'schedule_days' => ['Tue'], // But today is Monday
        'message_template' => 'Hello',
    ]);

    // 3. Not Due (Time not yet)
    $notDueTime = Reminder::create([
        'name' => 'Later Reminder',
        'type' => 'test',
        'is_enabled' => true,
        'schedule_time' => '11:00:00', // Future
        'schedule_days' => ['Mon'], // Day is OK
        'message_template' => 'Hello',
    ]);

    // Act
    $results = Reminder::due()->get();

    // Assert
    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($due->id);
});

test('update reminder endpoint validates schedule_days', function () {
    $user = User::factory()->create();
    // Allow user to manage settings
    Gate::define('manage-settings', fn () => true);

    $reminder = Reminder::create([
        'name' => 'Test',
        'type' => 'test',
        'schedule_time' => '09:00:00',
        'schedule_days' => ['Mon'],
        'message_template' => 'Hi',
    ]);

    // 1. Success Update
    $response = $this->actingAs($user)->putJson(route('whatsapp.reminders.update', $reminder), [
        'schedule_time' => '10:00',
        'message_template' => 'Updated',
        'schedule_days' => ['Sat', 'Sun'], // Weekend update
    ]);

    $response->assertOk();
    expect($reminder->fresh()->schedule_days)->toBe(['Sat', 'Sun']);

    // 2. Fail Update (Invalid Day)
    $response = $this->actingAs($user)->putJson(route('whatsapp.reminders.update', $reminder), [
        'schedule_time' => '10:00',
        'message_template' => 'Updated',
        'schedule_days' => ['NotADay'],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('schedule_days.0');
});
