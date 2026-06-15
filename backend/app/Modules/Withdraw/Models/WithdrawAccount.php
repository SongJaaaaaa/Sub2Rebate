<?php

namespace App\Modules\Withdraw\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawAccount extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'real_name',
        'account_no',
    ];
}
