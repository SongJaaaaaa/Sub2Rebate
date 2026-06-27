<?php

namespace App\Modules\Withdraw\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawRecord extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELED = 'canceled';
    public const TYPE_ALIPAY = 'alipay';
    public const TYPE_API_QUOTA = 'api_quota';

    protected $fillable = [
        'user_id',
        'withdraw_account_id',
        'type',
        'amount',
        'status',
        'account_type',
        'account_no',
        'real_name',
        'sub2api_balance_before',
        'sub2api_balance_after',
        'remark',
        'reject_reason',
        'payout_trade_no',
        'payout_error',
        'payout_time',
        'reviewed_by',
        'reviewed_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'payout_time' => 'datetime',
            'reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
            'sub2api_balance_before' => 'decimal:6',
            'sub2api_balance_after' => 'decimal:6',
        ];
    }
}
