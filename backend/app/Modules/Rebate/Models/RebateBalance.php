<?php

namespace App\Modules\Rebate\Models;

use Illuminate\Database\Eloquent\Model;

class RebateBalance extends Model
{
    protected $fillable = [
        'user_id',
        'available_amount',
        'frozen_amount',
        'withdrawn_amount',
    ];
}
