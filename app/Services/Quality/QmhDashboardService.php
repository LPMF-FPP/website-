<?php

namespace App\Services\Quality;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class QmhDashboardService
{
    public function getPulseStats(): array
    {
        $clauses = [4, 5, 6, 7, 8];
        $stats = [];

        foreach ($clauses as $clause) {
            $stats[$clause] = [
                'total' => 0,
                'draft' => 0,
                'review' => 0,
                'published' => 0,
                'overdue' => 0,
                'health' => 'healthy',
            ];
        }

        $rows = DB::table('qmh_documents')
            ->leftJoin('qmh_document_revisions', 'qmh_documents.current_revision_id', '=', 'qmh_document_revisions.id')
            ->select(
                'qmh_documents.clause',
                'qmh_document_revisions.status',
                'qmh_document_revisions.submitted_at',
                'qmh_document_revisions.created_at'
            )
            ->whereIn('qmh_documents.clause', $clauses)
            ->get();

        $now = now();

        foreach ($rows as $row) {
            $c = $row->clause;
            if (! isset($stats[$c])) {
                continue;
            }

            $stats[$c]['total']++;

            if (! $row->status) {
                continue;
            }

            switch ($row->status) {
                case 'draft':
                    $stats[$c]['draft']++;
                    if (! empty($row->created_at)) {
                        $days = abs($now->diffInDays(Carbon::parse($row->created_at), false));
                        if ($days > 30) {
                            $stats[$c]['overdue']++;
                        }
                    }
                    break;
                case 'in_review':
                    $stats[$c]['review']++;
                    if (! empty($row->submitted_at)) {
                        $days = abs($now->diffInDays(Carbon::parse($row->submitted_at), false));
                        if ($days > 7) {
                            $stats[$c]['overdue']++;
                        }
                    }
                    break;
                case 'published':
                    $stats[$c]['published']++;
                    break;
            }
        }

        foreach ($clauses as $c) {
            $s = $stats[$c];
            if ($s['overdue'] > 3) {
                $stats[$c]['health'] = 'critical';
            } elseif ($s['overdue'] > 0) {
                $stats[$c]['health'] = 'warning';
            } elseif ($s['review'] > 2 || $s['draft'] > 5) {
                $stats[$c]['health'] = 'active';
            } else {
                $stats[$c]['health'] = 'healthy';
            }
        }

        return [
            'clauses' => $stats,
            'global_pulse' => $this->calculateGlobalPulse($stats),
            'user_tasks' => 0,
        ];
    }

    private function calculateGlobalPulse(array $stats): string
    {
        $totalOverdue = collect($stats)->sum('overdue');
        if ($totalOverdue > 5) {
            return 'critical';
        }
        if ($totalOverdue > 0) {
            return 'warning';
        }

        return 'healthy';
    }
}
