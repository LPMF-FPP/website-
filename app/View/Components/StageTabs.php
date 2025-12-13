<?php

namespace App\View\Components;

use App\Enums\TestProcessStage;
use Illuminate\View\Component;

class StageTabs extends Component
{
    public $currentStage;
    public $baseUrl;

    public function __construct(?string $currentStage = null, string $baseUrl = '')
    {
        $this->currentStage = $currentStage ? TestProcessStage::from($currentStage) : null;
        $this->baseUrl = $baseUrl;
    }

    public function render()
    {
        return view('components.stage-tabs', [
            'stages' => TestProcessStage::cases()
        ]);
    }
}
