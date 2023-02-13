<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class SwipeGroups extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'swipe_groups';
    protected $primaryKey = 'id';
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'story_id',
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
        'story_id' => 'string',
        'title' => 'string',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];
}
