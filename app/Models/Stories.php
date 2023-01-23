<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Stories extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'stories';
    protected $primaryKey = 'id';
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'company_id',
        'media_id',
        'title',
        'publish',
        'status',
        'total_comments',
        'total_dislikes',
        'total_likes',
        'total_shares',
        'total_views',
        'total_clicks',
        'total_opens',
        'deleted_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'media_id' => 'string',
        'title' => 'string',
        'publish' => 'boolean',
        'status' => 'string',
        'total_comments' => 'integer',
        'total_dislikes' => 'integer',
        'total_likes' => 'integer',
        'total_shares' => 'integer',
        'total_views' => 'integer',
        'total_clicks' => 'integer',
        'total_opens' => 'integer',
        'deleted_at' => 'timestamp',
    ];

    public function getSource()
    {
        return $this->hasOne(LiveStreamMedias::class, 'id', 'media_id')->first();
    }

    public function getThumbnail()
    {
        return $this->hasOne(LiveStreamMedias::class, 'parent_id', 'media_id')->where('is_thumbnail', true)->first();
    }
}
