<?php

namespace App\Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentNotifyLog extends Model
{
    protected $fillable = [
        'provider',
        'event_type',
        'out_trade_no',
        'provider_trade_no',
        'notify_id',
        'trade_status',
        'verify_passed',
        'handle_status',
        'handle_msg',
        'payload',
        'received_at',
        'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'verify_passed' => 'bool',
            'payload' => 'array',
            'received_at' => 'datetime',
            'handled_at' => 'datetime',
        ];
    }
}
