<?php

namespace App\Http\StreamServices\Mux;

use Firebase\JWT\JWT;

class Stream extends Handler
{
    public static function doCreateLive(string $latency = 'low', string $policy = 'public')
    {
        $latency = match (strtolower($latency)) {
            'low' => 'low',
            'reduced' => 'reduced',
            'standard' => 'standard',
            default => 'low',
        };

        $policy = match (strtolower($policy)) {
            'public' => 'public',
            'signed' => 'signed',
            'private' => 'private',
            default => 'public',
        };

        return self::request()->post('/video/v1/live-streams', [
            'latency_mode' => $latency,
            'reconnect_window' => 60,
            'playback_policy' => [$policy],
            'new_asset_settings' => [
                'playback_policy' => [$policy],
            ],
        ])->object();
    }

    public static function deleteLive(string $live_id)
    {
        return self::request()->delete('/video/v1/live-streams/' . $live_id)->object();
    }

    public static function getLiveViews(string $live_id)
    {
        // // https://docs.mux.com/guides/data/see-how-many-people-are-watching
        // $payload = [
        //     "sub" => $live_id,
        //     "exp" => now()->addMinutes(5)->timestamp,
        //     "aud" => "live_stream_id",
        //     "kid" => env('MUX_PRIVATE_KEY_ID'),
        // ];

        // $jwt = JWT::encode($payload, base64_decode(env('MUX_PRIVATE_KEY')), 'RS256');

        // return self::request()->get('https://stats.mux.com/counts', [
        //     'token' => $jwt,
        // ])->object();

        $list = self::request()->get('/data/v1/dimensions/:live_stream_id')->object();
        if (!isset($list->data)) {
            return 0;
        }

        foreach ($list->data as $stream) {
            if ($stream->value === $live_id) {
                return $stream->total_count;
            }
        }

        return 0;
    }

    public static function doCreateAsset(string $input = 'https://muxed.s3.amazonaws.com/leds.mp4')
    {
        return self::request()->post('/video/v1/assets', [
            'input' => [
                'url' => $input,
            ],
            'playback_policy' => [
                'public'
            ],
        ])->object();
    }

    public static function getLiveAssets()
    {
        return self::request()->get('/video/v1/assets', [
            'limit' => 25,
            'page' => 1,
        ])->object();
    }

    public static function getLiveAsset(string $live_id)
    {
        return self::request()->get('/video/v1/assets', [
            'limit' => 1,
            'page' => 1,
            'live_stream_id' => $live_id,
        ])->object();
    }

    public static function getStream(string $live_id)
    {
        return self::request()->get('/video/v1/live-streams/' . $live_id)->object();
    }

    public static function getAllStreams()
    {
        return self::request()->get('/video/v1/live-streams')->object();
    }

    public static function getAllAssets(string $live_id)
    {
        return self::getStream($live_id)->assets;
    }

    public static function getStatus(string $live_id): ?string
    {
        $stream = self::getStream($live_id);
        if (!isset($stream->data->status)) {
            return null;
        }
        return $stream->data->status;
    }

    public static function deleteStream(string $live_id)
    {
        return self::request()->delete('/video/v1/live-streams/' . $live_id)->object();
    }

    public static function deleteAsset(string $live_id, string $asset_id)
    {
        return self::request()->delete('/video/v1/live-streams/' . $live_id . '/assets/' . $asset_id)->object();
    }

    public static function deleteAllAssets(string $live_id)
    {
        $assets = self::getStream($live_id)->assets;

        foreach ($assets as $asset) {
            self::deleteAsset($live_id, $asset->id);
        }
    }

    public static function deleteAllStreams()
    {
        $streams = self::request()->get('/video/v1/live-streams')->object();

        foreach ($streams as $stream) {
            self::deleteStream($stream->id);
        }
    }
}
