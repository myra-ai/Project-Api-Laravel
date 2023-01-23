<?php

namespace App\Console\Commands\Live;

use Illuminate\Console\Command;
use App\Models\LiveStreamMedias as mLiveStreamMedias;
use App\Http\Controllers\API;

class SyncWithS3 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:live/sync_with_s3';

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
        mLiveStreamMedias::where('s3_available', '=', null)->chunk(250, function ($medias) {
            if (count($medias) === 0) {
                return Command::SUCCESS;
            }
    
            foreach ($medias as $media) {
                echo "Syncing media {$media->id} with CDN" . PHP_EOL;
                if (API::doSyncMediaWithCDN($media) !== false) {
                    echo " [+] Media {$media->id} synced with CDN" . PHP_EOL;
                } else {
                    echo " [/] Media {$media->id} failed to sync with CDN" . PHP_EOL;
                }
            }
        });

        return Command::SUCCESS;
    }
}
