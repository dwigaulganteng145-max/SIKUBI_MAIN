<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'avatar_url', 'last_login_at',
        'can_import', 'can_manage_accounts', 'can_manage_settings',
        'can_detect_anomalies', 'can_edit_transactions', 'can_manage_cash_transactions',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'can_import' => 'boolean',
            'can_manage_accounts' => 'boolean',
            'can_manage_settings' => 'boolean',
            'can_detect_anomalies' => 'boolean',
            'can_edit_transactions' => 'boolean',
            'can_manage_cash_transactions' => 'boolean',
        ];
    }

    public function isDirektur(): bool
    {
        return $this->role === 'DIREKTUR';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN_KEUANGAN';
    }
}
