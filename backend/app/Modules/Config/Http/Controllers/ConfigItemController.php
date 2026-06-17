<?php

namespace App\Modules\Config\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Config\Services\ConfigService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ConfigItemController extends Controller
{
    public function __construct(private readonly ConfigService $configs)
    {
    }

    public function index(): JsonResponse
    {
        return ApiResponse::ok([
            'items' => $this->configs->all(),
            'values' => $this->configs->values(),
        ]);
    }
}
