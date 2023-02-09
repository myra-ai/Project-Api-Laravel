<?php

namespace App\Console\Commands;

use App\Models\LiveStreamCompanyTokens as mLiveStreamCompanyTokens;
use Illuminate\Console\Command;

class removeTokensExpired extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:remove_tokens_expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove tokens expired';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Checking tokens expired...');

        $tokens = mLiveStreamCompanyTokens::where('expires_at', '<', now()->format('Y-m-d H:i:s'))->get();
        foreach ($tokens as $t) {
            $this->info('Token expired: ' . $t->token);
            $t->delete();
        }

        $this->info('Done!');
        return Command::SUCCESS;
    }

}
