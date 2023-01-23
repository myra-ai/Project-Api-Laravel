<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class StoriesSwipe extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'stories_swipe';
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
        'story_id',
        'name',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'swipe_id' => 'integer',
        'story_id' => 'string',
        'name' => 'string',
    ];
}
