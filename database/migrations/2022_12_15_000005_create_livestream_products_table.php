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
        $product_max_title_length = (int) config('app.product_max_title_length', 100);
        $product_max_title_length = $product_max_title_length * 2 > 255 ? 255 : $product_max_title_length;

        Schema::create('livestreams_products', function (Blueprint $table) use ($product_max_title_length) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->index();
            $table->foreign('company_id')->references('id')->on('livestream_companies')->onDelete('cascade');
            $table->string('title', $product_max_title_length);
            $table->longText('description')->nullable()->default(null);
            $table->float('price', 8, 2, true)->default(0);
            $table->string('currency', 3)->default('BRL');
            $table->integer('status')->default(1);
            $table->uuid('link_id')->nullable()->default(null)->index();
            $table->foreign('link_id')->references('id')->on('links')->onDelete('cascade');
            $table->boolean('promoted')->default(false);
            $table->bigInteger('views')->default(0);
            $table->bigInteger('clicks')->default(0);
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
        Schema::dropIfExists('livestreams_products');
    }
};
