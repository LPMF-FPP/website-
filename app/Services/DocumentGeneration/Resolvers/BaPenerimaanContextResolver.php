<?php

namespace App\Services\DocumentGeneration\Resolvers;

use App\Enums\DocumentType;
use App\Models\TestRequest;
use App\Services\DocumentGeneration\AbstractContextResolver;

class BaPenerimaanContextResolver extends AbstractContextResolver
{
    public function getDocumentType(): DocumentType
    {
        return DocumentType::BA_PENERIMAAN;
    }

    public function resolve($contextId): array
    {
        /** @var TestRequest $request */
        $request = TestRequest::with(['investigator', 'samples'])
            ->findOrFail($contextId);

        return array_merge($this->getCommonContext(), [
            'request' => $request,
            'investigator' => $request->investigator,
            'samples' => $request->samples,
            'request_number' => $request->request_number,
            'receipt_number' => $request->receipt_number,
            'request_date' => $request->created_at,
        ]);
    }

    public function getSampleContext(): array
    {
        return array_merge($this->getCommonContext(), [
            'request' => $this->getMockRequest(),
            'investigator' => $this->getMockInvestigator(),
            'samples' => $this->getMockSamples(),
            'request_number' => 'REQ-001-XII-2025',
            'receipt_number' => 'RESI-001-XII-2025',
            'request_date' => now(),
        ]);
    }

    private function getMockRequest(): \stdClass
    {
        $obj = new \stdClass();
        $obj->request_number = 'REQ-001-XII-2025';
        $obj->receipt_number = 'RESI-001-XII-2025';
        $obj->created_at = now();
        $obj->received_at = now();
        $obj->notes = 'Contoh permintaan pengujian sampel';
        $obj->case_number = 'SP/001/XII/2025';
        $obj->to_office = 'Kepala Sub Satker Farmapol Pusdokkes Polri';
        $obj->investigator = $this->getMockInvestigator();
        $obj->samples = $this->getMockSamples();
        return $obj;
    }

    private function getMockInvestigator(): \stdClass
    {
        $obj = new \stdClass();
        $obj->name = 'Dr. John Doe';
        $obj->nrp = 'NRP12345';
        $obj->rank = 'AKBP';
        $obj->unit = 'Unit Pengujian';
        $obj->jurisdiction = 'Polda Metro Jaya';
        $obj->phone = '08123456789';
        return $obj;
    }

    private function getMockSamples(): \Illuminate\Support\Collection
    {
        $sample1 = new \stdClass();
        $sample1->sample_code = 'W001XII2025';
        $sample1->sample_name = 'Sampel Tablet Warna Biru';
        $sample1->description = 'Sampel berbentuk tablet warna biru';
        $sample1->quantity = 1;
        $sample1->unit = 'buah';
        $sample1->test_methods = ['uv_vis', 'gc_ms'];
        $sample1->sample_condition = 'Baik, tersimpan dalam kantong plastik';
        $sample1->notes = '-';
        
        $sample2 = new \stdClass();
        $sample2->sample_code = 'W002XII2025';
        $sample2->sample_name = 'Sampel Cairan Bening';
        $sample2->description = 'Sampel berupa cairan bening dalam botol';
        $sample2->quantity = 2;
        $sample2->unit = 'botol';
        $sample2->test_methods = ['lc_ms'];
        $sample2->sample_condition = 'Baik, botol tertutup rapat';
        $sample2->notes = '-';
        
        return collect([$sample1, $sample2]);
    }
}
