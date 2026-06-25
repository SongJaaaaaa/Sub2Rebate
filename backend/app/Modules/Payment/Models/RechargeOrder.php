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
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    public const CREDIT_PENDING = 'pending';
    public const CREDIT_SUCCESS = 'success';
    public const CREDIT_FAILED = 'failed';

    public const CHANNEL_ALIPAY = 'alipay';
    public const CHANNEL_ALIMPAY_QR = 'alimpay_qr';

    protected $fillable = [
        'user_id',
        'order_no',
        'channel',
        'out_trade_no',
        'provider_trade_no',
        'subject',
        'amount',
        'bonus_amount',
        'credit_amount',
        'paid_amount',
        'status',
        'trade_status',
        'credit_status',
        'payer_name',
        'payer_account',
        'voucher_image_url',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'paid_at',
        'credited_at',
        'expire_at',
        'remark',
        'pay_url',
        'channel_config_snapshot',
        'notify_payload',
        'credit_fail_msg',
        'review_remark',
        'rebate_event_id',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
            'credited_at' => 'datetime',
            'expire_at' => 'datetime',
            'channel_config_snapshot' => 'array',
            'notify_payload' => 'array',
            'user_id' => 'int',
            'reviewed_by' => 'int',
            'rebate_event_id' => 'int',
        ];
    }
}
