<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class LiveStreamProductGroups extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'product_groups';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'product_id',
        'stream_id',
        'story_id',
        'promoted',
        'time_from',
        'time_to'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'product_id' => 'string',
        'stream_id' => 'string',
        'story_id' => 'string',
        'promoted' => 'boolean',
        'time_from' => 'float',
        'time_to' => 'float',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];
}
