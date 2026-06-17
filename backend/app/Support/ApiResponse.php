<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use stdClass;

class ApiResponse
{
    public static function ok(mixed $data = null, string $msg = 'ok'): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'message' => $msg,
            'data' => func_num_args() === 0 ? new stdClass() : $data,
        ]);
    }

    public static function fail(int $code, string $msg, mixed $data = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $msg,
            'data' => $data,
        ], $status);
    }
}
