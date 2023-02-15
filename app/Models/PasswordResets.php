<?php

namespace App\Models;

use App\Casts\Timestamp;
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
        'shorten_code',
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
        'shorten_code' => 'string',
        'created_at' => Timestamp::class,
    ];
}
