<?php

namespace App\Services\Quality;

use App\Models\QmhKum;
use App\Models\StaffTask;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class KumActionItemGenerator
{
    /**
     * @param  array<int, array<string, mixed>>  $decisions
     * @return array<int, StaffTask>
     */
    public function generate(QmhKum $kum, array $decisions, User $actor): array
    {
        return DB::transaction(function () use ($kum, $decisions, $actor): array {
            $created = [];

            foreach ($decisions as $index => $decision) {
                $title = trim((string) ($decision['item'] ?? ''));
                if ($title === '') {
                    throw new InvalidArgumentException("Item keputusan pada index {$index} wajib diisi.");
                }

                $dueDate = (string) ($decision['due_date'] ?? '');
                if ($dueDate === '') {
                    throw new InvalidArgumentException("Due date pada index {$index} wajib diisi.");
                }

                $parsedDueDate = Carbon::parse($dueDate);
                if ($parsedDueDate->isPast()) {
                    throw new InvalidArgumentException("Due date pada index {$index} tidak boleh di masa lalu.");
                }

                $assignee = $this->resolveAssignee($decision['assignee_id'] ?? null, $actor);

                $created[] = StaffTask::query()->create([
                    'title' => $title,
                    'description' => (string) ($decision['description'] ?? 'Generated dari keputusan KUM.'),
                    'assigned_to' => $assignee->id,
                    'assigned_by' => $actor->id,
                    'source_module' => 'qmh',
                    'source_ref_type' => 'qmh_kum',
                    'source_ref_id' => (int) $kum->id,
                    'workflow_stage' => 'governance-follow-up',
                    'context_json' => [
                        'kum_id' => $kum->id,
                        'kum_title' => $kum->title,
                    ],
                    'priority' => StaffTask::PRIORITY_NORMAL,
                    'status' => StaffTask::STATUS_PENDING,
                    'due_at' => $parsedDueDate,
                    'notify_whatsapp' => false,
                ]);
            }

            return $created;
        });
    }

    private function resolveAssignee(mixed $assigneeId, User $fallbackActor): User
    {
        if ($assigneeId === null || (int) $assigneeId <= 0) {
            return $fallbackActor;
        }

        $assignee = User::query()->find((int) $assigneeId);
        if (! $assignee instanceof User) {
            throw new InvalidArgumentException('Assignee tidak valid.');
        }

        if (! $assignee->hasAnyPermission(['qmh.view', 'qmh.rapat.view', 'qmh.audit.view', 'qmh.kum.view'])) {
            throw new InvalidArgumentException('Assignee tidak memiliki permission governance yang sesuai.');
        }

        return $assignee;
    }
}
