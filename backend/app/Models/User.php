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
        'sub2api_aff_code',
        'sub2api_inviter_id',
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
