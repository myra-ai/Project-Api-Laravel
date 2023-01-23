<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class LiveStreamAssets extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'livestream_assets';
    protected $primaryKey = 'id';
    public $timestamps = true;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'livestream_id',
        'path',
        'policy',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'livestream_id' => 'string',
        'path' => 'string',
        'policy' => 'string',
    ];
}
