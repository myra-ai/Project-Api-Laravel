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
        Schema::create('stories_swipe', function (Blueprint $table) {
            $table->id();
            $table->uuid('story_id')->nullable()->default(null)->index();
            $table->foreign('story_id')->references('id')->on('stories')->onDelete('cascade');
            $table->string('name', 255)->nullable()->default(null);
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
        Schema::dropIfExists('stories_swipe');
    }
};
