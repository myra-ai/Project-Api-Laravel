<?php

namespace App\Models;

use App\Casts\Json;
use App\Casts\Timestamp;
use App\Http\Controllers\API;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class LiveStreamCompanyUsers extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'livestream_company_users';
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
        'company_id',
        'permissions',
        'role',
        'name',
        'email',
        'password',
        'email_verified_at',
        'phone_country',
        'phone_country_dial',
        'phone',
        'avatar',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'last_login',
        'last_login_ip',
        'is_master',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'id',
        'company_id',
        'permissions',
        'role',
        'password',
        'email_verified_at',
        'phone_country',
        'phone_country_dial',
        'phone',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'last_login',
        'last_login_ip',
        'is_master',
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
        'permissions' => Json::class,
        'role' => 'integer',
        'name' => 'string',
        'email' => 'string',
        'password' => 'string',
        'email_verified_at' => 'timestamp',
        'phone_country' => 'string',
        'phone_country_dial' => 'string',
        'phone' => 'string',
        'avatar' => 'string',
        'address' => 'string',
        'city' => 'string',
        'state' => 'string',
        'zip' => 'string',
        'country' => 'string',
        'last_login' => 'timestamp',
        'last_login_ip' => 'string',
        'is_master' => 'boolean',
        'deleted_at' => 'timestamp',
        'created_at' => Timestamp::class,
        'updated_at' => 'timestamp',
    ];

    public function generateToken($expires_at): ?string
    {
        $token = Str::random(60);

        try {
            LiveStreamCompanyTokens::create([
                'user_id' => $this->id,
                'token' => $token,
                'expires_at' => $expires_at,
            ]);
        } catch (\Exception $e) {
            if (config('app.debug')) {
                throw $e;
            }
            $token = null;
        }

        return $token;
    }

    public function getToken(): ?string
    {
        $token = null;

        try {
            $token = LiveStreamCompanyTokens::where('user_id', $this->id)->first()->token;
        } catch (\Exception $e) {
            if (config('app.debug')) {
                throw $e;
            }
        }

        return $token;
    }

    public function updateOrCreateToken($expires_at): ?string
    {
        $token = null;

        try {
            $token = LiveStreamCompanyTokens::updateOrCreate(
                ['user_id' => $this->id],
                ['expires_at' => $expires_at]
            )->token;
        } catch (\Exception $e) {
            if (config('app.debug')) {
                throw $e;
            }
        }

        return $token;
    }

    public function getAvatar()
    {
        $media = $this->hasOne(LiveStreamMedias::class, 'id', 'avatar')->first();

        if ($media == null) {
            return null;
        }

        return (object)[
            "height" => $media->height,
            "media_id" => $media->id,
            "mime" => $media->mime,
            "url" => match ($media->s3_available) {
                null => API::getMediaUrl($media->id),
                default => API::getMediaCdnUrl($media->path)
            },
            "width" => $media->width,

        ];
    }
}
