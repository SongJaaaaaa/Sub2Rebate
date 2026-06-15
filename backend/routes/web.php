<?php

use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ApiResponse::ok([
        'service' => config('app.name', 'Sub2Rebate'),
    ]);
});
