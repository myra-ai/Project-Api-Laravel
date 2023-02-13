<?php

namespace App\Models;

use App\Http\Controllers\API;
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
        'clicks',
        'comments',
        'company_id',
        'deleted_at',
        'dislikes',
        'likes',
        'loads',
        'media_id',
        'publish',
        'shares',
        'status',
        'title',
        'views',
    ];

    protected $hidden = [
        'id',
        'clicks',
        'comments',
        'company_id',
        'created_at',
        'deleted_at',
        'dislikes',
        'likes',
        'loads',
        'views',
        'media_id',
        'shares',
        'updated_at',
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
        'comments' => 'integer',
        'clicks' => 'integer',
        'comments' => 'integer',
        'dislikes' => 'integer',
        'likes' => 'integer',
        'loads' => 'integer',
        'shares' => 'integer',
        'views' => 'integer',
        'deleted_at' => 'timestamp',
    ];

    public function getSource()
    {
        return $this->hasOne(LiveStreamMedias::class, 'id', 'media_id')->first();
    }

    public function getThumbnail()
    {
        return $this->hasOne(LiveStreamMedias::class, 'parent_id', 'media_id')->where('type', '=', API::MEDIA_TYPE_IMAGE_THUMBNAIL)->first();
    }
}
