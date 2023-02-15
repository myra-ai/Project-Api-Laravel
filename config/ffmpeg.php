<?php

return [
    'ffmpeg' => [
        // Path to the FFmpeg binary
        'binaries' => env('FFMPEG_BINARIES', '/usr/bin/ffmpeg'),
        // FFmpeg threads count (default: 12)
        'threads' => env('FFMPEG_THREADS', 12),
    ],
    'ffprobe' => [
        // Path to the ffprobe binary
        'binaries' => env('FFPROBE_BINARIES', '/usr/bin/ffprobe'),
    ],
    // The timeout for the underlying process (in seconds). Default: 3600 (1 hour)
    'timeout' => env('FFMPEG_TIMEOUT', 3600),
];
