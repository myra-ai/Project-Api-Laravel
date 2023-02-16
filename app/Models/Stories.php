<?php

namespace App\Models;

use App\Http\Controllers\API;
use App\Jobs\MediaResizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Stories extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'stories';
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

    public function getSource(string $order_by = 'created_at', string $order = 'asc', int $offset = 0, int $limit = 1)
    {
        $source = $this->hasOne(LiveStreamMedias::class, 'id', 'media_id')
            ->orderBy($order_by, $order)
            ->offset($offset)
            ->limit($limit)
            ->first();

        if ($source === null) {
            return null;
        }

        return (object) [
            'id' => $source->id,
            'alt' => $source->alt ?? null,
            'mime' => $source->mime ?? null,
            'width' => $source->width ?? null,
            'height' => $source->height ?? null,
            'url' => $source->s3_available !== null ? API::getMediaCdnUrl($source->path) : API::getMediaUrl($source->id),
        ];
    }

    public function getThumbnail(string $order_by = 'created_at', string $order = 'asc', int $offset = 0, int $limit = 1)
    {
        $thumbnail = $this->hasOne(LiveStreamMedias::class, 'parent_id', 'media_id')
            ->where('type', '=', API::MEDIA_TYPE_IMAGE_THUMBNAIL)
            ->orderBy($order_by, $order)
            ->offset($offset)
            ->limit($limit)
            ->first();

        if ($thumbnail === null) {
            return null;
        }

        return (object) [
            'id' => $thumbnail->id,
            'alt' => $thumbnail->alt ?? null,
            'mime' => $thumbnail->mime ?? null,
            'width' => $thumbnail->width ?? null,
            'height' => $thumbnail->height ?? null,
            'url' => $thumbnail->s3_available !== null ? API::getMediaCdnUrl($thumbnail->path) : API::getMediaUrl($thumbnail->id),
        ];
    }

    public function getThumbnailOptimized(int $width = 512, int $height = 512, string $mode = 'resize', bool $keep_aspect_ratio = true, int $quality = 80, bool $blur = true, string $order_by = 'created_at', string $order = 'asc', int $offset = 0)
    {
        $thumbnail = $this->hasOne(LiveStreamMedias::class, 'parent_id', 'media_id')
            ->where('type', '=', API::MEDIA_TYPE_IMAGE_THUMBNAIL)
            ->orderBy($order_by, $order)
            ->offset($offset)
            ->first();

        if ($thumbnail === null) {
            return null;
        }

        if (($optimized = API::mediaSized($thumbnail->id, $width, $height, $order_by, $order, $offset)) === null) {
            MediaResizer::dispatch($thumbnail->id, $width, $height, $mode, $keep_aspect_ratio, $quality, $blur);
        } else {
            return (object) [
                'id' => $optimized->id,
                'alt' => $thumbnail->alt ?? null,
                'mime' => $thumbnail->mime ?? null,
                'width' => $optimized->width ?? null,
                'height' => $optimized->height ?? null,
                'url' => $optimized->s3_available !== null ? API::getMediaCdnUrl($optimized->path) : API::getMediaUrl($optimized->id),
            ];
        }

        return (object) [
            'id' => $thumbnail->id,
            'alt' => $thumbnail->alt ?? null,
            'mime' => $thumbnail->mime ?? null,
            'width' => $thumbnail->width ?? null,
            'height' => $thumbnail->height ?? null,
            'url' => $thumbnail->s3_available !== null ? API::getMediaCdnUrl($thumbnail->path) : API::getMediaUrl($thumbnail->id),
        ];
    }

    public function getThumbnails(string $order_by = 'created_at', string $order = 'asc', int $offset = 0, int $limit = 30)
    {
        $thumbnails = $this->hasMany(LiveStreamMedias::class, 'parent_id', 'media_id')
            ->where('type', '=', API::MEDIA_TYPE_IMAGE_THUMBNAIL)
            ->orderBy($order_by, $order)
            ->offset($offset)
            ->limit($limit)
            ->get();

        if ($thumbnails === null) {
            return null;
        }

        return $thumbnails->map(function ($thumbnail) {
            return (object) [
                'id' => $thumbnail->id,
                'alt' => $thumbnail->alt ?? null,
                'mime' => $thumbnail->mime ?? null,
                'width' => $thumbnail->width ?? null,
                'height' => $thumbnail->height ?? null,
                'url' => $thumbnail->s3_available !== null ? API::getMediaCdnUrl($thumbnail->path) : API::getMediaUrl($thumbnail->id),
            ];
        });
    }

    public function isAttachWith(string $swipe_id)
    {
        return $this->hasOne(SwipeGroups::class, 'story_id', 'id')
            ->where('swipe_id', '=', $swipe_id)
            ->exists();
    }
}
