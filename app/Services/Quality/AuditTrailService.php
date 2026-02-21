<?php

namespace App\Services\Quality;

use App\Constants\SystemActor;
use App\Models\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AuditTrailService
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $tableName,
        int|string $recordId,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $changedBy = null,
        ?string $source = null,
        ?string $reason = null,
    ): AuditTrail {
        return AuditTrail::query()->create([
            'table_name' => $tableName,
            'record_id' => (string) $recordId,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_by' => $changedBy ? (string) $changedBy : SystemActor::ID,
            'source' => $source ?? $this->detectSource(),
            'changed_at' => now(),
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'session_id' => $this->request->hasSession() ? $this->request->session()->getId() : null,
            'request_id' => $this->request->header('X-Request-Id'),
            'change_reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters = []): Builder
    {
        return AuditTrail::query()
            ->when(isset($filters['table_name']), fn (Builder $q) => $q->where('table_name', (string) $filters['table_name']))
            ->when(isset($filters['record_id']), fn (Builder $q) => $q->where('record_id', (string) $filters['record_id']))
            ->when(isset($filters['source']), fn (Builder $q) => $q->where('source', (string) $filters['source']))
            ->when(isset($filters['action']), fn (Builder $q) => $q->where('action', (string) $filters['action']))
            ->when(isset($filters['from']), fn (Builder $q) => $q->where('changed_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $q) => $q->where('changed_at', '<=', $filters['to']))
            ->orderByDesc('changed_at');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function export(array $filters = []): array
    {
        return $this->query($filters)
            ->limit((int) ($filters['limit'] ?? 5000))
            ->get()
            ->map(fn (AuditTrail $trail) => [
                'id' => $trail->id,
                'table_name' => $trail->table_name,
                'record_id' => $trail->record_id,
                'action' => $trail->action,
                'source' => $trail->source,
                'changed_by' => $trail->changed_by,
                'changed_at' => $trail->changed_at?->toIso8601String(),
                'change_reason' => $trail->change_reason,
                'old_values' => $trail->old_values,
                'new_values' => $trail->new_values,
            ])
            ->all();
    }

    private function detectSource(): string
    {
        if ($this->request->is('api/*')) {
            return 'api';
        }

        if (app()->runningInConsole()) {
            return 'cli';
        }

        return 'web';
    }
}
