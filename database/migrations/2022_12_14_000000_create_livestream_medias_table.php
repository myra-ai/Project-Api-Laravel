<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('livestream_medias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable()->default(null)->index();
            $table->uuid('checksum')->unique();
            $table->string('original_name', 255)->nullable()->default(null);
            $table->longText('original_url')->nullable()->default(null);
            $table->string('path')->nullable()->default(null)->index();
            $table->timestamp('s3_available', 6)->nullable()->default(null)->comment('When value is null, the file is not available on S3');
            $table->string('s3_path')->nullable()->default(null)->index();
            $table->string('policy', 16)->default('public');
            $table->smallInteger('type', false, true)->default(0)->comment('0: unknown, 1: image, 2: image thumbnail, 3: video, 4: audio, 5: document, 6: archive');
            $table->boolean('is_blurred')->default(false);
            $table->string('mime', 16)->nullable()->default(null);
            $table->string('extension', 10)->nullable()->default(null);
            $table->bigInteger('size')->nullable()->default(null);
            $table->integer('width')->nullable()->default(null);
            $table->integer('height')->nullable()->default(null);
            $table->integer('duration')->nullable()->default(null);
            $table->string('title', 80)->nullable()->default(null);
            $table->longText('description')->nullable()->default(null);
            $table->string('alt', 255)->nullable()->default(null);
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
