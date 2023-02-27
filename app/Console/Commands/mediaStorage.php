<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class mediaStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:media_storage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a default media storage folders';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (Storage::directoryMissing('public/images')) {
            Storage::makeDirectory('public/images');
            $this->info('Created directory: public/images');
        }

        if (Storage::directoryMissing('public/images/thumbnails')) {
            Storage::makeDirectory('public/images/thumbnails');
            $this->info('Created directory: public/images/thumbnails');
        }

        if (Storage::directoryMissing('public/videos')) {
            Storage::makeDirectory('public/videos');
            $this->info('Created directory: public/videos');
        }

        if (Storage::directoryMissing('public/unknown')) {
            Storage::makeDirectory('public/unknown');
            $this->info('Created directory: public/unknown');
        }

        $this->info('Done!');
        return Command::SUCCESS;
    }
}
