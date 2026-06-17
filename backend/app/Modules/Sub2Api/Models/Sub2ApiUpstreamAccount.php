<?php

namespace App\Modules\Sub2Api\Models;

use Illuminate\Database\Eloquent\Model;

class Sub2ApiUpstreamAccount extends Model
{
    protected $table = 'sub2api_upstream_accounts';

    protected $fillable = [
        'sub2api_id',
        'name',
        'provider',
        'model',
        'status',
        'used_quota',
        'total_quota',
        'request_count',
        'last_used_at',
        'last_synced_at',
        'last_error',
        'raw_account',
        'raw_usage',
        'raw_stats',
        'raw_today_stats',
    ];

    protected function casts(): array
    {
        return [
            'used_quota' => 'decimal:6',
            'total_quota' => 'decimal:6',
            'request_count' => 'int',
            'last_used_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'raw_account' => 'array',
            'raw_usage' => 'array',
            'raw_stats' => 'array',
            'raw_today_stats' => 'array',
        ];
    }
}
