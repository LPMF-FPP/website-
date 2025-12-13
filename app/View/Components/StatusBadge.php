<?php

namespace App\View\Components;

use Illuminate\View\Component;

class StatusBadge extends Component
{
    public string $status;
    public ?string $label;
    public ?string $customClass;

    protected array $statusConfig = [
        'submitted' => ['label' => 'Diajukan', 'class' => 'badge-info'],
        'verified' => ['label' => 'Diverifikasi', 'class' => 'badge-success'],
        'received' => ['label' => 'Diterima Lab', 'class' => 'badge-secondary'],
        'in_testing' => ['label' => 'Sedang diuji', 'class' => 'badge-warning'],
        'analysis' => ['label' => 'Analisis', 'class' => 'badge-info'],
        'quality_check' => ['label' => 'Quality Check', 'class' => 'badge-info'],
        'ready_for_delivery' => ['label' => 'Siap diserahkan', 'class' => 'badge-success'],
        'completed' => ['label' => 'Selesai', 'class' => 'badge-success'],
    ];

    public function __construct(string $status, ?string $label = null, ?string $customClass = null)
    {
        $this->status = $status;
        $this->label = $label;
        $this->customClass = $customClass;
    }

    public function render()
    {
        $config = $this->statusConfig[$this->status] ?? [
            'label' => ucfirst(str_replace('_', ' ', $this->status)),
            'class' => 'badge-secondary',
        ];

        return view('components.status-badge', [
            'label' => $this->label ?? $config['label'],
            'class' => $this->customClass ?? $config['class'],
        ]);
    }
}
