<?php

namespace Tests\Feature\Delivery;

use App\Models\CustomerSurvey;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\SurveyQuestionService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DeliverySurveyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_survey_form_renders_accessible_dynamic_rating_controls(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready_for_delivery',
        ]);

        $response = $this->actingAs($user)->get(route('delivery.survey', $request));

        $response->assertOk();
        $response->assertSee('Survei Kepuasan Pengguna');
        $response->assertSee('Permintaan '.$request->request_number);
        $response->assertSee('<fieldset id="answers-persyaratan"', false);
        $response->assertSee('name="answers[persyaratan]"', false);
        $response->assertSee('min-h-11', false);
        $response->assertSee('dark:bg-slate-800', false);
        $response->assertSee('dark:text-slate-100', false);
        $response->assertSee('focus-visible:ring-2', false);
        $response->assertSee('Kembali ke detail penyerahan');
        $response->assertDontSee('📝');
        $xpath = $this->surveyXPath($response);
        $this->assertSame(1, $xpath->query('//main//textarea')->length);
        $this->assertSame('suggestion-help', $xpath->query('//textarea[@name="suggestion"]')->item(0)->getAttribute('aria-describedby'));
        $response->assertSee('Saran atau keluhan tentang layanan');
        $response->assertDontSee('name="complaint"', false);
        $response->assertDontSee('name="follow_up"', false);
    }

    public function test_completed_request_renders_saved_survey_as_read_only_summary(): void
    {
        $user = User::factory()->create();
        $request = TestRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
        ]);
        CustomerSurvey::query()->create([
            'test_request_id' => $request->id,
            'respondent_name' => 'Responden Uji',
            'respondent_institution' => 'Instansi Uji',
            'respondent_job_category' => 'ASN',
            'request_type' => 'Kimia - Fisika',
            'voluntary_statement' => true,
            'answers' => ['persyaratan' => 3],
            'suggestion' => 'Pertahankan mutu layanan.',
            'score_avg' => 3,
        ]);

        $response = $this->actingAs($user)->get(route('delivery.survey', $request));

        $response->assertOk();
        $response->assertSee('Jawaban tidak dapat diubah.');
        $response->assertSee('Mudah (3)');
        $response->assertSee('Pertahankan mutu layanan.');
        $response->assertDontSee('name="answers[persyaratan]"', false);
        $response->assertDontSee('Kirim survei');
    }

    public function test_empty_question_set_keeps_submission_disabled_before_and_after_alpine_initialization(): void
    {
        $request = $this->surveyRequest();
        $this->mock(SurveyQuestionService::class)->shouldReceive('getQuestions')->andReturn([]);

        $response = $this->get(route('delivery.survey', $request));

        $response->assertOk()->assertSee('belum ada pertanyaan aktif');
        $xpath = $this->surveyXPath($response);
        $form = $xpath->query('//form[@action="'.route('delivery.survey.submit', $request).'"]')->item(0);
        $button = $xpath->query('.//button[@type="submit"]', $form)->item(0);
        $this->assertStringContainsString('unavailable: true', $form->getAttribute('x-data'));
        $this->assertTrue($button->hasAttribute('disabled'));
        $this->assertSame('submitting || unavailable', $button->getAttribute('x-bind:disabled'));
        $this->assertSame(0, $xpath->query('//input[starts-with(@name, "answers[")]')->length);
    }

    public function test_custom_scales_and_old_input_survive_validation_errors(): void
    {
        $request = $this->surveyRequest();
        $this->mock(SurveyQuestionService::class)->shouldReceive('getQuestions')->andReturn($this->surveyQuestions());
        $payload = $this->surveyPayload();
        $payload['respondent_name'] = 'Nama setelah koreksi';
        $payload['respondent_institution'] = '';

        $this->from(route('delivery.survey', $request))
            ->post(route('delivery.survey.submit', $request), $payload)
            ->assertSessionHasErrors('respondent_institution');
        $response = $this->get(route('delivery.survey', $request));

        $response->assertOk();
        $xpath = $this->surveyXPath($response);
        $this->assertSame('Nama setelah koreksi', $xpath->query('//input[@name="respondent_name"]')->item(0)->getAttribute('value'));
        $this->assertSame(5, $xpath->query('//input[@name="answers[ketelitian]"]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="answers[ketelitian]" and @value="5" and @checked]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="respondent_job_category" and @value="ASN" and @checked]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="request_type" and @value="Mikrobiologi" and @checked]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="voluntary_statement" and @checked]')->length);
        $this->assertSame($payload['suggestion'], $xpath->query('//textarea[@name="suggestion"]')->item(0)->textContent);
    }

    public function test_identity_is_prefilled_and_controls_match_the_server_length_limit(): void
    {
        $request = $this->surveyRequest();
        $response = $this->get(route('delivery.survey', $request));

        $response->assertOk()->assertSee('name="_token"', false);
        $xpath = $this->surveyXPath($response);
        $name = $xpath->query('//input[@name="respondent_name"]')->item(0);
        $institution = $xpath->query('//input[@name="respondent_institution"]')->item(0);
        $this->assertSame($request->investigator->name, $name->getAttribute('value'));
        $this->assertSame($request->investigator->jurisdiction, $institution->getAttribute('value'));
        $this->assertSame('255', $name->getAttribute('maxlength'));
        $this->assertSame('255', $institution->getAttribute('maxlength'));
    }

    public function test_invalid_submission_renders_field_errors_and_preserves_answers_without_saving(): void
    {
        $request = $this->surveyRequest();
        $this->mock(SurveyQuestionService::class)->shouldReceive('getQuestions')->andReturn($this->surveyQuestions());
        $payload = $this->surveyPayload();
        $payload['answers']['ketelitian'] = 6;
        $payload['voluntary_statement'] = false;

        $this->from(route('delivery.survey', $request))
            ->post(route('delivery.survey.submit', $request), $payload)
            ->assertRedirect(route('delivery.survey', $request))
            ->assertSessionHasErrors(['answers.ketelitian', 'voluntary_statement']);

        $response = $this->get(route('delivery.survey', $request));
        $response->assertOk()->assertSee('Periksa kembali isian survei');
        $xpath = $this->surveyXPath($response);
        $this->assertSame(4, (int) $xpath->query('//input[@name="answers[persyaratan]" and @checked]')->item(0)->getAttribute('value'));
        $invalid = $xpath->query('//input[@name="answers[ketelitian]" and @value="1"]')->item(0);
        $this->assertSame('true', $invalid->getAttribute('aria-invalid'));
        $this->assertSame('answers-ketelitian-error', $invalid->getAttribute('aria-describedby'));
        $this->assertSame(1, $xpath->query('//*[@id="answers-ketelitian-error"]')->length);
        $this->assertDatabaseMissing('customer_surveys', ['test_request_id' => $request->id]);
    }

    public function test_valid_custom_scale_submission_preserves_persistence_and_raw_average_scoring(): void
    {
        $request = $this->surveyRequest();
        $this->mock(SurveyQuestionService::class)->shouldReceive('getQuestions')->andReturn($this->surveyQuestions());

        $this->post(route('delivery.survey.submit', $request), $this->surveyPayload())
            ->assertRedirect(route('delivery.show', $request))
            ->assertSessionHas('success');

        $survey = $request->customerSurvey()->firstOrFail();
        $this->assertSame(['persyaratan' => 4, 'ketelitian' => 5], $survey->answers);
        $this->assertSame(4.5, (float) $survey->score_avg);
        $this->assertTrue($survey->voluntary_statement);
        $this->assertSame($request->user_id, $survey->submitted_by);
        $this->assertNotNull($survey->submitted_at);
    }

    public function test_completed_request_rejects_post_and_does_not_change_saved_survey(): void
    {
        $request = $this->surveyRequest('completed');
        $survey = $request->customerSurvey()->create($this->surveyPayload() + ['score_avg' => 4.5]);
        $payload = $this->surveyPayload();
        $payload['suggestion'] = 'Perubahan yang harus ditolak.';

        $this->from(route('delivery.survey', $request))
            ->post(route('delivery.survey.submit', $request), $payload)
            ->assertRedirect(route('delivery.survey', $request))
            ->assertSessionHas('error', 'Penyerahan sudah selesai. Survei tidak dapat diubah.');

        $this->assertSame($this->surveyPayload()['suggestion'], $survey->fresh()->suggestion);
        $this->assertSame(1, $request->customerSurvey()->count());
    }

    public function test_single_feedback_submission_preserves_legacy_feedback_and_displays_it_read_only(): void
    {
        $request = $this->surveyRequest();
        $this->mock(SurveyQuestionService::class)->shouldReceive('getQuestions')->andReturn($this->surveyQuestions());
        $survey = $request->customerSurvey()->create($this->surveyPayload() + [
            'complaint' => 'Keluhan lama tetap tersimpan.',
            'follow_up' => 'Harapan penyelesaian lama tetap tersimpan.',
            'score_avg' => 4.5,
        ]);
        $payload = $this->surveyPayload();
        $payload['suggestion'] = 'Masukan terpadu yang baru.';

        $this->post(route('delivery.survey.submit', $request), $payload)
            ->assertRedirect(route('delivery.show', $request));

        $survey->refresh();
        $this->assertSame($payload['suggestion'], $survey->suggestion);
        $this->assertSame('Keluhan lama tetap tersimpan.', $survey->complaint);
        $this->assertSame('Harapan penyelesaian lama tetap tersimpan.', $survey->follow_up);

        $request->update(['status' => 'completed']);
        $this->get(route('delivery.survey', $request))->assertOk()
            ->assertSee('Keluhan lama tetap tersimpan.')
            ->assertSee('Harapan penyelesaian lama tetap tersimpan.')
            ->assertSee('Masukan terpadu yang baru.');
    }

    public function test_completed_request_without_survey_has_an_empty_read_only_state(): void
    {
        $request = $this->surveyRequest('completed');

        $response = $this->get(route('delivery.survey', $request));

        $response->assertOk()->assertSee('Survei belum tersedia untuk permintaan ini.');
        $response->assertDontSee('Kirim survei');
        $response->assertDontSee('name="respondent_name"', false);
    }

    public function test_survey_routes_still_require_authentication(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        $request = TestRequest::factory()->create(['status' => 'ready_for_delivery']);

        $this->get(route('delivery.survey', $request))->assertRedirect(route('login'));
        $this->postJson(route('delivery.survey.submit', $request), $this->surveyPayload())->assertUnauthorized();
        $this->assertDatabaseMissing('customer_surveys', ['test_request_id' => $request->id]);
    }

    private function surveyRequest(string $status = 'ready_for_delivery'): TestRequest
    {
        Queue::fake();
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $this->actingAs($user);

        return TestRequest::factory()->create(['user_id' => $user->id, 'status' => $status]);
    }

    private function surveyQuestions(): array
    {
        return [
            SurveyQuestionService::DEFAULT_QUESTIONS['persyaratan'],
            ['key' => 'ketelitian', 'label' => 'Ketelitian hasil uji', 'scale' => ['Sangat kurang', 'Kurang', 'Cukup', 'Baik', 'Sangat baik']],
        ];
    }

    private function surveyPayload(): array
    {
        return [
            'respondent_name' => 'Responden Uji',
            'respondent_institution' => 'Instansi Uji',
            'respondent_job_category' => 'ASN',
            'request_type' => 'Mikrobiologi',
            'voluntary_statement' => 1,
            'answers' => ['persyaratan' => 4, 'ketelitian' => 5],
            'suggestion' => 'Pertahankan mutu layanan.',
        ];
    }

    private function surveyXPath(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }
}
