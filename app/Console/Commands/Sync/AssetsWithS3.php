<?php

namespace App\Console\Commands\Sync;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AssetsWithS3 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:sync/assets_with_s3';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync assets with CDN';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('[::] Looking for assets without CDN sync');

        // Read assets directory
        $assets = Storage::disk('dashboard_assets')->allFiles();
        if (($assets_count = count($assets)) === 0) {
            $this->info(' [-] No assets found');
            return Command::SUCCESS;
        }

        $count = -1;
        // Check if asset is already synced
        foreach ($assets as $asset) {
            $count++;
            $percentage = round(($count / $assets_count) * 100, 2);

            $this->info("[::] Checking asset {$asset} ({$percentage}%)");

            $s3_path = 'assets/' . $asset;
            if (Storage::disk('s3')->exists($s3_path)) {
                $this->info(" [/] Asset already synced");
                continue;
            }

            // Sync asset with CDN
            $this->info(" [+] Syncing asset with CDN");
            if (Storage::disk('s3')->put($s3_path, Storage::disk('dashboard_assets')->get($asset), [
                'ACL' => 'public-read',
            ])) {
                $this->info(" [+] Asset synced with CDN");
            } else {
                $this->info(" [-] Failed to sync asset with CDN");
            }
        }

        $this->info('Done!');
        return Command::SUCCESS;
    }
}
