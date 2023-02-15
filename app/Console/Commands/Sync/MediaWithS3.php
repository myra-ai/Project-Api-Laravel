<?php

namespace App\Console\Commands\Sync;

use Illuminate\Console\Command;
use App\Models\LiveStreamMedias as mLiveStreamMedias;
use App\Http\Controllers\API;
use App\Jobs\SyncWithS3;

class MediaWithS3 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:sync/media_with_s3';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all files without available in S3 and sync them';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        mLiveStreamMedias::where('s3_available', '=', null)->chunk(100, function ($medias) {
            if (count($medias) === 0) {
                return Command::SUCCESS;
            }

            foreach ($medias as $media) {
                $this->info("[::] Syncing media {$media->id} with S3");
                if (SyncWithS3::dispatch($media)->onQueue('sync_with_s3')->onConnection('sync_with_s3')->delay(now()->addSeconds(1)) === false) {
                    $this->error("[!!] Failed to dispatch SyncWithS3 job for media {$media->id}");
                } else {
                    $this->info("[OK] SyncWithS3 job dispatched for media {$media->id}");
                }
            }
        });

        return Command::SUCCESS;
    }
}
