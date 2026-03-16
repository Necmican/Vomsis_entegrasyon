<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',          
        'can_view_pos', 
        'can_view_physical_pos', 
        'can_create_tags',  // YENİ: Etiket üretebilme yetkisi
        'allowed_banks', 
        'allowed_tags',     // YENİ: Görebileceği etiketler
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'     => 'datetime',
            'password'              => 'hashed',
            'can_view_pos'          => 'boolean',
            'can_view_physical_pos' => 'boolean',
            'can_create_tags'       => 'boolean',
            'allowed_banks'         => 'array',
            'allowed_tags'          => 'array', 
        ];
    }
}