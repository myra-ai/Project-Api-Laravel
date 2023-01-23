<?php

namespace App\Http\StreamServices\AntMedia;

use Illuminate\Support\Str;

class Stream extends Handler
{
    public static function doCreateLive(?string $stream_id = null, string $latency_mode = 'low', array $args = [])
    {
        if ($stream_id === null) {
            $stream_id = Str::uuid()->toString();
        }

        $params = array_merge(['streamId' => $stream_id], $args);

        $base_url = match ($latency_mode) {
            'low' => '/WebRTCAppEE/rest',
            'normal' => '/LiveApp/rest',
            default => '/WebRTCAppEE/rest',
        };

        return self::request()->post($base_url . '/v2/broadcasts/create?autoStart=false', $params)->object();
    }

    public static function doDeleteLive(string $stream_id, string $latency_mode = 'low')
    {
        $base_url = match ($latency_mode) {
            'low' => '/WebRTCAppEE/rest',
            'normal' => '/LiveApp/rest',
            default => '/WebRTCAppEE/rest',
        };

        return self::request()->delete($base_url . '/v2/broadcasts/' . $stream_id)->object();
    }

    public static function getStream(string $stream_id, string $latency_mode = 'low')
    {
        $base_url = match ($latency_mode) {
            'low' => '/WebRTCAppEE/rest',
            'normal' => '/LiveApp/rest',
            default => '/WebRTCAppEE/rest',
        };

        return self::request()->get($base_url . '/v2/broadcasts/' . $stream_id)->object();
    }

    public static function getAllActiveStream(): array
    {
        $offset = 0;
        $size = 50;
        $total = 0;

        $streams = [];

        try {
            $total = self::request()->get('/WebRTCAppEE/rest/v2/broadcasts/count')->object()->number ?? 0;
        } catch (\Exception $e) {
            // Ignore
        }

        if ($total === 0) {
            return $streams;
        }

        while ($offset < $total) {
            $streamChunk = self::request()->get('/WebRTCAppEE/rest/v2/broadcasts/list/' . $offset . '/' . $size)->json();

            if (!is_array($streamChunk)) {
                break;
            }

            foreach ($streamChunk as $chunk) {
                $chunk = (object) $chunk;
                if ($chunk->status === 'broadcasting') {
                    $streams[] = (object) [
                        'stream_id' => $chunk->streamId,
                        'status' => $chunk->status,
                        'webRTCViewerCount' => $chunk->webRTCViewerCount,
                        'hlsViewerCount' => $chunk->hlsViewerCount,
                        'rtmpViewerCount' => $chunk->rtmpViewerCount,
                        'startTime' => $chunk->startTime,
                        'updateTime' => $chunk->updateTime,
                    ];
                }
            }

            $offset += $size;
        }

        return $streams;
    }
}
