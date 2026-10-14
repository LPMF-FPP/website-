<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\AiCommsService;
use App\Services\AI\AiFailure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiController extends Controller
{
    public function compose(Request $request, AiCommsService $aiService)
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:2000',
            'current_text' => 'nullable|string|max:10000',
            'share_draft' => 'sometimes|boolean',
            'context_type' => 'sometimes|string|in:general,broadcast,task,countdown,iso_countdown,reminder',
            'expected_revision' => 'sometimes|string|max:64',
            'variables' => 'nullable|array|max:100',
            'variables.*' => 'string|max:128',
        ]);
        try {
            if (isset($validated['expected_revision']) && $validated['expected_revision'] !== app(\App\Services\AI\AiConfiguration::class)->values()['revision']) {
                throw new AiFailure('AI_CONFIGURATION_CHANGED', 'Konfigurasi AI berubah. Muat ulang editor untuk memeriksa tujuan terbaru.', 409);
            }
            $arguments = [
                $validated['prompt'],
                ($validated['share_draft'] ?? false) ? ($validated['current_text'] ?? null) : null,
                $validated['context_type'] ?? 'general',
                [],
            ];
            if (isset($validated['expected_revision'])) {
                $arguments[] = $validated['expected_revision'];
            }
            $result = $aiService->generateMessage(...$arguments);

            return response()->json(['result' => $result]);
        } catch (\Throwable $error) {
            $failure = $error instanceof AiFailure ? $error : new AiFailure('AI_SERVICE_UNAVAILABLE', 'Layanan AI sedang tidak tersedia. Silakan coba lagi.');
            $context = ['request_id' => $failure->requestId, 'code' => $failure->errorCode];
            $diagnostic = $failure->getPrevious() ?? ($error instanceof AiFailure ? null : $error);
            if ($diagnostic !== null) {
                $context['exception'] = $diagnostic::class;
            }
            Log::warning('AI compose failed', $context);

            return $failure->response();
        }
    }
}
