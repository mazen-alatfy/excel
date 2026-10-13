<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(array $data, ?string $message = null, int $status = 200): JsonResponse
    {
        if ($message !== null) {
            $data = ['message' => $message, ...$data];
        }

        return response()->json($data, $status);
    }
}
