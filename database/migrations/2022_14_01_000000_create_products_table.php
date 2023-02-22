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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->index();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->string('title', 255)->index();
            $table->text('description')->nullable()->default(null);
            $table->float('price', 8, 2, true)->default(0)->index();
            $table->string('currency', 3)->default('BRL');
            $table->smallInteger('status', unsigned: true)->default(1)->index();
            $table->uuid('link_id')->nullable()->default(null)->index();
            $table->foreign('link_id')->references('id')->on('links')->onDelete('cascade');
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
        Schema::dropIfExists('products');
    }
};
