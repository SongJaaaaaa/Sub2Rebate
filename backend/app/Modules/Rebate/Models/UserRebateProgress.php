<?php

namespace App\Modules\Rebate\Models;

use Illuminate\Database\Eloquent\Model;

class UserRebateProgress extends Model
{
    protected $table = 'user_rebate_progress';

    protected $fillable = [
        'user_id',
        'total_recharge_amount',
        'milestone_times',
        'last_event_id',
    ];
}
