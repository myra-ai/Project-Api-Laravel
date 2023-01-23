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
        Schema::create('livestream_product_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->nullable()->default(null)->index();
            $table->foreign('product_id')->references('id')->on('livestreams_products')->onDelete('cascade');
            $table->uuid('stream_id')->nullable()->default(null)->index();
            $table->foreign('stream_id')->references('id')->on('livestreams')->onDelete('cascade');
            $table->uuid('story_id')->nullable()->default(null)->index();
            $table->foreign('story_id')->references('id')->on('stories')->onDelete('cascade');
            $table->string('title', 255)->nullable()->default(null);
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
        Schema::dropIfExists('livestream_product_groups');
    }
};
