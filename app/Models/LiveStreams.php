<?php

namespace App\Models;

use App\Casts\Base64;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Http\Controllers\API;

class LiveStreams extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'livestreams';
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
        'title',
        'sheduled_at',
        'thumbnail_id',
        'live_id',
        'stream_key',
        'latency_mode',
        'audio_only',
        'orientation',
        'status',
        'duration',
        'count_viewers',
        'total_likes',
        'total_dislikes',
        'total_comments',
        'total_shares',
        'total_viewers',
        'total_widget_views',
        'total_widget_clicks',
        'max_duration',
        'note',
        'deleted_at',
    ];

    protected $hidden = [
        'company_id',
        'live_id',
        'stream_key',
        'duration',
        'total_dislikes',
        'total_shares',
        'total_viewers',
        'total_widget_views',
        'total_widget_clicks',
        'deleted_at',
        'note',
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
        'title' => Base64::class,
        'sheduled_at' => 'timestamp',
        'thumbnail_id' => 'string',
        'live_id' => 'string',
        'stream_key' => 'string',
        'latency_mode' => 'string',
        'audio_only' => 'boolean',
        'orientation' => 'string',
        'status' => 'string',
        'duration' => 'integer',
        'count_viewers' => 'integer',
        'total_likes' => 'integer',
        'total_dislikes' => 'integer',
        'total_comments' => 'integer',
        'total_shares' => 'integer',
        'total_viewers' => 'integer',
        'total_widget_views' => 'integer',
        'total_widget_clicks' => 'integer',
        'max_duration' => 'integer',
        'note' => Base64::class,
        'deleted_at' => 'timestamp',
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
            default => 'https://EDGE.gobliver.co/WebRTCAppEE/streams/' . $this->live_id . '.m3u8',
        };
        return (object) [
            'url' => $url,
        ];
    }

    public function getThumbnail()
    {
        return $this->hasOne(LiveStreamMedias::class, 'id', 'thumbnail_id')->where('type', '=', API::MEDIA_TYPE_IMAGE_THUMBNAIL)->first();
    }
}
