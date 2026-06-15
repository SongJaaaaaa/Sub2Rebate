<?php

namespace App\Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentRecord extends Model
{
    protected $fillable = [
        'user_id',
        'source_type',
        'source_id',
        'status',
        'source_amount',
        'source_currency',
        'standard_amount',
        'standard_currency',
        'credit_amount',
        'config_snapshot',
        'operator_user_id',
        'remark',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'config_snapshot' => 'array',
            'paid_at' => 'datetime',
        ];
    }
}
