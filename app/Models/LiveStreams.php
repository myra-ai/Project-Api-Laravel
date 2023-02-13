<?php

namespace App\Models;

use App\Casts\Base64;
use App\Casts\Timestamp;
use App\Http\Controllers\API;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class LiveStreams extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'livestreams';
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
        'audio_only',
        'clicks',
        'comments',
        'company_id',
        'deleted_at',
        'dislikes',
        'duration',
        'latency_mode',
        'likes',
        'live_id',
        'loads',
        'max_duration',
        'note',
        'orientation',
        'shares',
        'sheduled_at',
        'status',
        'stream_key',
        'thumbnail_id',
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
        'duration',
        'latency_mode',
        'likes',
        'live_id',
        'loads',
        'max_duration',
        'note',
        'shares',
        'sheduled_at',
        'stream_key',
        'thumbnail_id',
        'updated_at',
        'views',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'audio_only' => 'boolean',
        'clicks' => 'integer',
        'comments' => 'integer',
        'company_id' => 'string',
        'deleted_at' => 'timestamp',
        'dislikes' => 'integer',
        'duration' => 'integer',
        'latency_mode' => 'string',
        'likes' => 'integer',
        'live_id' => 'string',
        'loads' => 'integer',
        'max_duration' => 'integer',
        'note' => Base64::class,
        'orientation' => 'string',
        'shares' => 'integer',
        'sheduled_at' => Timestamp::class,
        'status' => 'string',
        'stream_key' => 'string',
        'thumbnail_id' => 'string',
        'title' => Base64::class,
        'views' => 'integer',
    ];

    public function getLatestStreamID(string $company_id)
    {
        $stream = $this->where('company_id', '=', $company_id)->where('status', '=', 'active')->orderBy('created_at', 'desc')->first();
        return $stream->id ?? null;
    }

    public function getSource()
    {
        $url = match ($this->latency_mode) {
            'low' => 'wss://origin.gobliver.co/WebRTCAppEE/' . $this->live_id . '.webrtc',
            'normal' => 'https://origin.gobliver.co/WebRTCAppEE/streams/' . $this->live_id . '.m3u8',
            default => 'https://origin.gobliver.co/WebRTCAppEE/streams/' . $this->live_id . '.m3u8',
        };
        return (object) [
            'url' => $url,
        ];
    }

    public function getThumbnail()
    {
        $media = $this->hasOne(LiveStreamMedias::class, 'id', 'thumbnail_id')->where('type', '=', API::MEDIA_TYPE_IMAGE_THUMBNAIL)->first();

        if ($media == null) {
            return null;
        }

        return match ($media->s3_available) {
            null => API::getMediaUrl($media->id),
            default => API::getMediaCdnUrl($media->path)
        };
    }

    public function getThumbnailDetails()
    {
        $media = $this->hasOne(LiveStreamMedias::class, 'id', 'thumbnail_id')->where('type', '=', API::MEDIA_TYPE_IMAGE_THUMBNAIL)->first();

        if ($media == null) {
            return null;
        }

        return (object) [
            'id' => $media->id,
            'url' => match ($media->s3_available) {
                null => API::getMediaUrl($media->id),
                default => API::getMediaCdnUrl($media->path)
            },
            'alt' => $media->alt,
            'mime' => $media->mime,
            'width' => $media->width,
            'height' => $media->height,
        ];
    }

    public function getProducts()
    {
        return $this->hasMany(LiveStreamProductGroups::class, 'stream_id', 'id')->get();
    }
}
