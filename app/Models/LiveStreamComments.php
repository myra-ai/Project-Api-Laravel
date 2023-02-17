<?php

namespace App\Models;

use App\Casts\Base64;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class LiveStreamComments extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'comments';
    protected $primaryKey = 'id';
    protected $dateFormat = 'Y-m-d H:i:s.u';
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'stream_id',
        'story_id',
        'text',
        'name',
        'email',
        'parent_id',
        'pinned',
        'is_streammer',
        'likes',
        'dislikes',
        'shares',
        'modified',
        'deleted_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'stream_id' => 'string',
        'story_id' => 'string',
        'text' => Base64::class,
        'name' => 'string',
        'email' => 'string',
        'parent_id' => 'integer',
        'pinned' => 'boolean',
        'is_streammer' => 'boolean',
        'likes' => 'integer',
        'dislikes' => 'integer',
        'shares' => 'integer',
        'modified' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];
}
