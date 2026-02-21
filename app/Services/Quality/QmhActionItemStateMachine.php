<?php

namespace App\Services\Quality;

use App\Models\QmhRapatActionItem;
use InvalidArgumentException;

class QmhActionItemStateMachine
{
    /**
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        QmhRapatActionItem::STATUS_OPEN => [
            QmhRapatActionItem::STATUS_IN_PROGRESS,
            QmhRapatActionItem::STATUS_OVERDUE,
        ],
        QmhRapatActionItem::STATUS_IN_PROGRESS => [
            QmhRapatActionItem::STATUS_RESOLVED,
            QmhRapatActionItem::STATUS_OVERDUE,
        ],
        QmhRapatActionItem::STATUS_RESOLVED => [
            QmhRapatActionItem::STATUS_VERIFIED,
            QmhRapatActionItem::STATUS_OVERDUE,
        ],
        QmhRapatActionItem::STATUS_VERIFIED => [
            QmhRapatActionItem::STATUS_CLOSED,
        ],
        QmhRapatActionItem::STATUS_CLOSED => [],
        QmhRapatActionItem::STATUS_OVERDUE => [
            QmhRapatActionItem::STATUS_IN_PROGRESS,
            QmhRapatActionItem::STATUS_RESOLVED,
        ],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function transition(QmhRapatActionItem $actionItem, string $nextStatus, int $actorId): void
    {
        $currentStatus = (string) $actionItem->status;

        if ($currentStatus === $nextStatus) {
            return;
        }

        if (! $this->canTransition($currentStatus, $nextStatus)) {
            throw new InvalidArgumentException(sprintf(
                'Transisi status tidak valid dari %s ke %s.',
                $currentStatus,
                $nextStatus
            ));
        }

        $actionItem->status = $nextStatus;
        $actionItem->updated_by = $actorId;

        if ($nextStatus === QmhRapatActionItem::STATUS_RESOLVED && $actionItem->resolved_at === null) {
            $actionItem->resolved_at = now();
        }

        if ($nextStatus === QmhRapatActionItem::STATUS_VERIFIED && $actionItem->verified_at === null) {
            $actionItem->verified_at = now();
        }

        if ($nextStatus === QmhRapatActionItem::STATUS_CLOSED && $actionItem->closed_at === null) {
            $actionItem->closed_at = now();
        }

        $actionItem->save();
    }
}
