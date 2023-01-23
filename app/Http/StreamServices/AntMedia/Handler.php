<?php

namespace App\Http\StreamServices\AntMedia;

use Illuminate\Support\Facades\Http;
use Firebase\JWT\JWT;

class Handler
{
    public static function request(?int $ttl = 60, ?string $base_url = null)
    {
        $secret_key = env('ANTMEDIA_SECRET_KEY');
        $payload = [
            'exp' => time() + $ttl,
        ];
        $base_url = $base_url ?? env('ANTMEDIA_BASE_URL');

        if ($secret_key === null || $base_url === null) {
            throw new \Exception('Missing required environment variables.');
        }

        $jwt = JWT::encode($payload, $secret_key, 'HS256');

        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => $jwt,
        ])->baseUrl($base_url);
    }
}
