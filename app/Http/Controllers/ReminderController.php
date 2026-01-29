<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Services\WhatsApp\GowaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReminderController extends Controller
{
    public function index(): View
    {
        // $this->authorize('reminders.view'); // Temporarily disabled for dev

        $reminders = Reminder::with('recipients')->orderBy('schedule_time')->get();

        return view('reminders.index', compact('reminders'));
    }

    public function edit(Reminder $reminder): View
    {
        // $this->authorize('reminders.edit');

        return view('reminders.edit', compact('reminder'));
    }

    public function update(Request $request, Reminder $reminder)
    {
        // $this->authorize('reminders.edit');

        $validated = $request->validate([
            'schedule_time' => 'required|date_format:H:i:s', // HTML time input sends H:i:s usually or H:i
            'is_enabled' => 'boolean',
            'message_template' => 'required|string',
            'target_date' => 'nullable|date', // for ISO
            'recipients' => 'nullable|array',
            'recipients.*.type' => 'required|in:phone,group',
            'recipients.*.value' => 'required|string',
        ]);

        // Handle metadata updates specifically for ISO
        $metadata = $reminder->metadata;
        if ($reminder->type === 'iso_countdown' && isset($validated['target_date'])) {
            $metadata['target_date'] = $validated['target_date'];
        }

        $reminder->update([
            'schedule_time' => $validated['schedule_time'],
            'is_enabled' => $request->has('is_enabled'),
            'message_template' => $validated['message_template'],
            'metadata' => $metadata,
        ]);

        // Sync recipients
        $reminder->recipients()->delete();

        if (! empty($validated['recipients'])) {
            foreach ($validated['recipients'] as $recipientData) {
                if (! empty($recipientData['value'])) {
                    $reminder->recipients()->create([
                        'recipient_type' => $recipientData['type'],
                        'recipient_value' => $recipientData['value'],
                    ]);
                }
            }
        }

        return redirect()->route('reminders.index')
            ->with('success', 'Reminder updated successfully.');
    }

    public function toggle(Reminder $reminder)
    {
        // $this->authorize('reminders.edit');

        $reminder->update(['is_enabled' => ! $reminder->is_enabled]);

        return back()->with('success', 'Reminder status updated.');
    }

    public function trigger(Reminder $reminder)
    {
        // $this->authorize('reminders.edit');

        \App\Jobs\SendReminderJob::dispatch($reminder);

        return back()->with('success', 'Reminder triggered successfully (queued).');
    }

    public function fetchGroups(): JsonResponse
    {
        // $this->authorize('reminders.edit');

        $gowaClient = new GowaClient;
        $result = $gowaClient->listChats();

        if (! $result['success']) {
            return response()->json(['error' => $result['error'] ?? 'Failed to fetch groups'], 500);
        }

        // Filter groups only (JID ends with @g.us)
        $groups = collect($result['chats'] ?? [])
            ->filter(fn ($chat) => str_ends_with($chat['jid'] ?? '', '@g.us'))
            ->map(fn ($chat) => [
                'jid' => $chat['jid'],
                'name' => $chat['name'] ?? 'Unknown Group',
            ])
            ->values();

        return response()->json(['groups' => $groups]);
    }
}
