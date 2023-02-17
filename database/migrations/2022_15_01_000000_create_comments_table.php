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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->uuid('stream_id')->nullable()->default(null)->index();
            $table->foreign('stream_id')->references('id')->on('livestreams')->onDelete('cascade');
            $table->uuid('story_id')->nullable()->default(null)->index();
            $table->foreign('story_id')->references('id')->on('stories')->onDelete('cascade');
            $table->text('text');
            $table->string('name', 255);
            $table->string('email', 255)->index();
            $table->bigInteger('parent_id')->unsigned()->nullable()->index();
            $table->boolean('pinned')->default(false);
            $table->boolean('is_streammer')->default(false);
            $table->bigInteger('likes')->unsigned()->default(0);
            $table->bigInteger('dislikes')->unsigned()->default(0);
            $table->bigInteger('shares')->unsigned()->default(0);
            $table->timestamp('modified_at', 6)->nullable()->default(null);
            $table->timestamp('deleted_at', 6)->nullable()->default(null);
            $table->timestamps(6);
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('comments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('comments');
    }
};
