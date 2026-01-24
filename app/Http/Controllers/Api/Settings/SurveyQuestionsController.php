<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Services\SurveyQuestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SurveyQuestionsController extends Controller
{
    public function __construct(
        private readonly SurveyQuestionService $service
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'questions' => $this->service->getAllQuestions(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'questions' => 'required|array',
            'questions.*.key' => 'required|string|max:50',
            'questions.*.label' => 'required|string|max:255',
            'questions.*.scale' => 'required|array|min:2|max:10',
            'questions.*.scale.*' => 'required|string|max:100',
            'questions.*.enabled' => 'boolean',
        ]);

        $this->service->updateQuestions(
            $validated['questions'],
            auth()->id()
        );

        return response()->json([
            'message' => 'Pertanyaan survey berhasil disimpan',
            'questions' => $this->service->getAllQuestions(),
        ]);
    }
}
