<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Enums\DeliveryStatus;
use App\Enums\SampleStatus;
use App\Models\CustomerSurvey;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\EvidenceUnit;
use App\Models\Investigator;
use App\Models\RemainingUnit;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use App\Models\TestRequest;
use App\Models\User;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsappOutbox;
use App\Services\ReopenedSampleRemovalService;
use App\Services\WhatsApp\MilestoneNotificationService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdditionalSampleLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(SystemSettingSeeder::class);
        $this->seed(PermissionSeeder::class);
        settings_fake(['notifications.whatsapp.enabled' => false], true);
        Queue::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_active_request_can_review_one_unprocessed_sample_without_resetting_existing_samples(): void
    {
        $request = $this->makeRequest('in_testing');
        $processed = $this->makeSample($request, 'READY-OLD', SampleStatus::PREPARATION_PENDING->value);
        $this->createCompletedStages($processed);
        $added = $this->makeSample($request, 'NEW-ADDED', SampleStatus::FORM_SUBMITTED->value);

        $detail = $this->actingAs($this->admin)->get(route('testing.show', $request));
        $detail->assertOk()
            ->assertSee('sampel menunggu kaji ulang')
            ->assertSee(route('testing.additional-samples.review', [$request, $added]))
            ->assertDontSee('Kirim ke Penyerahan');

        $this->postAdditionalReview($request, $added)->assertRedirect(route('testing.show', $request));

        $this->assertSame('in_testing', $request->fresh()->status);
        $this->assertSame(SampleStatus::PREPARATION_PENDING->value, $added->fresh()->status);
        $this->assertSame(3, SampleTestProcess::query()->where('sample_id', $added->id)->count());
        $this->assertSame(3, SampleTestProcess::query()->where('sample_id', $processed->id)->count());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'ADDITIONAL_SAMPLE_REVIEWED',
            'subject_id' => $added->id,
        ]);

        $this->from(route('testing.additional-samples.review', [$request, $added]))
            ->post(route('testing.additional-samples.store', [$request, $added]), $this->reviewPayload($request, $added))
            ->assertSessionHasErrors('samples.0.id');

        $this->assertSame(3, SampleTestProcess::query()->where('sample_id', $added->id)->count());
    }

    public function test_initial_review_does_not_silently_select_another_request_for_an_ineligible_explicit_id(): void
    {
        $eligible = $this->makeRequest('received');
        $this->makeSample($eligible, 'ELIGIBLE', SampleStatus::FORM_SUBMITTED->value);
        $ineligible = $this->makeRequest('in_testing');

        $response = $this->actingAs($this->admin)
            ->get(route('review.create', ['request_id' => $ineligible->id]));

        $response->assertOk()
            ->assertSee('Permintaan tidak lagi berada di antrean kaji ulang')
            ->assertViewHas('selectedRequest', null)
            ->assertViewHas('ineligibleSelectedRequest', true);
    }

    public function test_additional_sample_actions_require_their_domain_permissions(): void
    {
        $request = $this->makeRequest('in_testing');
        $sample = $this->makeSample($request, 'UNPROCESSED', SampleStatus::FORM_SUBMITTED->value);
        $unprivileged = User::factory()->create(['role' => 'petugas', 'email_verified_at' => now()]);

        $this->actingAs($unprivileged)
            ->get(route('testing.additional-samples.review', [$request, $sample]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Anda tidak memiliki akses ke halaman ini.');

        $readyRequest = $this->makeRequest('ready_for_delivery');
        Delivery::query()->create([
            'request_id' => $readyRequest->id,
            'delivered_by' => $this->admin->id,
            'delivery_date' => now(),
            'status' => DeliveryStatus::READY,
        ]);

        $this->actingAs($unprivileged)
            ->post(route('delivery.reopen-additional-sample.store', $readyRequest), $this->samplePayload() + [
                'confirmation' => '1',
                'supplement_reason' => 'Sampel tambahan.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Anda tidak memiliki akses ke halaman ini.');

        $this->assertSame('ready_for_delivery', $readyRequest->fresh()->status);
    }

    public function test_additional_review_rejects_a_sample_owned_by_another_request(): void
    {
        $request = $this->makeRequest('in_testing');
        $otherRequest = $this->makeRequest('in_testing');
        $foreignSample = $this->makeSample($otherRequest, 'FOREIGN-SAMPLE', SampleStatus::FORM_SUBMITTED->value);

        $this->actingAs($this->admin)
            ->get(route('testing.additional-samples.review', [$request, $foreignSample]))
            ->assertNotFound();

        $this->assertSame(0, SampleTestProcess::query()->where('sample_id', $foreignSample->id)->count());
    }

    public function test_additional_review_rejects_inactive_or_non_staff_analysts(): void
    {
        $request = $this->makeRequest('in_testing');
        $sample = $this->makeSample($request, 'ANALYST-GUARD', SampleStatus::FORM_SUBMITTED->value);

        foreach ([
            User::factory()->create(['role' => 'analis', 'is_active' => false]),
            User::factory()->create(['role' => 'investigator', 'is_active' => true]),
        ] as $analyst) {
            $payload = $this->reviewPayload($request, $sample);
            $payload['samples'][0]['assigned_analyst_id'] = $analyst->id;

            $this->actingAs($this->admin)
                ->post(route('testing.additional-samples.store', [$request, $sample]), $payload)
                ->assertSessionHasErrors('samples.0.assigned_analyst_id');
        }

        $this->assertSame(0, SampleTestProcess::query()->where('sample_id', $sample->id)->count());
    }

    public function test_active_request_has_a_dedicated_sample_intake_that_routes_to_single_sample_review(): void
    {
        $request = $this->makeRequest('in_testing');

        $this->actingAs($this->admin)
            ->get(route('testing.additional-samples.create', $request))
            ->assertOk()
            ->assertSee('Tambah Sampel pada Pengujian Aktif');

        $response = $this->actingAs($this->admin)->post(
            route('testing.additional-samples.store-new', $request),
            $this->samplePayload() + ['supplement_reason' => 'Sampel tertinggal pada permintaan awal.']
        );

        $sample = Sample::query()->where('test_request_id', $request->id)->firstOrFail();
        $response->assertRedirect(route('testing.additional-samples.review', [$request, $sample]));
        $this->assertSame('in_testing', $request->fresh()->status);
        $this->assertSame(SampleStatus::FORM_SUBMITTED->value, $sample->status);
        $this->assertSame(0, $sample->testProcesses()->count());

        $this->actingAs($this->admin)
            ->get(route('testing.additional-samples.review', [$request, $sample]))
            ->assertOk()
            ->assertSee('Kaji Ulang Sampel Tambahan');
    }

    public function test_collected_request_cannot_be_reopened_and_must_use_supplement(): void
    {
        $request = $this->makeRequest('completed');
        Delivery::query()->create([
            'request_id' => $request->id,
            'delivered_by' => $this->admin->id,
            'delivery_date' => now()->subDay(),
            'status' => DeliveryStatus::COLLECTED,
            'collected_at' => now()->subDay(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('delivery.reopen-additional-sample.store', $request), $this->samplePayload() + [
                'confirmation' => '1',
                'supplement_reason' => 'Tidak boleh reopen hasil yang sudah diambil.',
            ])
            ->assertSessionHasErrors('reopen_reason');

        $this->assertSame('completed', $request->fresh()->status);
        $this->assertSame(0, $request->deliveryReopenings()->count());
        $this->assertSame(0, $request->supplementalRequests()->count());
    }

    public function test_ready_request_can_be_reopened_without_removing_old_documents_or_processes(): void
    {
        Storage::fake('public');
        $request = $this->makeRequest('ready_for_delivery');
        $oldSample = $this->makeSample($request, 'READY-OLD', SampleStatus::READY_FOR_DELIVERY->value);
        $this->createCompletedStages($oldSample);
        $delivery = Delivery::query()->create([
            'request_id' => $request->id,
            'delivered_by' => $this->admin->id,
            'delivery_date' => now()->subDay(),
            'status' => DeliveryStatus::READY,
            'handover_cycle' => 1,
        ]);
        $oldDocument = Document::factory()->generated()->create([
            'investigator_id' => $request->investigator_id,
            'test_request_id' => $request->id,
            'document_type' => 'ba_penyerahan',
            'filename' => 'cycle-1.pdf',
            'original_filename' => 'cycle-1.pdf',
            'file_path' => 'archive/ba-cycle-1.pdf',
            'path' => 'archive/ba-cycle-1.pdf',
        ]);
        Storage::disk('public')->put($oldDocument->file_path, 'previous handover PDF');
        $outbox = app(MilestoneNotificationService::class)->queue(
            $request->id,
            'READY_FOR_PICKUP',
            '08123456789',
            '628123456789@s.whatsapp.net',
            'Penyidik Uji',
            'Notifikasi lama untuk siklus pertama.'
        );
        $pendingNotification = WhatsAppMessageLog::query()
            ->where('source_type', WhatsappOutbox::class)
            ->where('source_id', $outbox->id)
            ->firstOrFail();
        $pendingNotification->update(['status' => WhatsAppMessageLog::STATUS_PENDING]);
        $outbox->update(['status' => 'queued']);

        $response = $this->actingAs($this->admin)
            ->post(route('delivery.reopen-additional-sample.store', $request), $this->samplePayload() + [
                'confirmation' => '1',
                'supplement_reason' => 'Sampel tertinggal pada tahap penerimaan.',
            ]);

        $response->assertRedirect(route('testing.show', $request));
        $request->refresh();
        $delivery->refresh();
        $oldDocument->refresh();

        $this->assertSame('in_testing', $request->status);
        $this->assertNull($request->ready_for_delivery_at);
        $this->assertSame(DeliveryStatus::REOPENED, $delivery->status);
        $this->assertSame(2, $delivery->handover_cycle);
        $this->assertNotNull($oldDocument->extra['superseded_at'] ?? null);
        $this->assertSame(WhatsAppMessageLog::STATUS_BLOCKED, $pendingNotification->fresh()->status);
        $this->assertSame('failed', WhatsappOutbox::query()->findOrFail($pendingNotification->source_id)->status);
        Storage::disk('public')->assertExists('archive/ba-cycle-1.pdf');
        $this->actingAs($this->admin)
            ->get(route('delivery.handover.archive', [$delivery, $oldDocument]))
            ->assertOk();
        $this->actingAs($this->admin)
            ->get(route('testing.show', $request))
            ->assertOk()
            ->assertSee('Riwayat penyerahan sebelumnya')
            ->assertSee('cycle-1.pdf');
        $this->assertSame(3, SampleTestProcess::query()->where('sample_id', $oldSample->id)->count());
        $this->assertDatabaseHas('delivery_reopenings', [
            'test_request_id' => $request->id,
            'delivery_id' => $delivery->id,
            'handover_cycle' => 2,
            'reopened_by' => $this->admin->id,
        ]);

        $newSample = Sample::query()->where('test_request_id', $request->id)->where('short_description', 'Sampel tambahan')->firstOrFail();
        $this->assertSame($newSample->id, $request->deliveryReopenings()->firstOrFail()->sample_id);
        $this->assertDatabaseCount('sample_test_processes', 3);
        $this->actingAs($this->admin)
            ->post(route('testing.ready-for-delivery', $request))
            ->assertSessionHasErrors('error');

        $this->postAdditionalReview($request, $newSample)->assertRedirect(route('testing.show', $request));
        $this->completeAllStages($newSample);
        $this->actingAs($this->admin)->post(route('testing.ready-for-delivery', $request))->assertRedirect(route('delivery.show', $request));

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::READY, $delivery->status);
        $this->assertSame(2, $delivery->handover_cycle);
        $this->assertSame('ready_for_delivery', $request->fresh()->status);
    }

    public function test_reopen_is_blocked_while_an_old_cycle_notification_status_is_unknown(): void
    {
        $request = $this->makeRequest('ready_for_delivery');
        Delivery::query()->create([
            'request_id' => $request->id,
            'delivered_by' => $this->admin->id,
            'delivery_date' => now(),
            'status' => DeliveryStatus::READY,
        ]);
        $outbox = app(MilestoneNotificationService::class)->queue(
            $request->id,
            'READY_FOR_PICKUP',
            '08123456789',
            '628123456789@s.whatsapp.net',
            'Penyidik Uji',
            'Notifikasi yang hasilnya tidak diketahui.'
        );
        $messageLog = WhatsAppMessageLog::query()
            ->where('source_type', WhatsappOutbox::class)
            ->where('source_id', $outbox->id)
            ->firstOrFail();
        $messageLog->update(['status' => WhatsAppMessageLog::STATUS_UNKNOWN]);

        $this->actingAs($this->admin)
            ->post(route('delivery.reopen-additional-sample.store', $request), $this->samplePayload() + [
                'confirmation' => '1',
                'supplement_reason' => 'Sampel tambahan.',
            ])
            ->assertSessionHasErrors('reopen_reason');

        $this->assertSame('ready_for_delivery', $request->fresh()->status);
        $this->assertSame(0, $request->deliveryReopenings()->count());
        $this->assertSame(WhatsAppMessageLog::STATUS_UNKNOWN, $messageLog->fresh()->status);
    }

    public function test_completed_but_not_collected_request_can_reopen_and_preserves_its_prior_completion_and_survey(): void
    {
        $completedAt = now()->subDays(2);
        $request = $this->makeRequest('completed');
        $request->forceFill([
            'completed_at' => $completedAt,
            'ready_for_delivery_at' => $completedAt,
        ])->save();
        $this->makeSample($request, 'COMPLETED-BUT-NOT-COLLECTED', SampleStatus::READY_FOR_DELIVERY->value);
        $delivery = Delivery::query()->create([
            'request_id' => $request->id,
            'delivered_by' => $this->admin->id,
            'delivery_date' => $completedAt,
            'status' => DeliveryStatus::UNCOLLECTED,
            'handover_cycle' => 1,
        ]);
        CustomerSurvey::query()->create([
            'test_request_id' => $request->id,
            'handover_cycle' => 1,
            'respondent_name' => 'Responden uji',
            'respondent_institution' => 'Instansi uji',
            'respondent_job_category' => 'Polri',
            'request_type' => 'Kimia - Fisika',
            'voluntary_statement' => true,
            'answers' => [],
            'suggestion' => 'Baik.',
            'submitted_at' => $completedAt,
            'submitted_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('delivery.reopen-additional-sample.store', $request), $this->samplePayload() + [
                'confirmation' => '1',
                'supplement_reason' => 'Sampel tambahan belum diambil.',
            ])
            ->assertRedirect(route('testing.show', $request));

        $this->assertSame('in_testing', $request->fresh()->status);
        $this->assertNull($request->fresh()->completed_at);
        $this->assertSame(2, $delivery->fresh()->handover_cycle);
        $this->assertSame(DeliveryStatus::REOPENED, $delivery->fresh()->status);
        $this->assertSame($completedAt->toDateTimeString(), $request->deliveryReopenings()->firstOrFail()->previous_completed_at->toDateTimeString());
        $this->actingAs($this->admin)
            ->get(route('delivery.survey', $request))
            ->assertOk()
            ->assertViewHas('survey', null)
            ->assertViewHas('isReadOnly', false);

        $questions = app(\App\Services\SurveyQuestionService::class)->getQuestions();
        $answers = collect($questions)->mapWithKeys(fn (array $question): array => [$question['key'] => 4])->all();
        $this->actingAs($this->admin)
            ->post(route('delivery.survey', $request), [
                'respondent_name' => 'Responden siklus baru',
                'respondent_institution' => 'Instansi uji',
                'respondent_job_category' => 'Polri',
                'request_type' => 'Kimia - Fisika',
                'voluntary_statement' => '1',
                'answers' => $answers,
                'suggestion' => 'Baik.',
            ])
            ->assertRedirect(route('delivery.show', $request));

        $this->assertDatabaseHas('customer_surveys', [
            'test_request_id' => $request->id,
            'handover_cycle' => 1,
            'respondent_name' => 'Responden uji',
        ]);
        $this->assertDatabaseHas('customer_surveys', [
            'test_request_id' => $request->id,
            'handover_cycle' => 2,
            'respondent_name' => 'Responden siklus baru',
        ]);
        $this->assertSame('Responden siklus baru', $request->fresh()->customerSurvey?->respondent_name);
        $this->assertDatabaseHas('customer_surveys', [
            'test_request_id' => $request->id,
            'handover_cycle' => 1,
            'respondent_name' => 'Responden uji',
        ]);

        $this->actingAs($this->admin)
            ->get(route('delivery.show', $request))
            ->assertRedirect(route('testing.show', $request));
    }

    public function test_collected_request_creates_linked_supplement_without_mutating_original(): void
    {
        $request = $this->makeRequest('completed');
        $originalSample = $this->makeSample($request, 'ORIGINAL', SampleStatus::READY_FOR_DELIVERY->value);
        $delivery = Delivery::query()->create([
            'request_id' => $request->id,
            'delivered_by' => $this->admin->id,
            'delivery_date' => now()->subDay(),
            'status' => DeliveryStatus::COLLECTED,
            'collected_at' => now()->subDay(),
            'handover_cycle' => 1,
        ]);
        $originalDescription = $originalSample->short_description;

        $response = $this->actingAs($this->admin)->post(
            route('requests.supplemental-samples.store', $request),
            $this->samplePayload() + [
                'supplement_reason' => 'Analisis tambahan diminta setelah penyerahan awal.',
                'collection_confirmation' => '1',
            ]
        );

        $supplement = TestRequest::query()->where('parent_test_request_id', $request->id)->firstOrFail();
        $response->assertRedirect(route('review.create', ['request_id' => $supplement->id]));
        $this->assertNotSame($request->receipt_number, $supplement->receipt_number);
        $this->assertSame('received', $supplement->status);
        $this->assertSame(1, $supplement->samples()->count());
        $this->assertSame('completed', $request->fresh()->status);
        $this->assertSame($originalDescription, $originalSample->fresh()->short_description);
        $this->assertSame(DeliveryStatus::COLLECTED, $delivery->fresh()->status);
        $this->assertSame($request->id, $supplement->parent_test_request_id);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'SUPPLEMENTAL_REQUEST_CREATED',
            'subject_id' => $supplement->id,
        ]);
    }

    public function test_sample_addition_does_not_proceed_if_generic_edit_bypasses_lifecycle_route(): void
    {
        $request = $this->makeRequest('completed');
        $existing = $this->makeSample($request, 'COMPLETED-ORIGINAL', SampleStatus::READY_FOR_DELIVERY->value);
        $investigator = Investigator::query()->findOrFail($request->investigator_id);

        $payload = [
            'investigator_rank' => $investigator->rank,
            'investigator_name' => $investigator->name,
            'investigator_nrp' => $investigator->nrp,
            'investigator_jurisdiction' => $investigator->jurisdiction,
            'investigator_phone' => $investigator->phone,
            'case_number' => $request->case_number,
            'suspects' => [['name' => $request->suspect_name]],
            'samples' => [
                [
                    'id' => $existing->id,
                    'short_description' => $existing->short_description,
                    'package_quantity' => $existing->package_quantity,
                    'unit' => $existing->unit,
                ],
                ['short_description' => 'Bypass attempt', 'package_quantity' => 1, 'unit' => 'tablet'],
            ],
        ];

        $response = $this->actingAs($this->admin)->put(route('requests.update', $request), $payload);

        $response->assertSessionHasErrors('samples');
        $this->assertSame(1, $request->samples()->count());
        $this->assertSame('completed', $request->fresh()->status);
    }

    public function test_stale_generic_edit_cannot_delete_a_sample_created_by_delivery_reopening(): void
    {
        $request = $this->makeRequest('ready_for_delivery');
        $existing = $this->makeSample($request, 'REOPEN-ORIGINAL', SampleStatus::READY_FOR_DELIVERY->value);
        Delivery::query()->create([
            'request_id' => $request->id,
            'delivered_by' => $this->admin->id,
            'delivery_date' => now(),
            'status' => DeliveryStatus::READY,
        ]);

        $this->actingAs($this->admin)
            ->post(route('delivery.reopen-additional-sample.store', $request), $this->samplePayload() + [
                'confirmation' => '1',
                'supplement_reason' => 'Sampel tambahan untuk regresi edit stale.',
            ])
            ->assertRedirect(route('testing.show', $request));

        $reopenedSample = $request->fresh()->deliveryReopenings()->firstOrFail()->sample;
        $investigator = Investigator::query()->findOrFail($request->investigator_id);

        $this->actingAs($this->admin)
            ->put(route('requests.update', $request), [
                'investigator_rank' => $investigator->rank,
                'investigator_name' => $investigator->name,
                'investigator_nrp' => $investigator->nrp,
                'investigator_jurisdiction' => $investigator->jurisdiction,
                'investigator_phone' => $investigator->phone,
                'case_number' => $request->case_number,
                'suspects' => [['name' => $request->suspect_name]],
                'samples' => [[
                    'id' => $existing->id,
                    'short_description' => $existing->short_description,
                    'package_quantity' => $existing->package_quantity,
                    'unit' => $existing->unit,
                ]],
            ])
            ->assertSessionHasErrors('samples');

        $this->assertDatabaseHas('samples', ['id' => $reopenedSample->id]);
        $this->assertDatabaseHas('delivery_reopenings', ['sample_id' => $reopenedSample->id]);
        $this->assertSame(2, $request->fresh()->samples()->count());
    }

    public function test_request_edit_can_remove_an_unprocessed_reopened_sample_while_retaining_its_audit_snapshot(): void
    {
        $request = $this->makeRequest('ready_for_delivery');
        $original = $this->makeSample($request, 'REOPEN-KEEP', SampleStatus::READY_FOR_DELIVERY->value);
        $this->createCompletedStages($original);
        Delivery::query()->create([
            'request_id' => $request->id,
            'delivered_by' => $this->admin->id,
            'delivery_date' => now(),
            'status' => DeliveryStatus::READY,
        ]);

        $this->actingAs($this->admin)
            ->post(route('delivery.reopen-additional-sample.store', $request), $this->samplePayload() + [
                'confirmation' => '1',
                'supplement_reason' => 'Sampel tambahan yang ternyata salah input.',
            ])
            ->assertRedirect(route('testing.show', $request));

        $reopening = $request->fresh()->deliveryReopenings()->firstOrFail();
        $reopenedSample = $reopening->sample;
        $evidenceUnit = EvidenceUnit::query()->create([
            'request_id' => $request->id,
            'sample_id' => $reopenedSample->id,
            'receipt_code' => $request->receipt_number,
            'sample_code' => $reopenedSample->sample_code,
            'condition_received' => $reopenedSample->condition,
            'received_by' => $this->admin->id,
        ]);
        $remainingUnit = RemainingUnit::query()->create([
            'evidence_unit_id' => $evidenceUnit->id,
            'sample_code' => $reopenedSample->sample_code,
            'qty_remaining' => $reopenedSample->package_quantity,
            'uom' => $reopenedSample->unit,
            'seal_status_delivered' => 'disegel',
            'delivered_at' => now(),
            'delivered_by' => $this->admin->id,
        ]);
        $investigator = Investigator::query()->findOrFail($request->investigator_id);
        $this->actingAs($this->admin)
            ->get(route('requests.edit', $request))
            ->assertOk()
            ->assertSee('Hapus sampel salah input')
            ->assertSee('reopened_sample_removal_reason');
        $editPayload = [
            'investigator_rank' => $investigator->rank,
            'investigator_name' => $investigator->name,
            'investigator_nrp' => $investigator->nrp,
            'investigator_jurisdiction' => $investigator->jurisdiction,
            'investigator_phone' => $investigator->phone,
            'case_number' => $request->case_number,
            'suspects' => [['name' => $request->suspect_name]],
            'samples' => [
                [
                    'id' => $original->id,
                    'short_description' => $original->short_description,
                    'package_quantity' => $original->package_quantity,
                    'unit' => $original->unit,
                ],
                [
                    'id' => $reopenedSample->id,
                    'short_description' => $reopenedSample->short_description,
                    'package_quantity' => $reopenedSample->package_quantity,
                    'unit' => $reopenedSample->unit,
                ],
            ],
            'remove_reopened_sample_ids' => [$reopenedSample->id],
        ];

        $this->actingAs($this->admin)
            ->put(route('requests.update', $request), $editPayload)
            ->assertSessionHasErrors('reopened_sample_removal_reason');

        $this->assertDatabaseHas('samples', ['id' => $reopenedSample->id]);

        $this->actingAs($this->admin)
            ->put(route('requests.update', $request), $editPayload + [
                'reopened_sample_removal_reason' => 'Sampel tambahan dicatat karena salah input dan tidak boleh diproses.',
            ])
            ->assertRedirect(route('requests.show', $request));

        $this->assertDatabaseMissing('samples', ['id' => $reopenedSample->id]);
        $this->assertDatabaseMissing('evidence_units', ['id' => $evidenceUnit->id]);
        $this->assertDatabaseMissing('remaining_units', ['id' => $remainingUnit->id]);
        $this->assertSame(1, $request->fresh()->samples()->count());
        $this->assertDatabaseHas('delivery_reopenings', [
            'id' => $reopening->id,
            'sample_id' => null,
        ]);
        $snapshot = $reopening->fresh()->sample_snapshot;
        $this->assertSame($reopenedSample->sample_code, $snapshot['sample_code']);
        $this->assertSame('Sampel tambahan dicatat karena salah input dan tidak boleh diproses.', $snapshot['removal_reason']);
        $this->assertSame($this->admin->id, $snapshot['removed_by']);
        $this->assertSame($evidenceUnit->id, $snapshot['removed_evidence_units'][0]['id']);
        $this->assertSame($remainingUnit->id, $snapshot['removed_remaining_units'][0]['id']);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'REOPENED_SAMPLE_REMOVED_FROM_ACTIVE_RECORDS',
            'subject_id' => $request->id,
        ]);
        $this->actingAs($this->admin)
            ->get(route('testing.show', $request))
            ->assertOk()
            ->assertSee($reopenedSample->sample_code)
            ->assertSee('Snapshot audit tetap tersimpan.');
    }

    public function test_reopened_sample_removal_rejects_printed_labels_and_physical_handover_records(): void
    {
        $request = $this->makeRequest('ready_for_delivery');
        $this->makeSample($request, 'REOPEN-PRINT-KEEP', SampleStatus::READY_FOR_DELIVERY->value);

        $this->actingAs($this->admin)
            ->post(route('delivery.reopen-additional-sample.store', $request), $this->samplePayload() + [
                'confirmation' => '1',
                'supplement_reason' => 'Sampel tambahan yang ternyata salah input.',
            ])
            ->assertRedirect(route('testing.show', $request));

        $reopening = $request->fresh()->deliveryReopenings()->firstOrFail();
        $reopenedSample = $reopening->sample;
        $evidenceUnit = EvidenceUnit::query()->create([
            'request_id' => $request->id,
            'sample_id' => $reopenedSample->id,
            'sample_code' => $reopenedSample->sample_code,
        ]);
        $remainingUnit = RemainingUnit::query()->create([
            'evidence_unit_id' => $evidenceUnit->id,
            'sample_code' => $reopenedSample->sample_code,
            'qty_remaining' => 1,
        ]);
        $remainingUnit->printLogs()->create([
            'label_type' => 'remaining',
            'printed_by' => $this->admin->id,
            'print_reason' => 'first_print',
            'print_format' => 'a4',
            'print_count' => 1,
        ]);

        try {
            app(ReopenedSampleRemovalService::class)->remove(
                $request,
                $reopenedSample,
                $this->admin,
                'Sampel salah input.'
            );
            $this->fail('Penghapusan seharusnya ditolak setelah label dicetak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('remove_reopened_sample_ids', $exception->errors());
        }

        $remainingUnit->printLogs()->delete();
        $remainingUnit->update(['handover_doc_no' => 'BA-SERAH-TERIMA']);

        try {
            app(ReopenedSampleRemovalService::class)->remove(
                $request,
                $reopenedSample,
                $this->admin,
                'Sampel salah input.'
            );
            $this->fail('Penghapusan seharusnya ditolak setelah serah terima fisik dicatat.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('remove_reopened_sample_ids', $exception->errors());
        }

        $this->assertDatabaseHas('samples', ['id' => $reopenedSample->id]);
        $this->assertDatabaseHas('evidence_units', ['id' => $evidenceUnit->id]);
        $this->assertDatabaseHas('remaining_units', ['id' => $remainingUnit->id]);
        $this->assertDatabaseHas('delivery_reopenings', [
            'id' => $reopening->id,
            'sample_id' => $reopenedSample->id,
        ]);
    }

    public function test_request_edit_cannot_remove_a_reopened_sample_after_testing_has_started(): void
    {
        $request = $this->makeRequest('ready_for_delivery');
        $original = $this->makeSample($request, 'REOPEN-PROCESSED-KEEP', SampleStatus::READY_FOR_DELIVERY->value);
        $this->createCompletedStages($original);
        Delivery::query()->create([
            'request_id' => $request->id,
            'delivered_by' => $this->admin->id,
            'delivery_date' => now(),
            'status' => DeliveryStatus::READY,
        ]);

        $this->actingAs($this->admin)
            ->post(route('delivery.reopen-additional-sample.store', $request), $this->samplePayload() + [
                'confirmation' => '1',
                'supplement_reason' => 'Pengujian sampel tambahan.',
            ])
            ->assertRedirect(route('testing.show', $request));

        $reopenedSample = $request->fresh()->deliveryReopenings()->firstOrFail()->sample;
        $this->createCompletedStages($reopenedSample);
        $investigator = Investigator::query()->findOrFail($request->investigator_id);

        $this->actingAs($this->admin)
            ->put(route('requests.update', $request), [
                'investigator_rank' => $investigator->rank,
                'investigator_name' => $investigator->name,
                'investigator_nrp' => $investigator->nrp,
                'investigator_jurisdiction' => $investigator->jurisdiction,
                'investigator_phone' => $investigator->phone,
                'case_number' => $request->case_number,
                'suspects' => [['name' => $request->suspect_name]],
                'samples' => [[
                    'id' => $original->id,
                    'short_description' => $original->short_description,
                    'package_quantity' => $original->package_quantity,
                    'unit' => $original->unit,
                ], [
                    'id' => $reopenedSample->id,
                    'short_description' => $reopenedSample->short_description,
                    'package_quantity' => $reopenedSample->package_quantity,
                    'unit' => $reopenedSample->unit,
                ]],
                'remove_reopened_sample_ids' => [$reopenedSample->id],
                'reopened_sample_removal_reason' => 'Ditolak karena sampel sudah diproses.',
            ])
            ->assertSessionHasErrors('samples');

        $this->assertDatabaseHas('samples', ['id' => $reopenedSample->id]);
        $this->assertDatabaseHas('delivery_reopenings', ['sample_id' => $reopenedSample->id]);
    }

    public function test_supplement_creation_requires_collection_confirmation(): void
    {
        $request = $this->makeRequest('completed');
        Delivery::query()->create([
            'request_id' => $request->id,
            'delivered_by' => $this->admin->id,
            'delivery_date' => now(),
            'status' => DeliveryStatus::COLLECTED,
            'collected_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('requests.supplemental-samples.store', $request), $this->samplePayload() + [
            'supplement_reason' => 'Uji tambahan.',
        ]);

        $response->assertSessionHasErrors('collection_confirmation');
        $this->assertSame(0, $request->supplementalRequests()->count());
    }

    public function test_completed_legacy_request_can_create_a_supplement_after_explicit_collection_confirmation(): void
    {
        $request = $this->makeRequest('completed');
        $request->forceFill(['completed_at' => now()->subDay()])->save();

        $this->actingAs($this->admin)->post(
            route('requests.supplemental-samples.store', $request),
            $this->samplePayload() + [
                'supplement_reason' => 'Sampel lanjutan setelah hasil diambil.',
                'collection_confirmation' => '1',
            ]
        )->assertRedirect();

        $delivery = $request->delivery()->firstOrFail();
        $supplement = $request->supplementalRequests()->firstOrFail();

        $this->assertSame(DeliveryStatus::COLLECTED, $delivery->status);
        $this->assertNotNull($delivery->collected_at);
        $this->assertSame('completed', $request->fresh()->status);
        $this->assertSame($request->id, $supplement->parent_test_request_id);
    }

    public function test_completed_delivery_requires_explicit_collection_confirmation(): void
    {
        $request = $this->makeRequest('ready_for_delivery');
        $sample = $this->makeSample($request, 'READY', SampleStatus::READY_FOR_DELIVERY->value);
        Delivery::query()->create([
            'request_id' => $request->id,
            'delivered_by' => $this->admin->id,
            'delivery_date' => now(),
            'status' => DeliveryStatus::READY,
        ]);
        CustomerSurvey::query()->create([
            'test_request_id' => $request->id,
            'handover_cycle' => 1,
            'respondent_name' => 'Responden uji',
            'respondent_institution' => 'Instansi uji',
            'respondent_job_category' => 'Polri',
            'request_type' => 'Kimia - Fisika',
            'voluntary_statement' => true,
            'answers' => [],
            'suggestion' => 'Baik.',
            'submitted_at' => now(),
            'submitted_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('delivery.complete', $request));

        $response->assertSessionHasErrors('collection_confirmation');
        $this->assertSame('ready_for_delivery', $request->fresh()->status);
        $this->assertSame(DeliveryStatus::READY, $request->delivery()->firstOrFail()->status);
        $this->assertNotNull($sample->fresh());
    }

    public function test_ui_offers_audited_reopen_before_collection_and_linked_supplement_after_collection(): void
    {
        $notCollected = $this->makeRequest('ready_for_delivery');
        Delivery::query()->create([
            'request_id' => $notCollected->id,
            'delivered_by' => $this->admin->id,
            'delivery_date' => now(),
            'status' => DeliveryStatus::READY,
        ]);

        $this->actingAs($this->admin)
            ->get(route('delivery.reopen-additional-sample.create', $notCollected))
            ->assertOk()
            ->assertSee('hasil permintaan ini belum diambil')
            ->assertSee('Alasan penambahan');

        $collected = $this->makeRequest('completed');
        Delivery::query()->create([
            'request_id' => $collected->id,
            'delivered_by' => $this->admin->id,
            'delivery_date' => now()->subDay(),
            'status' => DeliveryStatus::COLLECTED,
            'collected_at' => now()->subDay(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('requests.supplemental-samples.create', $collected))
            ->assertOk()
            ->assertSee('Saya memastikan hasil dari permintaan awal sudah diambil')
            ->assertSee('Buat Suplemen Tertaut');
    }

    public function test_delivery_detail_exposes_only_the_lifecycle_action_for_its_collection_state(): void
    {
        $notCollected = $this->makeRequest('ready_for_delivery');
        Delivery::query()->create([
            'request_id' => $notCollected->id,
            'delivered_by' => $this->admin->id,
            'delivery_date' => now(),
            'status' => DeliveryStatus::READY,
        ]);
        Sample::factory()->create([
            'test_request_id' => $notCollected->id,
            'status' => SampleStatus::READY_FOR_DELIVERY->value,
            'sample_status' => 'ready_for_delivery',
        ]);

        $this->actingAs($this->admin)
            ->get(route('delivery.show', $notCollected))
            ->assertOk()
            ->assertSee('Buka Kembali untuk Sampel Tambahan')
            ->assertDontSee('Buat Suplemen Tertaut');

        $collected = $this->makeRequest('completed');
        Delivery::query()->create([
            'request_id' => $collected->id,
            'delivered_by' => $this->admin->id,
            'delivery_date' => now()->subDay(),
            'status' => DeliveryStatus::COLLECTED,
            'collected_at' => now()->subDay(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('delivery.show', $collected))
            ->assertOk()
            ->assertSee('Buat Suplemen Tertaut')
            ->assertDontSee('Buka Kembali untuk Sampel Tambahan');
    }

    private function makeRequest(string $status): TestRequest
    {
        return TestRequest::factory()->create([
            'status' => $status,
            'investigator_id' => Investigator::factory()->create()->id,
            'user_id' => $this->admin->id,
        ]);
    }

    private function makeSample(TestRequest $request, string $description, string $status): Sample
    {
        return Sample::factory()->create([
            'test_request_id' => $request->id,
            'short_description' => $description,
            'status' => $status,
            'sample_status' => $status === SampleStatus::READY_FOR_DELIVERY->value ? 'ready_for_delivery' : 'received',
            'requested_test_methods' => json_encode(['uv_vis']),
            'test_methods' => json_encode(['uv_vis']),
        ]);
    }

    private function createCompletedStages(Sample $sample): void
    {
        foreach (['preparation', 'instrumentation', 'interpretation'] as $stage) {
            SampleTestProcess::factory()->create([
                'sample_id' => $sample->id,
                'stage' => $stage,
                'started_at' => now()->subDays(2),
                'completed_at' => now()->subDay(),
            ]);
        }
    }

    private function completeAllStages(Sample $sample): void
    {
        $sample->testProcesses()->update([
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function samplePayload(): array
    {
        return [
            'short_description' => 'Sampel tambahan',
            'sample_form' => 'pill',
            'sample_category' => 'obat_keras',
            'package_quantity' => 2,
            'unit' => 'tablet',
            'condition' => 'baik',
        ];
    }

    private function postAdditionalReview(TestRequest $request, Sample $sample)
    {
        return $this->actingAs($this->admin)->post(
            route('testing.additional-samples.store', [$request, $sample]),
            $this->reviewPayload($request, $sample)
        );
    }

    /** @return array<string, mixed> */
    private function reviewPayload(TestRequest $request, Sample $sample): array
    {
        $analyst = User::factory()->create(['role' => 'analis', 'is_active' => true]);

        return [
            'request_id' => $request->id,
            'test_date' => now()->format('Y-m-d'),
            'samples' => [[
                'id' => $sample->id,
                'assigned_analyst_id' => $analyst->id,
                'test_methods' => ['uv_vis'],
                'active_substance' => 'Parasetamol',
                'physical_identification' => 'Tablet sampel tambahan.',
                'quantity' => 1,
                'batch_number' => 'SUP-001',
                'test_type' => 'kualitatif',
            ]],
        ];
    }
}
