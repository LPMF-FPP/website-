<?php

namespace App\Services\DocumentGeneration\Resolvers;

use App\Enums\DocumentType;
use App\Models\Delivery;
use App\Services\DocumentGeneration\AbstractContextResolver;

class BaPenyerahanContextResolver extends AbstractContextResolver
{
    public function getDocumentType(): DocumentType
    {
        return DocumentType::BA_PENYERAHAN;
    }

    public function resolve($contextId): array
    {
        /** @var Delivery $delivery */
        $delivery = Delivery::with([
            'request.investigator',
            'request.samples'
        ])->findOrFail($contextId);

        return array_merge($this->getCommonContext(), [
            'delivery' => $delivery,
            'request' => $delivery->request,
            'investigator' => $delivery->request->investigator,
            'samples' => $delivery->request->samples,
            'receipt_number' => $delivery->request->receipt_number,
        ]);
    }

    public function getSampleContext(): array
    {
        return array_merge($this->getCommonContext(), [
            'delivery' => $this->getMockDelivery(),
            'request' => $this->getMockRequest(),
            'investigator' => $this->getMockInvestigator(),
            'samples' => $this->getMockSamples(),
            'receipt_number' => 'RESI-001-XII-2025',
        ]);
    }

    private function getMockDelivery(): \stdClass
    {
        $obj = new \stdClass();
        $obj->delivered_at = now();
        $obj->received_by = 'John Doe';
        $obj->notes = 'Delivered successfully';
        $obj->request = $this->getMockRequest();
        return $obj;
    }

    private function getMockRequest(): \stdClass
    {
        $obj = new \stdClass();
        $obj->request_number = 'REQ-001-XII-2025';
        $obj->receipt_number = 'RESI-001-XII-2025';
        $obj->created_at = now()->subDays(7);
        $obj->case_number = 'SP/001/XII/2025';
        $obj->investigator = $this->getMockInvestigator();
        $obj->samples = $this->getMockSamples();
        return $obj;
    }

    private function getMockInvestigator(): \stdClass
    {
        $obj = new \stdClass();
        $obj->name = 'Dr. Jane Smith';
        $obj->nrp = 'NRP67890';
        $obj->rank = 'KOMPOL';
        $obj->unit = 'Investigation Unit';
        $obj->jurisdiction = 'Polda Jawa Barat';
        return $obj;
    }

    private function getMockSamples(): \Illuminate\Support\Collection
    {
        $sample1 = new \stdClass();
        $sample1->sample_code = 'W001XII2025';
        $sample1->sample_name = 'Sample Tablet Biru';
        $sample1->description = 'Tablet warna biru';
        $sample1->test_methods = ['uv_vis'];
        $sample1->quantity = 10;
        $sample1->unit = 'tablet';
        $sample1->quantity_unit = 'tablet';
        $sample1->package_quantity = 1;
        $sample1->package_type = 'kantong plastik';
        $sample1->packaging_type = 'kantong plastik';
        $sample1->lhu_number = 'LHU/001/XII/2025/FARMAPOL';
        $sample1->flhu_number = null;
        $sample1->report_number = 'LHU/001/XII/2025/FARMAPOL';
        $sample1->metadata = null;
        $sample1->process = null;
        $sample1->test_process = null;
        $sample1->latest_process = null;
        $sample1->interpretation_process = null;
        $sample1->sample_test_process = null;
        
        $sample2 = new \stdClass();
        $sample2->sample_code = 'W002XII2025';
        $sample2->sample_name = 'Sample Cairan';
        $sample2->description = 'Cairan bening';
        $sample2->test_methods = ['lc_ms'];
        $sample2->quantity = 500;
        $sample2->unit = 'ml';
        $sample2->quantity_unit = 'ml';
        $sample2->package_quantity = 2;
        $sample2->package_type = 'botol';
        $sample2->packaging_type = 'botol';
        $sample2->lhu_number = 'LHU/002/XII/2025/FARMAPOL';
        $sample2->flhu_number = null;
        $sample2->report_number = 'LHU/002/XII/2025/FARMAPOL';
        $sample2->metadata = null;
        $sample2->process = null;
        $sample2->test_process = null;
        $sample2->latest_process = null;
        $sample2->interpretation_process = null;
        $sample2->sample_test_process = null;
        
        return collect([$sample1, $sample2]);
    }
}
