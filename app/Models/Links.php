<?php

namespace App\Models;

use App\Casts\Base64;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Links extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'links';
    protected $primaryKey = 'id';
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'url',
        'clicks',
        'checksum',
        'deleted_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'url' => Base64::class,
        'clicks' => 'integer',
        'checksum' => 'string',
        'deleted_at' => 'timestamp',
    ];

    protected $hidden = [
        'clicks',
        'checksum',
        'created_at',
        'deleted_at',
        'updated_at',
    ];
}
