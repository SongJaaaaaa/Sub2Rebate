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

    protected $fillable = [
        'user_id',
        'withdraw_account_id',
        'amount',
        'status',
        'account_type',
        'account_no',
        'real_name',
        'remark',
        'reject_reason',
        'reviewed_by',
        'reviewed_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
