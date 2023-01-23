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
        $stream_max_title_length = (int) config('app.stream_title_max_length', 100);
        $stream_max_title_length = $stream_max_title_length * 2 > 255 ? 255 : $stream_max_title_length;

        Schema::create('livestreams', function (Blueprint $table) use ($stream_max_title_length) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->index();
            $table->foreign('company_id')->references('id')->on('livestream_companies')->onDelete('cascade');
            $table->string('title', $stream_max_title_length)->nullable()->default(null);
            $table->timestamp('sheduled_at')->nullable()->default(null);
            $table->uuid('thumbnail_id')->nullable()->default(null)->index();
            $table->foreign('thumbnail_id')->references('id')->on('livestream_medias')->onDelete('cascade');
            $table->string('live_id', 96)->index();
            $table->string('stream_key', 64)->index();
            $table->string('latency_mode')->default('low');
            $table->boolean('audio_only')->default(false);
            $table->string('orientation')->default('landscape')->index();
            $table->string('status', 16)->default('idle')->index();
            $table->bigInteger('duration')->default(0);
            $table->bigInteger('viewers')->unsigned()->default(0)->index();
            $table->bigInteger('likes')->unsigned()->default(0)->index();
            $table->bigInteger('dislikes')->unsigned()->default(0);
            $table->bigInteger('comments')->unsigned()->default(0);
            $table->bigInteger('shares')->unsigned()->default(0);
            $table->bigInteger('widget_views')->unsigned()->default(0)->index();
            $table->bigInteger('widget_clicks')->unsigned()->default(0)->index();
            $table->bigInteger('max_duration')->default(43200);
            $table->longText('note')->nullable()->default(null);
            $table->timestamp('deleted_at', 6)->nullable()->default(null);
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
        Schema::dropIfExists('livestreams');
    }
};
