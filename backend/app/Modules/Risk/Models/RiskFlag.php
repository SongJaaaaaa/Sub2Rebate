<?php

namespace App\Modules\Risk\Models;

use Illuminate\Database\Eloquent\Model;

class RiskFlag extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_RESOLVED = 'resolved';

    public const TYPE_BLACKLIST = 'blacklist';
    public const TYPE_WITHDRAW_FREEZE = 'withdraw_freeze';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'reason',
        'created_by',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
