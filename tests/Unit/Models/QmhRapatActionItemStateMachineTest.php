<?php

namespace Tests\Unit\Models;

use App\Models\QmhRapat;
use App\Models\QmhRapatActionItem;
use App\Models\User;
use App\Services\Quality\QmhActionItemStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class QmhRapatActionItemStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_allows_legal_transition_and_sets_timestamps(): void
    {
        $user = User::factory()->create();
        $rapat = QmhRapat::query()->create([
            'title' => 'Rapat Uji',
            'meeting_type' => 'ad_hoc',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $actionItem = QmhRapatActionItem::query()->create([
            'rapat_id' => $rapat->id,
            'title' => 'Follow up',
            'status' => QmhRapatActionItem::STATUS_IN_PROGRESS,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $service = new QmhActionItemStateMachine;
        $service->transition($actionItem, QmhRapatActionItem::STATUS_RESOLVED, $user->id);

        $actionItem->refresh();

        $this->assertSame(QmhRapatActionItem::STATUS_RESOLVED, $actionItem->status);
        $this->assertNotNull($actionItem->resolved_at);
    }

    public function test_it_rejects_illegal_transition(): void
    {
        $user = User::factory()->create();
        $rapat = QmhRapat::query()->create([
            'title' => 'Rapat Uji',
            'meeting_type' => 'ad_hoc',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $actionItem = QmhRapatActionItem::query()->create([
            'rapat_id' => $rapat->id,
            'title' => 'Follow up',
            'status' => QmhRapatActionItem::STATUS_OPEN,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $service = new QmhActionItemStateMachine;
        $service->transition($actionItem, QmhRapatActionItem::STATUS_CLOSED, $user->id);
    }
}
