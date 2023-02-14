<?php

namespace App\Console\Commands\Sync;

use Illuminate\Console\Command;
use App\Models\LiveStreamMedias as mLiveStreamMedias;
use App\Http\Controllers\API;

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
                if (API::doSyncMediaWithCDN($media) !== false) {
                    $this->info(" [+] Synced media with S3");
                } else {
                    $this->info(" [-] Failed to sync media with S3");
                }
            }
        });

        return Command::SUCCESS;
    }
}
