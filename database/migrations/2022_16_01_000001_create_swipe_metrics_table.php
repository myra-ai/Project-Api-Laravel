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
        Schema::create('swipe_metrics', function (Blueprint $table) {
            $table->id();
            $table->uuid('swipe_id')->index();
            $table->foreign('swipe_id')->references('id')->on('swipes')->onDelete('cascade');
            $table->timestamp('created_at', 6)->useCurrent();
            $table->string('ip', 128)->nullable()->default(null)->index();
            $table->string('country', 80)->nullable()->default(null)->index();
            $table->string('city', 60)->nullable()->default(null)->index();
            $table->string('region', 60)->nullable()->default(null)->index();
            $table->longText('user_agent')->nullable()->default(null)->index();
            $table->string('device', 80)->nullable()->default(null)->index();
            $table->string('os', 60)->nullable()->default(null)->index();
            $table->string('browser', 128)->nullable()->default(null)->index();
            $table->tinyInteger('load')->unsigned()->default(0)->index();
            $table->tinyInteger('click')->unsigned()->default(0)->index();
            $table->tinyInteger('like')->unsigned()->default(0)->index();
            $table->tinyInteger('unlike')->unsigned()->default(0)->index();
            $table->tinyInteger('dislike')->unsigned()->default(0)->index();
            $table->tinyInteger('undislike')->unsigned()->default(0)->index();
            $table->tinyInteger('view')->unsigned()->default(0)->index();
            $table->tinyInteger('share')->unsigned()->default(0)->index();
            $table->tinyInteger('comment')->unsigned()->default(0)->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('swipe_metrics');
    }
};
