<?php

namespace App\Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class RechargeOrder extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'order_no',
        'channel',
        'amount',
        'bonus_amount',
        'credit_amount',
        'status',
        'payer_name',
        'payer_account',
        'voucher_image_url',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'paid_at',
        'expire_at',
        'remark',
        'review_remark',
        'rebate_event_id',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
            'expire_at' => 'datetime',
            'user_id' => 'int',
            'reviewed_by' => 'int',
            'rebate_event_id' => 'int',
        ];
    }
}