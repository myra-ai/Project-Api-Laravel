<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class LiveStreamCompanies extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'livestream_companies';
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'name',
        'email',
        'password',
        'email_verified_at',
        'token',
        'phone',
        'address',
        'city',
        'state',
        'zip',
        'country',
    ];

    protected $hidden = [
        'company_id',
        'password',
        'email_verified_at',
        'token',
        'phone',
        'address',
        'city',
        'state',
        'zip',
        'country',
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
        'company_id' => 'string',
        'name' => 'string',
        'email' => 'string',
        'password' => 'string',
        'email_verified_at' => 'timestamp',
        'token' => 'string',
        'phone' => 'string',
        'address' => 'string',
        'city' => 'string',
        'state' => 'string',
        'zip' => 'string',
        'country' => 'string',
    ];


    public function generateToken()
    {
        $this->token = Str::random(60);
        $this->save();
        return $this->token;
    }

    public function getCompanyUsers()
    {
        return $this->hasMany(LiveStreamCompanyUsers::class, 'company_id', 'id')->get();
    }

    public function getCompanyUser(string $id)
    {
        return $this->hasMany(LiveStreamCompanyUsers::class, 'company_id', 'id')->where('id', $id)->first();
    }

    public function getCompanyUserByEmail(string $email)
    {
        return $this->hasMany(LiveStreamCompanyUsers::class, 'company_id', 'id')->where('email', $email)->first();
    }

    public function getCompanyUserByToken(string $token)
    {
        return $this->hasMany(LiveStreamCompanyUsers::class, 'company_id', 'id')->where('token', $token)->first();
    }
}
