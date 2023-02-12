<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class SwipeMetrics extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'swipe_metrics';
    protected $primaryKey = 'id';
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'swipe_id',
        'created_at',
        'ip',
        'country',
        'city',
        'region',
        'user_agent',
        'device',
        'os',
        'browser',
        'load',
        'click',
        'like',
        'unlike',
        'dislike',
        'undislike',
        'view',
        'share',
        'comment'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'swipe_id' => 'string',
        'created_at' => 'timestamp',
        'ip' => 'string',
        'country' => 'string',
        'city' => 'string',
        'region' => 'string',
        'user_agent' => 'string',
        'device' => 'string',
        'os' => 'string',
        'browser' => 'string',
        'load' => 'integer',
        'click' => 'integer',
        'like' => 'integer',
        'unlike' => 'integer',
        'dislike' => 'integer',
        'undislike' => 'integer',
        'view' => 'integer',
        'share' => 'integer',
        'comment' => 'integer'
    ];
}
