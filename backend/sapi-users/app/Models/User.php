<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * users. A API nunca devolve a palavra-passe nem o hash.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids;

    /** Coluna da palavra-passe (o Laravel usa "password" por defeito). */
    protected $authPasswordName = 'password_hash';

    /** Não há coluna remember_token (autenticação por JWT na EAPI). */
    protected $rememberTokenName = '';

    protected $fillable = [
        'name',
        'email',
        'password_hash',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
