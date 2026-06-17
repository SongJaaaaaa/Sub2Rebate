<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasName
{
    use HasApiTokens;
    use Notifiable;

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'username',
        'email',
        'role',
        'status',
        'rebate_status',
        'sub2api_aff_code',
        'sub2api_inviter_id',
        'last_recharge_at',
        'last_balance_decreased_at',
        'last_invited_at',
        'rebate_disabled_at',
        'rebate_disabled_reason',
        'last_sub2api_balance',
        'last_sub2api_total_recharged',
        'last_balance_checked_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'sub2api_inviter_id' => 'int',
            'last_recharge_at' => 'datetime',
            'last_balance_decreased_at' => 'datetime',
            'last_invited_at' => 'datetime',
            'rebate_disabled_at' => 'datetime',
            'last_sub2api_balance' => 'decimal:6',
            'last_sub2api_total_recharged' => 'decimal:6',
            'last_balance_checked_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->role === 'admin'
            && $this->status === 'active';
    }

    public function getFilamentName(): string
    {
        return (string) ($this->username ?: $this->email ?: 'user_'.$this->id);
    }
}
