<?php

namespace App\View\Components;

use App\Models\Sample;
use App\Enums\TestProcessStage;
use Illuminate\View\Component;

class SampleStatusBadge extends Component
{
    public $sample;
    public $stage;
    public $showLabel;

    public function __construct(Sample $sample, ?TestProcessStage $stage = null, bool $showLabel = true)
    {
        $this->sample = $sample;
        $this->stage = $stage;
        $this->showLabel = $showLabel;
    }

    public function render()
    {
        $colors = $this->getStatusColors();
        $label = $this->sample->status->label();

        return view('components.sample-status-badge', [
            'colors' => $colors,
            'label' => $label
        ]);
    }

    protected function getStatusColors(): array
    {
        return match($this->sample->status->value) {
            'form_submitted' => ['bg-blue-100', 'text-blue-800'],

            'admin_pending', 'preparation_pending', 'instrumentation_pending', 'interpretation_pending'
                => ['bg-yellow-100', 'text-yellow-800'],

            'admin_in_progress', 'preparation_in_progress', 'instrumentation_in_progress', 'interpretation_in_progress'
                => ['bg-orange-100', 'text-orange-800'],

            'admin_done', 'preparation_done', 'instrumentation_done', 'interpretation_done'
                => ['bg-green-100', 'text-green-800'],

            'ready_for_delivery' => ['bg-teal-100', 'text-teal-800'],

            default => ['bg-gray-100', 'text-gray-800']
        };
    }
}
