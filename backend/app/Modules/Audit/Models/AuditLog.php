<?php

namespace App\Modules\Audit\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_user_id',
        'target_user_id',
        'module',
        'action',
        'subject_type',
        'subject_id',
        'before_values',
        'after_values',
        'ip',
        'user_agent',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
        ];
    }
}
