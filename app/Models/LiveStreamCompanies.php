<?php

namespace App\Models;

use App\Http\Controllers\API;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class LiveStreamCompanies extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'livestream_companies';
    protected $primaryKey = 'id';
    protected $dateFormat = 'Y-m-d H:i:s.u';
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'primary_color',
        'cta_color',
        'accent_color',
        'text_chat_color',
        'rtmp_key',
        'avatar',
        'logo',
        'font',
        'stories_is_embedded',
        'livestream_autoopen',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'id',
        'rtmp_key',
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
        'tenant_id' => 'string',
        'name' => 'string',
        'address' => 'string',
        'city' => 'string',
        'state' => 'string',
        'zip' => 'string',
        'country' => 'string',
        'primary_color' => 'string',
        'cta_color' => 'string',
        'accent_color' => 'string',
        'text_chat_color' => 'string',
        'rtmp_key' => 'string',
        'avatar' => 'string',
        'logo' => 'string',
        'font' => 'integer',
        'stories_is_embedded' => 'boolean',
        'livestream_autoopen' => 'boolean',
        'deleted_at' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public function users()
    {
        return $this->hasMany(LiveStreamCompanyUsers::class, 'company_id', 'id');
    }

    public function getCompanyUsers()
    {
        return $this->users()->get();
    }

    public function getCompanyUser(string $id)
    {
        return $this->hasMany(LiveStreamCompanyUsers::class, 'company_id', 'id')->where('id', $id)->first();
    }

    public function getCompanyUserByEmail(string $email)
    {
        return $this->hasMany(LiveStreamCompanyUsers::class, 'company_id', 'id')->where('email', $email)->first();
    }

    public function getAvatar()
    {
        $media = LiveStreamMedias::where('id', $this->avatar)->first();
        
        if (!$media) {
            return null;
        }

        return (object) [
            'media_id' => $media->id,
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

    public function getLogo()
    {
        $media = LiveStreamMedias::where('id', $this->logo)->first();

        if (!$media) {
            return null;
        }

        return (object) [
            'media_id' => $media->id,
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
}
