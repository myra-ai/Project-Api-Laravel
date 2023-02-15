<?php

namespace Tests\Unit;

use App\Jobs\ImageResizer;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\TestCase;

class ImageResize extends TestCase
{
    /**
     * A basic unit test example.
     *
     * @return void
     */
    public function testImageResize()
    {
        Queue::fake();

        ImageResizer::dispatch('1c62bd56-1011-4766-beaa-08cec87979e9', 100, 100);

        Queue::assertPushed(ImageResizer::class, function ($job) {
            return $job->media_id === '1c62bd56-1011-4766-beaa-08cec87979e9';
        });
    }
}
