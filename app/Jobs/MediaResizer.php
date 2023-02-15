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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class MediaResizer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(public string $media_id, public int $width, public int $height, public string $mode, public bool $keep_aspect_radio, public int $quality, public bool $blur)
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $media = mLiveStreamMedias::where('id', '=', $this->media_id)->first();
        if ($media === null) {
            Log::error('MediaResizer: Media not found');
            return;
        }
        $resized = mLiveStreamMedias::where('parent_id', '=', $media->id)
            ->where('width', '=', $this->width)
            ->where('height', '=', $this->height)
            ->first();
        if ($resized !== null) {
            if ($resized->width === $this->width && $resized->height === $this->height) {
                Log::info('MediaResizer: Media already resized');
                return;
            }
        }

        Log::info('MediaResizer: Resizing ' . $media->id);

        $base_path = storage_path('app/public/' . $media->path);
        $path = API::mediaPathType($media->type) . $media->hash . '_' . $this->width . 'x' . $this->height . '.' . $media->extension;
        $resized_path = storage_path('app/public/' . $path);

        $image = Image::make($base_path);
        $image->resize($this->width, $this->height, function ($constraint) {
            if ($this->keep_aspect_radio) {
                $constraint->aspectRatio();
            }
            $constraint->upsize();
        });

        $media->quality = is_numeric($media->quality) ? $media->quality : 80;
        $media->quality = $media->quality < 50 ? 50 : $media->quality;
        $media->quality = $media->quality > 100 ? 100 : $media->quality;

        if ($image->save($resized_path, $media->quality, $media->extension)) {
            $checksum = API::getFileChecksum($resized_path, $media->type === API::MEDIA_TYPE_IMAGE_THUMBNAIL ? true : false);
            $resized_id = Str::uuid()->toString();

            mLiveStreamMedias::create([
                'id' => $resized_id,
                'parent_id' => $media->id,
                'checksum' => $checksum,
                'original_name' => null,
                'hash' => null,
                'original_url' => null,
                'path' => $path,
                's3_available' => null,
                'policy' => null,
                'type' => $media->type,
                'is_blurred' => $media->is_blurred,
                'is_resized' => true,
                'mime' => $media->mime,
                'extension' => $media->extension,
                'size' => filesize($resized_path),
                'width' => $this->width,
                'height' => $this->height,
                'quality' => $media->quality,
            ]);

            SyncWithS3::dispatch($resized_id);
        }
        $image->destroy();

        return true;
    }
}
