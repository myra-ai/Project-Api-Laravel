<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Storage::directoryMissing('public/images')) {
            Storage::makeDirectory('public/images');
            Log::info('Created directory: public/images');
        }

        if (Storage::directoryMissing('public/images/thumbnails')) {
            Storage::makeDirectory('public/images/thumbnails');
            Log::info('Created directory: public/images/thumbnails');
        }

        if (Storage::directoryMissing('public/videos')) {
            Storage::makeDirectory('public/videos');
            Log::info('Created directory: public/videos');
        }

        if (Storage::directoryMissing('public/unknown')) {
            Storage::makeDirectory('public/unknown');
            Log::info('Created directory: public/unknown');
        }

        Schema::create('livestream_medias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable()->default(null)->index();
            $table->uuid('checksum')->unique();
            $table->string('original_name', 255)->nullable()->default(null);
            $table->string('hash', 128)->nullable()->default(null);
            $table->text('original_url')->nullable()->default(null);
            $table->string('path')->nullable()->default(null)->index();
            $table->timestamp('s3_available', 6)->nullable()->default(null)->comment('When value is null, the file is not available on S3');
            $table->string('policy', 16)->nullable()->default('public');
            $table->smallInteger('type', false, true)->default(0)->comment('0: unknown, 1: image, 2: thumbnail, 3: avatar, 4: logo, 5: video, 6: audio, 7: document, 8: other');
            $table->boolean('is_blurred')->default(false)->index();
            $table->boolean('is_resized')->default(false)->index();
            $table->string('mime', 16)->nullable()->default(null);
            $table->string('extension', 10)->nullable()->default(null);
            $table->bigInteger('size')->nullable()->default(null)->index();
            $table->integer('width')->nullable()->default(null)->index();
            $table->integer('height')->nullable()->default(null)->index();
            $table->float('duration')->nullable()->default(null)->index();
            $table->float('bitrate')->nullable()->default(null);
            $table->float('framerate')->nullable()->default(null);
            $table->string('channels')->nullable()->default(null);
            $table->integer('quality')->nullable()->default(null)->index();
            $table->string('alt', 255)->nullable()->default(null);
            $table->text('legend')->nullable()->default(null);
            $table->timestamp('deleted_at', 6)->nullable()->default(null);
            $table->timestamps(6);
        });

        Schema::table('livestream_medias', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('livestream_medias')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('livestream_medias');
    }
};
