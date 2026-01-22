<?php

namespace App\Http\Controllers;

use App\Models\Investigator;
use App\Models\User;
use App\Models\WhatsappBroadcast;
use App\Models\WhatsappBroadcastRecipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsappBroadcastController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $query = WhatsappBroadcast::with('creator')
            ->orderBy('created_at', 'desc');

        $status = $request->input('status');
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $broadcasts = $query->paginate(20);

        if ($request->wantsJson()) {
            return response()->json([
                'broadcasts' => $broadcasts,
            ]);
        }

        return view('broadcasts.index', [
            'broadcasts' => $broadcasts,
            'statuses' => WhatsappBroadcast::statuses(),
            'targetTypes' => WhatsappBroadcast::targetTypes(),
        ]);
    }

    public function create(): View
    {
        $jurisdictions = Investigator::select('jurisdiction')
            ->distinct()
            ->orderBy('jurisdiction')
            ->pluck('jurisdiction')
            ->filter();

        return view('broadcasts.create', [
            'targetTypes' => WhatsappBroadcast::targetTypes(),
            'jurisdictions' => $jurisdictions,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'target_type' => 'required|in:investigators,users,custom',
            'target_filters' => 'nullable|array',
            'target_filters.jurisdiction' => 'nullable|string',
            'target_filters.role' => 'nullable|string',
            'recipient_ids' => 'nullable|array',
            'recipient_ids.*' => 'integer',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $broadcast = WhatsappBroadcast::create([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'target_type' => $validated['target_type'],
            'target_filters' => $validated['target_filters'] ?? null,
            'recipient_ids' => $validated['recipient_ids'] ?? null,
            'created_by' => $request->user()->id,
            'status' => isset($validated['scheduled_at']) ? WhatsappBroadcast::STATUS_SCHEDULED : WhatsappBroadcast::STATUS_DRAFT,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
        ]);

        // Build recipients
        $this->buildRecipients($broadcast);

        return response()->json([
            'message' => 'Broadcast berhasil dibuat',
            'broadcast' => $broadcast->load('creator'),
        ], 201);
    }

    public function show(WhatsappBroadcast $broadcast): JsonResponse
    {
        $broadcast->load(['creator', 'recipients']);

        return response()->json([
            'broadcast' => $broadcast,
            'recipients' => $broadcast->recipients()->paginate(50),
        ]);
    }

    public function update(Request $request, WhatsappBroadcast $broadcast): JsonResponse
    {
        if (! $broadcast->canEdit()) {
            return response()->json([
                'message' => 'Broadcast tidak dapat diedit',
            ], 422);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'message' => 'sometimes|required|string|max:2000',
            'target_type' => 'sometimes|required|in:investigators,users,custom',
            'target_filters' => 'nullable|array',
            'recipient_ids' => 'nullable|array',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $broadcast->update($validated);

        // Rebuild recipients if target changed
        if (isset($validated['target_type']) || isset($validated['target_filters']) || isset($validated['recipient_ids'])) {
            $broadcast->recipients()->delete();
            $this->buildRecipients($broadcast);
        }

        return response()->json([
            'message' => 'Broadcast berhasil diperbarui',
            'broadcast' => $broadcast->fresh('creator'),
        ]);
    }

    public function send(WhatsappBroadcast $broadcast): JsonResponse
    {
        if (! $broadcast->canSend()) {
            return response()->json([
                'message' => 'Broadcast tidak dapat dikirim',
            ], 422);
        }

        // Dispatch job to send broadcast
        dispatch(new \App\Jobs\SendBroadcastJob($broadcast->id));

        $broadcast->update([
            'status' => WhatsappBroadcast::STATUS_SENDING,
            'started_at' => now(),
        ]);

        return response()->json([
            'message' => 'Broadcast sedang dikirim',
            'broadcast' => $broadcast->fresh(),
        ]);
    }

    public function cancel(WhatsappBroadcast $broadcast): JsonResponse
    {
        if (! $broadcast->canCancel()) {
            return response()->json([
                'message' => 'Broadcast tidak dapat dibatalkan',
            ], 422);
        }

        $broadcast->markAsCancelled();

        return response()->json([
            'message' => 'Broadcast dibatalkan',
            'broadcast' => $broadcast->fresh(),
        ]);
    }

    public function destroy(WhatsappBroadcast $broadcast): JsonResponse
    {
        if (! $broadcast->canEdit()) {
            return response()->json([
                'message' => 'Broadcast tidak dapat dihapus',
            ], 422);
        }

        $broadcast->delete();

        return response()->json([
            'message' => 'Broadcast berhasil dihapus',
        ]);
    }

    public function previewRecipients(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => 'required|in:investigators,users,custom',
            'target_filters' => 'nullable|array',
            'recipient_ids' => 'nullable|array',
        ]);

        $recipients = $this->getRecipientsPreview(
            $validated['target_type'],
            $validated['target_filters'] ?? [],
            $validated['recipient_ids'] ?? []
        );

        return response()->json([
            'count' => $recipients->count(),
            'recipients' => $recipients->take(20),
        ]);
    }

    private function buildRecipients(WhatsappBroadcast $broadcast): void
    {
        $recipients = $this->getRecipientsPreview(
            $broadcast->target_type,
            $broadcast->target_filters ?? [],
            $broadcast->recipient_ids ?? []
        );

        $recipientRecords = [];

        foreach ($recipients as $recipient) {
            $recipientRecords[] = [
                'broadcast_id' => $broadcast->id,
                'recipient_type' => $recipient['type'],
                'recipient_id' => $recipient['id'],
                'phone' => $recipient['phone'],
                'name' => $recipient['name'],
                'status' => WhatsappBroadcastRecipient::STATUS_PENDING,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (count($recipientRecords) > 0) {
            WhatsappBroadcastRecipient::insert($recipientRecords);
        }

        $broadcast->update([
            'total_recipients' => count($recipientRecords),
        ]);
    }

    private function getRecipientsPreview(string $targetType, array $filters, array $customIds): \Illuminate\Support\Collection
    {
        $recipients = collect();

        if ($targetType === WhatsappBroadcast::TARGET_INVESTIGATORS) {
            $query = Investigator::whereNotNull('phone')
                ->where('phone', '!=', '');

            if (! empty($filters['jurisdiction'])) {
                $query->where('jurisdiction', $filters['jurisdiction']);
            }

            $investigators = $query->get(['id', 'name', 'phone', 'rank']);

            foreach ($investigators as $inv) {
                $recipients->push([
                    'type' => 'investigator',
                    'id' => $inv->id,
                    'name' => $inv->rank.' '.$inv->name,
                    'phone' => $inv->phone,
                ]);
            }
        } elseif ($targetType === WhatsappBroadcast::TARGET_USERS) {
            $query = User::where('is_active', true)
                ->whereNotNull('phone')
                ->where('phone', '!=', '');

            if (! empty($filters['role'])) {
                $query->where('role', $filters['role']);
            }

            $users = $query->get(['id', 'name', 'phone', 'role']);

            foreach ($users as $user) {
                $recipients->push([
                    'type' => 'user',
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                ]);
            }
        } elseif ($targetType === WhatsappBroadcast::TARGET_CUSTOM && ! empty($customIds)) {
            // Custom can mix investigators and users
            // Format: ["inv:1", "inv:2", "user:3"]
            foreach ($customIds as $customId) {
                if (is_string($customId) && str_contains($customId, ':')) {
                    [$type, $id] = explode(':', $customId, 2);

                    if ($type === 'inv') {
                        $inv = Investigator::find($id);
                        if ($inv && $inv->phone) {
                            $recipients->push([
                                'type' => 'investigator',
                                'id' => $inv->id,
                                'name' => $inv->rank.' '.$inv->name,
                                'phone' => $inv->phone,
                            ]);
                        }
                    } elseif ($type === 'user') {
                        $user = User::find($id);
                        if ($user && $user->phone) {
                            $recipients->push([
                                'type' => 'user',
                                'id' => $user->id,
                                'name' => $user->name,
                                'phone' => $user->phone,
                            ]);
                        }
                    }
                }
            }
        }

        return $recipients;
    }
}
