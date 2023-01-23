<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class LiveStreamPlaybacks extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'livestream_playbacks';
    protected $primaryKey = 'id';
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'livestream_id',
        'policy',
        'type',
        'format',
        'resolution',
        'frame_rate',
        'bit_rate',
        'audio_codec',
        'video_codec',
        'audio_bit_rate',
        'video_bit_rate',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'livestream_id' => 'string',
        'policy' => 'string',
        'type' => 'string',
        'format' => 'string',
        'resolution' => 'string',
        'frame_rate' => 'string',
        'bit_rate' => 'string',
        'audio_codec' => 'string',
        'video_codec' => 'string',
        'audio_bit_rate' => 'string',
        'video_bit_rate' => 'string',
    ];
}
