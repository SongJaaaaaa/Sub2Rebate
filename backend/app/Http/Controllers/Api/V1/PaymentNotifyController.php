<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Payment\Services\RechargeCallbackService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentNotifyController extends Controller
{
    public function __construct(private readonly RechargeCallbackService $callbacks)
    {
    }

    public function epay(Request $request): Response
    {
        $result = $this->callbacks->handleEpay($request->all());

        return response((string) ($result['response'] ?? 'fail'), (int) ($result['status'] ?? 400))
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
