<?php

namespace App\Modules\Rebate\Models;

use Illuminate\Database\Eloquent\Model;

class RebateRecord extends Model
{
    public const TYPE_MILESTONE = 'milestone';
    public const TYPE_DECAY = 'decay';

    protected $fillable = [
        'event_id',
        'payer_user_id',
        'receiver_user_id',
        'type',
        'level',
        'source_amount',
        'rebate_amount',
        'status',
        'config_snapshot',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'config_snapshot' => 'array',
        ];
    }
}
