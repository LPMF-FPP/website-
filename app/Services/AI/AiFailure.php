<?php

namespace App\Services\AI;

use Illuminate\Support\Str;
use RuntimeException;

class AiFailure extends RuntimeException
{
    public readonly string $requestId;

    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 503, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->requestId = (string) Str::uuid();
    }

    public function response(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $this->getMessage(),
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'request_id' => $this->requestId,
        ], $this->status);
    }
}
