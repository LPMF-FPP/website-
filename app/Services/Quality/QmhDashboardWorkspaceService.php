<?php

namespace App\Services\Quality;

use App\Models\AuditTrail;
use App\Models\QmhAuditTemuan;
use App\Models\QmhDocument;
use App\Models\QmhDocumentRevision;
use App\Models\QmhKum;
use App\Models\QmhRapat;
use App\Models\QmhWorkflowEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class QmhDashboardWorkspaceService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(User $user, array $filters): array
    {
        $clause = isset($filters['clause']) ? (int) $filters['clause'] : null;
        $docType = isset($filters['doc_type']) ? (string) $filters['doc_type'] : null;
        $period = max(7, min(90, (int) ($filters['period'] ?? 30)));
        $requestedQueueTab = (string) ($filters['queue_tab'] ?? 'mine');
        $queueTab = in_array($requestedQueueTab, ['mine', 'overdue', 'done'], true)
            ? $requestedQueueTab
            : 'mine';
        $activityPage = max(1, (int) ($filters['activity_page'] ?? 1));
        $activityFeed = $this->buildActivityFeed($activityPage);

        return [
            'filters' => [
                'clause' => $clause,
                'doc_type' => $docType,
                'period' => $period,
                'queue_tab' => $queueTab,
                'activity_page' => $activityFeed['meta']['current_page'],
            ],
            'alerts' => $this->buildAlerts($clause, $docType, $period),
            'queue' => $this->buildQueue($user, $clause, $docType, $period, $queueTab),
            'governance' => $this->buildGovernanceSnapshot(),
            'activities' => $activityFeed['items'],
            'activities_meta' => $activityFeed['meta'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildAlerts(?int $clause, ?string $docType, int $period): array
    {
        $base = $this->baseDocumentQuery($clause, $docType);

        $overdueCount = (clone $base)
            ->whereHas('currentRevision', fn (Builder $query) => $this->applyOverdueRevisionConstraint($query))
            ->count();

        $approvalStalledCount = (clone $base)
            ->whereHas('currentRevision', function (Builder $query): void {
                $query->whereIn('status', ['approved_by_reviewer', 'in_approval'])
                    ->where(function (Builder $stalled): void {
                        $stalled
                            ->where(function (Builder $fromReview): void {
                                $fromReview->whereNotNull('reviewed_at')->where('reviewed_at', '<', now()->subDays(2));
                            })
                            ->orWhere(function (Builder $noReview): void {
                                $noReview->whereNull('reviewed_at')->where('updated_at', '<', now()->subDays(2));
                            });
                    });
            })
            ->count();

        $openFindings = QmhAuditTemuan::query()->open()->count();

        $baseQuery = $this->dashboardFilterQuery($clause, $docType, $period);

        return [
            [
                'icon' => 'alert',
                'label' => 'Overdue',
                'count' => $overdueCount,
                'href' => route('quality.index', array_merge($baseQuery, ['queue_tab' => 'overdue'])),
            ],
            [
                'icon' => 'clock',
                'label' => 'Approval >48 jam',
                'count' => $approvalStalledCount,
                'href' => route('quality.documents.index', array_merge($this->documentFilterQuery($clause, $docType), ['status' => 'in_approval'])),
            ],
            [
                'icon' => 'audit',
                'label' => 'Temuan Audit Open',
                'count' => $openFindings,
                'href' => route('quality.audit.index'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQueue(User $user, ?int $clause, ?string $docType, int $period, string $activeTab): array
    {
        $base = $this->baseDocumentQuery($clause, $docType);

        $mineCount = (clone $base)
            ->whereHas('currentRevision', fn (Builder $query) => $this->applyMineQueueConstraint($query, $user->id))
            ->count();

        $overdueCount = (clone $base)
            ->whereHas('currentRevision', fn (Builder $query) => $this->applyOverdueRevisionConstraint($query))
            ->count();

        $doneCount = (clone $base)
            ->whereHas('currentRevision', fn (Builder $query) => $this->applyDoneQueueConstraint($query, $period))
            ->count();

        $rowsQuery = (clone $base)->with('currentRevision');

        if ($activeTab === 'mine') {
            $rowsQuery->whereHas('currentRevision', fn (Builder $query) => $this->applyMineQueueConstraint($query, $user->id));
        }

        if ($activeTab === 'overdue') {
            $rowsQuery->whereHas('currentRevision', fn (Builder $query) => $this->applyOverdueRevisionConstraint($query));
        }

        if ($activeTab === 'done') {
            $rowsQuery->whereHas('currentRevision', fn (Builder $query) => $this->applyDoneQueueConstraint($query, $period));
        }

        $canManage = $user->hasPermission('qmh.create');

        $rows = $rowsQuery
            ->orderBy('doc_code')
            ->limit(50)
            ->get()
            ->map(fn (QmhDocument $document) => $this->mapQueueRow($document, $activeTab, $canManage))
            ->filter()
            ->values();

        $sortedRows = match ($activeTab) {
            'done' => $rows->sortByDesc('sort_timestamp')->values(),
            default => $rows->sortByDesc('age_days')->values(),
        };

        $baseQuery = $this->dashboardFilterQuery($clause, $docType, $period);

        return [
            'active_tab' => $activeTab,
            'tabs' => [
                [
                    'key' => 'mine',
                    'label' => 'Menunggu Saya',
                    'count' => $mineCount,
                    'href' => route('quality.index', array_merge($baseQuery, ['queue_tab' => 'mine'])),
                ],
                [
                    'key' => 'overdue',
                    'label' => 'Overdue',
                    'count' => $overdueCount,
                    'href' => route('quality.index', array_merge($baseQuery, ['queue_tab' => 'overdue'])),
                ],
                [
                    'key' => 'done',
                    'label' => 'Selesai 7 Hari',
                    'count' => $doneCount,
                    'href' => route('quality.index', array_merge($baseQuery, ['queue_tab' => 'done'])),
                ],
            ],
            'rows' => $sortedRows->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildGovernanceSnapshot(): array
    {
        $rapatPending = QmhRapat::query()
            ->whereIn('status', ['draft', 'scheduled', 'in_progress'])
            ->count();

        $auditOpen = QmhAuditTemuan::query()->open()->count();

        $kumDueSoon = QmhKum::query()
            ->whereIn('status', ['draft', 'scheduled', 'in_progress'])
            ->whereNotNull('scheduled_at')
            ->whereDate('scheduled_at', '<=', now()->addDays(7)->toDateString())
            ->count();

        return [
            [
                'icon' => 'rapat',
                'label' => 'Rapat Pending',
                'count' => $rapatPending,
                'href' => route('quality.rapat.index', ['status' => 'scheduled']),
            ],
            [
                'icon' => 'audit',
                'label' => 'Temuan Audit Open',
                'count' => $auditOpen,
                'href' => route('quality.audit.index', ['status' => 'in_progress']),
            ],
            [
                'icon' => 'kum',
                'label' => 'KUM Jatuh Tempo',
                'count' => $kumDueSoon,
                'href' => route('quality.kum.index', ['status' => 'scheduled']),
            ],
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, meta: array<string, int|bool>}
     */
    private function buildActivityFeed(int $page): array
    {
        $perPage = 20;
        $auditTables = ['qmh_rapats', 'qmh_audits', 'qmh_kums', 'qmh_audit_temuans', 'qmh_rapat_action_items'];
        $workflowTotal = QmhWorkflowEvent::query()->count();
        $auditTotal = AuditTrail::query()->whereIn('table_name', $auditTables)->count();
        $total = $workflowTotal + $auditTotal;

        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, $page), $lastPage);
        $fetchSize = max($perPage, $currentPage * $perPage);

        $workflowEvents = QmhWorkflowEvent::query()
            ->with(['revision.document'])
            ->orderByDesc('created_at')
            ->limit($fetchSize)
            ->get();

        $auditTrails = AuditTrail::query()
            ->whereIn('table_name', $auditTables)
            ->orderByDesc('changed_at')
            ->limit($fetchSize)
            ->get();

        $actorIds = collect()
            ->merge($workflowEvents->pluck('actor_id')->filter(fn ($id) => is_int($id) && $id > 0))
            ->merge($auditTrails->pluck('changed_by')->filter(fn ($id) => ctype_digit((string) $id))->map(fn ($id) => (int) $id))
            ->unique()
            ->values();

        $actorNames = User::query()
            ->whereIn('id', $actorIds->all())
            ->pluck('name', 'id');

        $workflowItems = $workflowEvents->map(function (QmhWorkflowEvent $event) use ($actorNames): array {
            $document = $event->revision?->document;
            $docCode = $document?->doc_code ?? 'Dokumen QMH';
            $actor = $actorNames->get((int) $event->actor_id, 'Sistem');
            $title = $this->workflowEventLabel((string) $event->event_type);

            return [
                'icon' => 'document',
                'title' => $title,
                'meta' => sprintf('%s - %s', $docCode, $actor),
                'time' => $event->created_at,
                'href' => $document
                    ? route('quality.documents.show', $document)
                    : route('quality.documents.index'),
            ];
        });

        $trailItems = $auditTrails->map(function (AuditTrail $trail) use ($actorNames): array {
            $entity = $this->trailEntityLabel((string) $trail->table_name);
            $action = $this->trailActionLabel((string) $trail->action);
            $actorId = ctype_digit((string) $trail->changed_by) ? (int) $trail->changed_by : null;
            $actor = $actorId !== null ? $actorNames->get($actorId, 'Sistem') : 'Sistem';

            return [
                'icon' => 'governance',
                'title' => sprintf('%s %s', $entity, $action),
                'meta' => $actor,
                'time' => $trail->changed_at,
                'href' => $this->trailDestination((string) $trail->table_name),
            ];
        });

        $offset = ($currentPage - 1) * $perPage;

        $items = $workflowItems
            ->concat($trailItems)
            ->sortByDesc(fn (array $item) => $item['time'] instanceof Carbon ? $item['time']->getTimestamp() : 0)
            ->slice($offset, $perPage)
            ->map(function (array $item): array {
                /** @var Carbon|null $timestamp */
                $timestamp = $item['time'] instanceof Carbon ? $item['time'] : null;

                return [
                    'icon' => $item['icon'],
                    'title' => $item['title'],
                    'meta' => $item['meta'],
                    'href' => $item['href'],
                    'time_label' => $timestamp ? $timestamp->diffForHumans() : '-',
                ];
            })
            ->values()
            ->all();

        return [
            'items' => $items,
            'meta' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total === 0 ? 0 : $offset + 1,
                'to' => $total === 0 ? 0 : min($offset + $perPage, $total),
                'has_prev' => $currentPage > 1,
                'has_next' => $currentPage < $lastPage,
            ],
        ];
    }

    private function baseDocumentQuery(?int $clause, ?string $docType): Builder
    {
        return QmhDocument::query()
            ->whereNotNull('current_revision_id')
            ->when($clause !== null, fn (Builder $query) => $query->where('clause', $clause))
            ->when($docType !== null && $docType !== '', fn (Builder $query) => $query->where('doc_type', $docType));
    }

    private function applyMineQueueConstraint(Builder $query, int $userId): void
    {
        $query->where(function (Builder $mine) use ($userId): void {
            $mine
                ->where(function (Builder $review) use ($userId): void {
                    $review->where('status', 'in_review')->where('diperiksa_oleh', $userId);
                })
                ->orWhere(function (Builder $approval) use ($userId): void {
                    $approval->whereIn('status', ['approved_by_reviewer', 'in_approval'])
                        ->where('disahkan_oleh', $userId);
                });
        });
    }

    private function applyOverdueRevisionConstraint(Builder $query): void
    {
        $query->where(function (Builder $overdue): void {
            $overdue
                ->where(function (Builder $draft): void {
                    $draft->where('status', 'draft')->where('created_at', '<', now()->subDays(30));
                })
                ->orWhere(function (Builder $review): void {
                    $review->where('status', 'in_review')
                        ->whereNotNull('submitted_at')
                        ->where('submitted_at', '<', now()->subDays(7));
                })
                ->orWhere(function (Builder $approval): void {
                    $approval->whereIn('status', ['approved_by_reviewer', 'in_approval'])
                        ->where(function (Builder $stalled): void {
                            $stalled
                                ->where(function (Builder $fromReview): void {
                                    $fromReview->whereNotNull('reviewed_at')->where('reviewed_at', '<', now()->subDays(2));
                                })
                                ->orWhere(function (Builder $noReview): void {
                                    $noReview->whereNull('reviewed_at')->where('updated_at', '<', now()->subDays(2));
                                });
                        });
                });
        });
    }

    private function applyDoneQueueConstraint(Builder $query, int $period): void
    {
        $since = now()->subDays($period);

        $query->where('status', 'published')
            ->where(function (Builder $done) use ($since): void {
                $done
                    ->where(function (Builder $approved): void {
                        $approved->whereNotNull('approved_at')->where('approved_at', '>=', now()->subDays(7));
                    })
                    ->orWhere(function (Builder $fallback) use ($since): void {
                        $fallback->whereNull('approved_at')->where('updated_at', '>=', $since);
                    });
            });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapQueueRow(QmhDocument $document, string $activeTab, bool $canManage): ?array
    {
        $revision = $document->currentRevision;
        if (! $revision instanceof QmhDocumentRevision) {
            return null;
        }

        $anchor = $this->ageAnchor($revision);
        $ageDays = $anchor?->diffInDays(now()) ?? 0;

        return [
            'id' => $document->id,
            'doc_code' => $document->doc_code,
            'title' => $document->title,
            'clause' => $document->clause,
            'status_label' => $this->statusLabel((string) $revision->status),
            'status_variant' => $this->statusVariant((string) $revision->status),
            'age_days' => $ageDays,
            'age_label' => $ageDays > 0 ? $ageDays.' hari' : 'Hari ini',
            'sort_timestamp' => $anchor?->getTimestamp() ?? 0,
            'show_url' => route('quality.documents.show', $document),
            'assign_url' => $canManage ? route('quality.documents.edit', $document) : null,
            'defer_url' => $canManage ? route('quality.documents.edit', $document).'?intent=defer' : null,
            'can_manage' => $canManage,
            'row_mode' => $activeTab,
        ];
    }

    private function ageAnchor(QmhDocumentRevision $revision): ?Carbon
    {
        if ($revision->status === 'in_review' && $revision->submitted_at instanceof Carbon) {
            return $revision->submitted_at;
        }

        if (in_array($revision->status, ['approved_by_reviewer', 'in_approval'], true) && $revision->reviewed_at instanceof Carbon) {
            return $revision->reviewed_at;
        }

        if ($revision->status === 'published' && $revision->approved_at instanceof Carbon) {
            return $revision->approved_at;
        }

        return $revision->created_at;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'in_review' => 'In Review',
            'approved_by_reviewer' => 'Siap Approval',
            'in_approval' => 'In Approval',
            'published' => 'Published',
            'obsolete' => 'Obsolete',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    private function statusVariant(string $status): string
    {
        return match ($status) {
            'published' => 'success',
            'in_review', 'in_approval', 'approved_by_reviewer' => 'warning',
            'obsolete' => 'danger',
            default => 'neutral',
        };
    }

    private function workflowEventLabel(string $eventType): string
    {
        return match ($eventType) {
            'create_draft' => 'Draft dokumen dibuat',
            'submit_review' => 'Dokumen diajukan untuk review',
            'review_return' => 'Dokumen dikembalikan reviewer',
            'review_pass' => 'Dokumen lolos review',
            'approve' => 'Dokumen disetujui',
            'publish' => 'Dokumen dipublish',
            'reject' => 'Dokumen ditolak',
            'download' => 'Dokumen diunduh',
            'unlock' => 'Kunci editor dibuka',
            default => ucfirst(str_replace('_', ' ', $eventType)),
        };
    }

    private function trailEntityLabel(string $tableName): string
    {
        return match ($tableName) {
            'qmh_rapats' => 'Rapat',
            'qmh_audits' => 'Audit',
            'qmh_kums' => 'KUM',
            'qmh_audit_temuans' => 'Temuan Audit',
            'qmh_rapat_action_items' => 'Action Item',
            default => 'Data',
        };
    }

    private function trailActionLabel(string $action): string
    {
        return match (strtoupper($action)) {
            'CREATE' => 'ditambahkan',
            'UPDATE' => 'diperbarui',
            'DELETE' => 'dihapus',
            default => strtolower($action),
        };
    }

    private function trailDestination(string $tableName): string
    {
        return match ($tableName) {
            'qmh_rapats', 'qmh_rapat_action_items' => route('quality.rapat.index'),
            'qmh_audits', 'qmh_audit_temuans' => route('quality.audit.index'),
            'qmh_kums' => route('quality.kum.index'),
            default => route('quality.governance.index'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardFilterQuery(?int $clause, ?string $docType, int $period): array
    {
        return array_filter([
            'clause' => $clause,
            'doc_type' => $docType,
            'period' => $period,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function documentFilterQuery(?int $clause, ?string $docType): array
    {
        return array_filter([
            'clause' => $clause,
            'doc_type' => $docType,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
