<?php

namespace App\Services\DocumentGeneration\Resolvers;

use App\Enums\DocumentType;
use App\Models\SampleTestProcess;
use App\Services\DocumentGeneration\AbstractContextResolver;

class LhuContextResolver extends AbstractContextResolver
{
    public function getDocumentType(): DocumentType
    {
        return DocumentType::LHU;
    }

    public function resolve($contextId): array
    {
        /** @var SampleTestProcess $process */
        $process = SampleTestProcess::with([
            'sample.testRequest.investigator',
            'sample.testRequest',
            'performer'
        ])->findOrFail($contextId);

        // Get or generate LHU number
        $lhuNumber = $process->lhu_number ?? $this->generateLhuNumber($process);

        return array_merge($this->getCommonContext(), [
            'process' => $process,
            'sample' => $process->sample,
            'request' => $process->sample->testRequest,
            'investigator' => $process->sample->testRequest->investigator,
            'noLHU' => $lhuNumber,
            'forcedActiveSubstance' => $process->sample->active_substance,
        ]);
    }

    public function getSampleContext(): array
    {
        return array_merge($this->getCommonContext(), [
            'process' => $this->getMockProcess(),
            'sample' => $this->getMockSample(),
            'request' => $this->getMockRequest(),
            'investigator' => $this->getMockInvestigator(),
            'noLHU' => 'FLHU001',
            'forcedActiveSubstance' => 'Sample Active Substance',
        ]);
    }

    private function generateLhuNumber(SampleTestProcess $process): string
    {
        // This would use NumberingService in real implementation
        return 'FLHU' . str_pad($process->id, 3, '0', STR_PAD_LEFT);
    }

    private function getMockProcess(): array
    {
        return [
            'stage' => 'interpretation',
            'instrument' => 'GC-MS',
            'test_result' => 'positive',
            'detected_substance' => 'Methamphetamine',
            'notes' => 'Test completed successfully',
            'started_at' => now()->subDays(2),
            'completed_at' => now(),
        ];
    }

    private function getMockSample(): array
    {
        return [
            'sample_code' => 'W001XII2025',
            'description' => 'Sample test material',
            'active_substance' => 'Methamphetamine',
            'received_at' => now()->subDays(3),
        ];
    }

    private function getMockRequest(): array
    {
        return [
            'request_number' => 'REQ-001-XII-2025',
            'created_at' => now()->subDays(5),
        ];
    }

    private function getMockInvestigator(): array
    {
        return [
            'name' => 'Dr. John Doe',
            'nrp' => 'NRP12345',
            'unit' => 'Forensic Unit',
        ];
    }
}
