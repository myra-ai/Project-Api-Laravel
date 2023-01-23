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
        Schema::create('livestream_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('stream_id')->nullable()->default(null)->index();
            $table->foreign('stream_id')->references('id')->on('livestreams')->onDelete('cascade');
            $table->string('path')->nullable();
            $table->string('policy')->default('public');
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
        Schema::dropIfExists('livestream_assets');
    }
};
