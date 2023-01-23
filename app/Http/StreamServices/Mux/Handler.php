<?php

namespace App\Http\StreamServices\Mux;

use Illuminate\Support\Facades\Http;

class Handler
{
    public static function request(?string $token_id = null, ?string $token_secret = null, ?string $base_url = null)
    {
        $token_id = $token_id ?? env('MUX_TOKEN_ID');
        $token_secret = $token_secret ?? env('MUX_TOKEN_SECRET');
        $base_url = $base_url ?? env('MUX_BASE_URL');

        if ($token_id === null || $token_secret === null || $base_url === null) {
            throw new \Exception('Missing Mux credentials.');
        }

        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->withBasicAuth($token_id, $token_secret)
            ->baseUrl($base_url);
    }
}
