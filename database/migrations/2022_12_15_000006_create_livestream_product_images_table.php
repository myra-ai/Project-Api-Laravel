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
        Schema::create('livestream_product_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->nullable()->default(null)->index();
            $table->foreign('product_id')->references('id')->on('livestreams_products')->onDelete('cascade');
            $table->uuid('media_id')->nullable()->default(null)->index();
            $table->foreign('media_id')->references('id')->on('livestream_medias')->onDelete('cascade');
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
        Schema::dropIfExists('livestream_product_images');
    }
};
