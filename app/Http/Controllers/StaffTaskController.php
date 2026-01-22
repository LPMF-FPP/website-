<?php

namespace App\Http\Controllers;

use App\Models\StaffTask;
use App\Models\TestRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffTaskController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $filter = $request->input('filter', 'my_tasks');
        $status = $request->input('status', 'active');

        $query = StaffTask::with(['assignee', 'assigner', 'testRequest']);

        // Filter by ownership
        if ($filter === 'my_tasks') {
            $query->where('assigned_to', $user->id);
        } elseif ($filter === 'assigned_by_me') {
            $query->where('assigned_by', $user->id);
        }
        // 'all' shows all tasks (admin only)

        // Filter by status
        if ($status === 'active') {
            $query->active();
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $tasks = $query->orderByRaw("CASE priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'normal' THEN 3 
            WHEN 'low' THEN 4 
            ELSE 5 END")
            ->orderBy('due_at')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Get stats
        $stats = [
            'pending' => StaffTask::where('assigned_to', $user->id)->pending()->count(),
            'in_progress' => StaffTask::where('assigned_to', $user->id)->inProgress()->count(),
            'overdue' => StaffTask::where('assigned_to', $user->id)->overdue()->count(),
            'completed_today' => StaffTask::where('assigned_to', $user->id)
                ->where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'tasks' => $tasks,
                'stats' => $stats,
            ]);
        }

        $users = User::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return view('tasks.index', [
            'tasks' => $tasks,
            'stats' => $stats,
            'users' => $users,
            'priorities' => StaffTask::priorities(),
            'statuses' => StaffTask::statuses(),
            'filter' => $filter,
            'currentStatus' => $status,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'assigned_to' => 'required|exists:users,id',
            'test_request_id' => 'nullable|exists:test_requests,id',
            'priority' => 'required|in:low,normal,high,urgent',
            'due_at' => 'nullable|date|after_or_equal:today',
            'notify_whatsapp' => 'boolean',
        ]);

        $validated['assigned_by'] = $request->user()->id;
        $validated['status'] = StaffTask::STATUS_PENDING;
        $validated['notify_whatsapp'] = $validated['notify_whatsapp'] ?? true;

        $task = StaffTask::create($validated);

        // Dispatch WhatsApp notification if enabled
        if ($task->notify_whatsapp) {
            dispatch(new \App\Jobs\SendTaskNotificationJob($task->id, 'assigned'));
        }

        return response()->json([
            'message' => 'Tugas berhasil dibuat',
            'task' => $task->load(['assignee', 'assigner', 'testRequest']),
        ], 201);
    }

    public function show(StaffTask $task): JsonResponse
    {
        return response()->json([
            'task' => $task->load(['assignee', 'assigner', 'testRequest']),
        ]);
    }

    public function update(Request $request, StaffTask $task): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'assigned_to' => 'sometimes|required|exists:users,id',
            'test_request_id' => 'nullable|exists:test_requests,id',
            'priority' => 'sometimes|required|in:low,normal,high,urgent',
            'due_at' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'notify_whatsapp' => 'boolean',
        ]);

        $task->update($validated);

        return response()->json([
            'message' => 'Tugas berhasil diperbarui',
            'task' => $task->fresh(['assignee', 'assigner', 'testRequest']),
        ]);
    }

    public function updateStatus(Request $request, StaffTask $task): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,completed,cancelled',
            'notes' => 'nullable|string|max:2000',
        ]);

        $oldStatus = $task->status;
        $newStatus = $validated['status'];

        $updates = ['status' => $newStatus];

        if (isset($validated['notes'])) {
            $updates['notes'] = $validated['notes'];
        }

        // Set timestamps based on status change
        if ($newStatus === StaffTask::STATUS_IN_PROGRESS && $oldStatus === StaffTask::STATUS_PENDING) {
            $updates['started_at'] = now();
        } elseif ($newStatus === StaffTask::STATUS_COMPLETED) {
            $updates['completed_at'] = now();
        }

        $task->update($updates);

        // Send notification if status changed significantly
        if ($task->notify_whatsapp && in_array($newStatus, ['completed', 'in_progress'])) {
            dispatch(new \App\Jobs\SendTaskNotificationJob($task->id, 'status_changed'));
        }

        return response()->json([
            'message' => 'Status tugas diperbarui',
            'task' => $task->fresh(['assignee', 'assigner', 'testRequest']),
        ]);
    }

    public function destroy(StaffTask $task): JsonResponse
    {
        $task->delete();

        return response()->json([
            'message' => 'Tugas berhasil dihapus',
        ]);
    }

    public function getForRequest(TestRequest $testRequest): JsonResponse
    {
        $tasks = StaffTask::with(['assignee', 'assigner'])
            ->where('test_request_id', $testRequest->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'tasks' => $tasks,
        ]);
    }

    public function quickCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'assigned_to' => 'required|exists:users,id',
            'test_request_id' => 'nullable|exists:test_requests,id',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ]);

        $task = StaffTask::create([
            'title' => $validated['title'],
            'assigned_to' => $validated['assigned_to'],
            'assigned_by' => $request->user()->id,
            'test_request_id' => $validated['test_request_id'] ?? null,
            'priority' => $validated['priority'] ?? 'normal',
            'status' => StaffTask::STATUS_PENDING,
            'notify_whatsapp' => true,
        ]);

        if ($task->notify_whatsapp) {
            dispatch(new \App\Jobs\SendTaskNotificationJob($task->id, 'assigned'));
        }

        return response()->json([
            'message' => 'Tugas cepat berhasil dibuat',
            'task' => $task->load(['assignee']),
        ], 201);
    }
}
