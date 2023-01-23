<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class LiveStreamSettings extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'livestream_settings';
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
        'primary_color',
        'cta_color',
        'accent_color',
        'text_chat_color',
        'notification_text',
        'notification_email',
        'rtmp_key',
        'avatar',
        'logo',
        'stories_is_embedded',
        'livestream_autoopen',
    ];

    protected $hidden = [
        'id',
        'rtmp_key',
        'notification_text',
        'notification_email',
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
        'primary_color' => 'string',
        'cta_color' => 'string',
        'accent_color' => 'string',
        'text_chat_color' => 'string',
        'notification_text' => 'string',
        'notification_email' => 'string',
        'rtmp_key' => 'string',
        'avatar' => 'string',
        'logo' => 'string',
        'stories_is_embedded' => 'boolean',
        'livestream_autoopen' => 'boolean',
    ];
}
