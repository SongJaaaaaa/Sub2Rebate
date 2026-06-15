<?php

namespace App\Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class RebateEvent extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'payment_record_id',
        'source_type',
        'source_id',
        'event_type',
        'status',
        'source_amount',
        'source_currency',
        'standard_amount',
        'standard_currency',
        'credit_amount',
        'config_snapshot',
        'operator_user_id',
        'remark',
        'occurred_at',
        'processed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'config_snapshot' => 'array',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
