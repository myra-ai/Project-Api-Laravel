<?php

namespace App\Console\Commands\Live;

use App\Http\StreamServices\AntMedia\Stream as AntMediaStream;
use App\Http\StreamServices\Mux\Stream as MuxStream;
use App\Models\LiveStreams as mLiveStreams;
use Illuminate\Console\Command;

class StreamStatusCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:live/stream_status_check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Connect to the stream service and check all streams with status "active"';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $streams = match (strtolower(env('STREAM_SERVICE'))) {
            'mux' => MuxStream::getAllActiveStream(),
            'antmedia' => AntMediaStream::getAllActiveStream(),
            default => [],
        };

        if (count($streams) === 0) {
            return Command::SUCCESS;
        }

        print_r($streams);

        foreach ($streams as $stream) {
            $count_viewers = 0;
            $count_viewers += (int) $stream->webRTCViewerCount ?? 0;
            $count_viewers += (int) $stream->rtmpViewerCount ?? 0;
            $count_viewers += (int) $stream->hlsViewerCount ?? 0;
            mLiveStreams::where('stream_key', $stream->stream_id)->update([
                'status' => $stream->status,
                'count_viewers' => $count_viewers,
            ]);
        }

        return Command::SUCCESS;
    }
}
