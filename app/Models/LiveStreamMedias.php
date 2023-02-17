<?php

namespace App\Models;

use App\Casts\Base64;
use App\Http\Controllers\API;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class LiveStreamMedias extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'medias';
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
        'company_id',
        'parent_id',
        'checksum',
        'original_name',
        'file_name',
        'original_url',
        'path',
        's3_available',
        'is_blurred',
        'is_resized',
        'policy',
        'type',
        'mime',
        'extension',
        'size',
        'width',
        'height',
        'duration',
        'bitrate',
        'framerate',
        'channels',
        'alt',
        'legend',
    ];

    protected $hidden = [
        'checksum',
        'company_id',
        'original_name',
        'original_url',
        's3_available',
        'is_blurred',
        'is_resized',
        'policy',
        'size',
        'duration',
        'bitrate',
        'framerate',
        'channels',
        'deleted_at',
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
        'company_id' => 'string',
        'parent_id' => 'string',
        'checksum' => 'string',
        'original_name' => 'string',
        'file_name' => 'string',
        'original_url' => Base64::class,
        'path' => 'string',
        'is_blurred' => 'boolean',
        'is_resized' => 'boolean',
        'policy' => 'string',
        'type' => 'integer',
        'mime' => 'string',
        'extension' => 'string',
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration' => 'float',
        'bitrate' => 'float',
        'framerate' => 'float',
        'channels' => 'string',
        'alt' => Base64::class,
        'legend' => Base64::class,
        'deleted_at' => 'datetime',
    ];

    public function getThumbnail()
    {
        return $this->hasOne(LiveStreamMedias::class, 'parent_id', 'id')->where('type', '=', API::MEDIA_TYPE_IMAGE_THUMBNAIL)->first();
    }

    public function getThumbnails()
    {
        return $this->hasMany(LiveStreamMedias::class, 'parent_id', 'id')->where('type', '=', API::MEDIA_TYPE_IMAGE_THUMBNAIL)->get();
    }
}
