<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class PasswordResets extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'password_resets';
    protected $primaryKey = 'email';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email',
        'token',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email' => 'string',
        'token' => 'string',
        'created_at' => 'timestamp',
    ];
}
