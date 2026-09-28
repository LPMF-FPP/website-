<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\SampleStatus;
use App\Models\Delivery;
use App\Models\Sample;
use App\Models\Suspect;
use App\Models\TestRequest;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplementalRequestService
{
    /**
     * @param  array<string, mixed>  $sampleData
     */
    public function create(TestRequest $parent, User $actor, string $reason, bool $collectionConfirmed, array $sampleData): TestRequest
    {
        return DB::transaction(function () use ($parent, $actor, $reason, $collectionConfirmed, $sampleData): TestRequest {
            $lockedParent = TestRequest::query()->lockForUpdate()->findOrFail($parent->id);
            $delivery = $lockedParent->delivery()->lockForUpdate()->first();

            if (! in_array($lockedParent->status, ['ready_for_delivery', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'collection_confirmation' => 'Suplemen hanya dapat dibuat untuk permintaan yang sudah berada pada tahap penyerahan.',
                ]);
            }

            if (! $collectionConfirmed || ! $this->hasBeenCollected($lockedParent, $delivery)) {
                throw ValidationException::withMessages([
                    'collection_confirmation' => 'Pastikan dan konfirmasikan bahwa hasil permintaan awal sudah diambil sebelum membuat suplemen tertaut.',
                ]);
            }

            if ($lockedParent->status === 'completed' && ! $delivery?->hasBeenCollected()) {
                $delivery ??= Delivery::query()->create([
                    'request_id' => $lockedParent->id,
                    'delivered_by' => $actor->id,
                    'delivery_date' => $lockedParent->completed_at ?? now(),
                    'status' => DeliveryStatus::PENDING,
                ]);

                $previousDeliveryStatus = $delivery->status;
                if ($delivery->status === DeliveryStatus::PENDING || $delivery->status === DeliveryStatus::REOPENED) {
                    $delivery->status = DeliveryStatus::PENDING;
                    if (! $delivery->status->canTransitionTo(DeliveryStatus::READY)) {
                        throw ValidationException::withMessages([
                            'collection_confirmation' => 'Status penyerahan tidak dapat dikonfirmasi sebagai telah diambil.',
                        ]);
                    }
                    $delivery->status = DeliveryStatus::READY;
                }

                if (! $delivery->status->canTransitionTo(DeliveryStatus::COLLECTED)) {
                    throw ValidationException::withMessages([
                        'collection_confirmation' => 'Status penyerahan tidak dapat dikonfirmasi sebagai telah diambil.',
                    ]);
                }

                $delivery->forceFill([
                    'status' => DeliveryStatus::COLLECTED,
                    'collected_at' => now(),
                ])->save();

                ActivityLogger::log(
                    'DELIVERY_COLLECTION_CONFIRMED',
                    null,
                    $delivery,
                    ['status' => $previousDeliveryStatus->value ?? (string) $previousDeliveryStatus],
                    ['status' => DeliveryStatus::COLLECTED->value, 'collected_at' => $delivery->collected_at?->toISOString()],
                    ['test_request_id' => $lockedParent->id, 'confirmed_by' => $actor->id],
                    $actor->id
                );
            }

            $lockedParent->loadMissing('suspects');

            $supplement = TestRequest::query()->create([
                'parent_test_request_id' => $lockedParent->id,
                'supplement_reason' => $reason,
                'investigator_id' => $lockedParent->investigator_id,
                'user_id' => $actor->id,
                'suspect_name' => $lockedParent->suspect_name,
                'suspect_gender' => $lockedParent->suspect_gender,
                'suspect_age' => $lockedParent->suspect_age,
                'suspect_address' => $lockedParent->suspect_address,
                'case_number' => $lockedParent->case_number,
                'letter_date' => $lockedParent->letter_date,
                'case_description' => $lockedParent->case_description,
                'incident_date' => $lockedParent->incident_date,
                'incident_location' => $lockedParent->incident_location,
                'tujuan' => $lockedParent->tujuan,
                'status' => 'received',
                'submitted_at' => now(),
                'received_at' => now(),
            ]);

            foreach ($lockedParent->suspects as $suspect) {
                Suspect::query()->create([
                    'test_request_id' => $supplement->id,
                    'name' => $suspect->name,
                    'gender' => $suspect->gender,
                    'age' => $suspect->age,
                    'order_no' => $suspect->order_no,
                ]);
            }

            $sample = Sample::query()->create([
                'test_request_id' => $supplement->id,
                'short_description' => $sampleData['short_description'],
                'sample_form' => $sampleData['sample_form'],
                'sample_category' => $sampleData['sample_category'],
                'other_sample_category' => $sampleData['sample_category'] === 'other'
                    ? ($sampleData['other_sample_category'] ?? null)
                    : null,
                'sample_description' => $sampleData['sample_description'] ?? null,
                'sample_weight' => $sampleData['sample_weight'] ?? null,
                'package_quantity' => $sampleData['package_quantity'],
                'unit' => $sampleData['unit'],
                'condition' => $sampleData['condition'],
                'sample_status' => 'received',
                'status' => SampleStatus::FORM_SUBMITTED->value,
                'test_methods' => null,
                'requested_test_methods' => null,
                'active_substance' => null,
            ]);

            ActivityLogger::log(
                'SUPPLEMENTAL_REQUEST_CREATED',
                null,
                $supplement,
                null,
                ['status' => $supplement->status, 'receipt_number' => $supplement->receipt_number],
                [
                    'parent_test_request_id' => $lockedParent->id,
                    'parent_receipt_number' => $lockedParent->receipt_number,
                    'sample_id' => $sample->id,
                    'sample_code' => $sample->sample_code,
                    'supplement_reason' => $reason,
                    'collection_confirmed' => $collectionConfirmed,
                ],
                $actor->id
            );

            return $supplement;
        }, attempts: 3);
    }

    private function hasBeenCollected(TestRequest $request, $delivery): bool
    {
        return $request->status === 'completed'
            || $delivery?->collected_at !== null
            || $delivery?->status === DeliveryStatus::COLLECTED;
    }
}
