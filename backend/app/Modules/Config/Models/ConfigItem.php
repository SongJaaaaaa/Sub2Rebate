<?php

namespace App\Modules\Config\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigItem extends Model
{
    protected $fillable = [
        'key',
        'group',
        'name',
        'type',
        'value',
        'tips',
        'sort',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'sort' => 'int',
            'is_public' => 'bool',
        ];
    }
}
