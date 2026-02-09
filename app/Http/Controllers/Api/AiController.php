<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\AiCommsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiController extends Controller
{
    public function compose(Request $request, AiCommsService $aiService)
    {
        $request->validate([
            'prompt' => 'required|string',
            'current_text' => 'nullable|string',
        ]);

        try {
            $result = $aiService->generateMessage(
                $request->input('prompt'),
                $request->input('current_text')
            );

            return response()->json(['result' => $result]);
        } catch (\Exception $e) {
            Log::error('AI Compose Error: '.$e->getMessage());

            return response()->json(['error' => 'Failed to generate message.'], 500);
        }
    }
}
