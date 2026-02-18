<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\AiCommsService;
use App\Services\WhatsApp\TemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class AiController extends Controller
{
    public function compose(Request $request, AiCommsService $aiService, TemplateService $templateService)
    {
        $request->validate([
            'prompt' => 'required|string|max:2000',
            'current_text' => 'nullable|string|max:10000',
            'variables' => 'nullable|array|max:100',
            'variables.*' => 'string|max:128',
        ]);

        $allowedVariableKeys = collect($templateService->getMagicVariables())
            ->flatten()
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn ($v) => trim($v))
            ->unique()
            ->values()
            ->all();

        $allowedSet = array_fill_keys($allowedVariableKeys, true);

        $requested = $request->input('variables', []);
        if ($requested === null) {
            $requested = [];
        }

        $variables = [];
        foreach ($requested as $key) {
            if (! is_string($key)) {
                continue;
            }

            $key = trim($key);
            $key = trim($key, '{}');
            $key = trim($key);

            if ($key === '' || ! isset($allowedSet[$key])) {
                continue;
            }

            $variables[] = $key;

            if (count($variables) >= 100) {
                break;
            }
        }

        $variables = array_values(array_unique($variables));

        try {
            $result = $aiService->generateMessage(
                $request->input('prompt'),
                $request->input('current_text'),
                'general',
                $variables
            );

            return response()->json(['result' => $result]);
        } catch (RuntimeException $e) {
            $requestId = (string) Str::uuid();
            Log::warning('AI Compose Runtime Error', [
                'request_id' => $requestId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Layanan AI sedang tidak tersedia. Silakan coba lagi.',
                'code' => 'AI_SERVICE_UNAVAILABLE',
                'request_id' => $requestId,
            ], 503);
        } catch (\Throwable $e) {
            $requestId = (string) Str::uuid();
            Log::error('AI Compose Error', [
                'request_id' => $requestId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Terjadi gangguan internal. Silakan coba lagi.',
                'code' => 'AI_INTERNAL_ERROR',
                'request_id' => $requestId,
            ], 500);
        }
    }
}
