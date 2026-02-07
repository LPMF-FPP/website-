<?php

use App\Models\Reminder;
use App\Services\Reminders\Handlers\CountdownHandler;
use Carbon\Carbon;

it('builds message with countdown placeholders and custom milestone message', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 00:00:00'));

    $reminder = new Reminder([
        'type' => 'countdown',
        'message_template' => '{event_emoji} {event_name} {days_remaining} {milestone_message}',
        'metadata' => [
            'target_date' => '2026-08-15',
            'event_name' => 'Surveillance Umum',
            'event_emoji' => '📋',
            'milestones' => [
                '60' => 'Dua bulan lagi.',
                '30' => 'Satu bulan lagi.',
                '0' => 'Hari ini.',
            ],
        ],
    ]);

    $message = app(CountdownHandler::class)->handle($reminder);

    expect($message)->toContain('📋');
    expect($message)->toContain('Surveillance Umum');
    expect($message)->toContain('30');
    expect($message)->toContain('Satu bulan lagi.');

    Carbon::setTestNow();
});

it('supports legacy motivation placeholder for backward compatibility', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-15 08:00:00'));

    $reminder = new Reminder([
        'type' => 'iso_countdown',
        'message_template' => 'Sisa {days_remaining} hari. {motivation_message}',
        'metadata' => [
            'target_date' => '2026-08-15',
        ],
    ]);

    $message = app(CountdownHandler::class)->handle($reminder);

    expect($message)->toContain('0');
    expect($message)->not->toContain('{motivation_message}');

    Carbon::setTestNow();
});
