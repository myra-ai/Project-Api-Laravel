<?php

namespace App\Jobs;

use App\Http\Controllers\API;
use App\Models\LiveStreamMedias as mLiveStreamMedias;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncWithS3 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(public mLiveStreamMedias|string $media)
    {
        //
    }

    public function updateDatabase(): bool
    {
        if ($this->media === null || is_string($this->media)) {
            return false;
        }

        $this->media->s3_available = now()->format('Y-m-d H:i:s.u');
        if ($this->media->save() === false) {
            return false;
        }

        return Cache::put('media_by_id_' . $this->media->id, $this->media, now()->addSeconds(API::CACHE_TTL_MEDIA));
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (is_string($this->media)) {
            $this->media = mLiveStreamMedias::where('id', '=', $this->media)->first();
        }

        if ($this->media === null) {
            Log::error('SyncWithS3: Media not found');
            return false;
        }

        if ($this->media->s3_available !== null) {
            Log::info('SyncWithS3: Media already synced');
            return true;
        }

        $s3Available = Storage::disk('s3')->exists($this->media->path);
        if ($s3Available === true) {
            Log::info('SyncWithS3: Media already synced, checking checksum');

            //     $s3Metadata = Storage::disk('s3')->getMetadata($this->media->path);
            //     if ($s3Metadata['Metadata']['media_checksum'] !== $this->media->checksum) {
            //         Log::error('SyncWithS3: Media checksum mismatch');
            //         return false;
            //     }

            if (!$this->updateDatabase()) {
                Log::error('SyncWithS3: Media database update failed');
                return false;
            }

            Log::info('SyncWithS3: Checksum match, database updated');
            return true;
        }

        $file = Storage::disk('public')->get($this->media->path);
        if ($file === false || $file === null) {
            Log::error('SyncWithS3: Media not found on local storage');
            return false;
        }

        Log::info('SyncWithS3: Syncing ' . $this->media->id);

        $s3Available = Storage::disk('s3')->put($this->media->path, $file, [
            'ContentType' => $this->media->mime,
            'ContentDisposition' => 'inline; filename="' . $this->media->hash . '.' . $this->media->extension . '"',
            'CacheControl' => 'max-age=31536000, public',
            'Metadata' => [
                'media_id' => $this->media->id,
                'media_checksum' => $this->media->checksum,
                'media_type' => $this->media->type,
            ],
            'ACL' => 'public-read',
        ]);

        if ($s3Available === false) {
            Log::error('SyncWithS3: Media not synced');
            return false;
        }

        if (!$this->updateDatabase()) {
            Log::info('SyncWithS3: Media synced, but failed to update local database');
            return false;
        }

        Log::info('SyncWithS3: Media synced successfully.');
        return true;
    }
}
