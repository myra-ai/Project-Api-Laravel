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
        Schema::create('swipe_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('swipe_id')->nullable()->default(null)->index();
            $table->foreign('swipe_id')->references('id')->on('swipes')->onDelete('cascade');
            $table->uuid('story_id')->nullable()->default(null)->index();
            $table->foreign('story_id')->references('id')->on('stories')->onDelete('cascade');
            $table->timestamps(6);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('swipe_groups');
    }
};
