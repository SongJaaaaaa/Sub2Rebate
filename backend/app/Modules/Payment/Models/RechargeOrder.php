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
    public const STATUS_PAID = 'paid'; // Epay 在线支付自动到账终态（区别于人工 approved）

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
        'pay_method',
        'epay_trade_no',
        'epay_paid_amount',
        'sub2_balance_before',
        'sub2_balance_after',
        'notify_raw',
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
            'epay_paid_amount' => 'decimal:6',
            'sub2_balance_before' => 'decimal:6',
            'sub2_balance_after' => 'decimal:6',
            'notify_raw' => 'array',
        ];
    }
}