<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'title_prefix',
        'title_suffix',
        'rank',
        'nrp',
        'nip',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function getDisplayNameWithTitleAttribute(): string
    {
        $parts = array_filter([
            $this->title_prefix ? trim($this->title_prefix) : null,
            $this->name ? trim($this->name) : null,
            $this->title_suffix ? trim($this->title_suffix) : null,
        ]);

        if (count($parts) === 0) {
            return (string) $this->name;
        }

        return implode(' ', $parts);
    }

    public function getIdentificationNumberAttribute(): ?string
    {
        return $this->nrp ?: $this->nip;
    }
}
