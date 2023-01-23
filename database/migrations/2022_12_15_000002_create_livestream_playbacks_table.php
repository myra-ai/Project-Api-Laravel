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
        Schema::create('livestream_playbacks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('livestream_id')->nullable()->default(null)->index();
            $table->foreign('livestream_id')->references('id')->on('livestreams')->onDelete('cascade');
            $table->string('policy', 16)->default('public');
            $table->string('type', 8)->nullable()->default(null);
            $table->string('format', 8)->nullable()->default(null);
            $table->string('resolution', 12)->nullable()->default(null);
            $table->string('frame_rate', 8)->nullable()->default(null);
            $table->string('bit_rate', 8)->nullable()->default(null);
            $table->string('audio_codec', 60)->nullable()->default(null);
            $table->string('video_codec', 60)->nullable()->default(null);
            $table->string('audio_bit_rate', 16)->nullable()->default(null);
            $table->string('video_bit_rate', 16)->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('livestream_playbacks');
    }
};
