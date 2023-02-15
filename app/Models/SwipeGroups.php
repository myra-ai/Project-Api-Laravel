<?php

namespace App\Models;

use App\Casts\Timestamp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class SwipeGroups extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'swipe_groups';
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
        'swipe_id',
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
        'swipe_id' => 'string',
        'story_id' => 'string',
        'created_at' => Timestamp::class,
        'updated_at' => Timestamp::class,
    ];

    public function getStory()
    {
        return $this->belongsTo(Stories::class, 'story_id', 'id')->first();
    }
}
