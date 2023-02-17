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
use Illuminate\Support\Facades\Storage;

class MediaResizer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(public string $media_id, public int $width, public int $height, public string $mode, public bool $keep_aspect_radio, public ?int $quality = null, public ?bool $blur = null, public ?string $format = null)
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

        if (!Storage::disk('public')->exists($media->path)) {
            if (!Storage::disk('s3')->exists($media->path)) {
                Log::error('MediaResizer: Media not found on S3 and not in local storage');
                return;
            }
            Storage::disk('public')->put($media->path, Storage::disk('s3')->get($media->path));
            Log::info('MediaResizer: Media found on S3 and downloaded to local storage');
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

        $media->quality = is_numeric($media->quality) ? $media->quality : 80;
        $media->quality = $media->quality < 50 ? 50 : $media->quality;
        $media->quality = $media->quality > 100 ? 100 : $media->quality;

        $format = $this->format !== null ? $this->format : $media->extension;
        $quality = $this->quality !== null ? $this->quality : $media->quality;
        $media->extension = $format;
        $media->quality = $quality;
        $media->mime = API::getMimeByExtension($format);

        $base_path = storage_path('app/public/' . $media->path);
        $file_name = $media->file_name . '_' . $this->width . 'x' . $this->height;
        $path = API::mediaPathType($media->type) . $file_name . '.' . $format;
        $resized_path = storage_path('app/public/' . $path);

        $image = Image::make($base_path);
        $image->resize($this->width, $this->height, function ($constraint) {
            if ($this->keep_aspect_radio) {
                $constraint->aspectRatio();
            }
            $constraint->upsize();
        });



        if ($image->save($resized_path, $quality, $format)) {
            $checksum = API::getMediaChecksum($resized_path, $media->type);
            $resized_id = Str::uuid()->toString();

            mLiveStreamMedias::create([
                'id' => $resized_id,
                'company_id' => $media->company_id,
                'parent_id' => $media->id,
                'checksum' => $checksum,
                'original_name' => null,
                'file_name' => $file_name,
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
