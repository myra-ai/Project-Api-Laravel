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
        Schema::create('links_metrics', function (Blueprint $table) {
            $table->id();
            $table->uuid('link_id')->index();
            $table->foreign('link_id')->references('id')->on('links')->onDelete('cascade');
            $table->timestamp('created_at', 6)->useCurrent()->index();
            $table->string('ip', 128)->nullable()->default(null)->index();
            $table->string('region', 80)->nullable()->default(null)->index();
            $table->string('state', 60)->nullable()->default(null)->index();
            $table->string('country', 60)->nullable()->default(null)->index();
            $table->text('user_agent')->nullable()->default(null)->index();
            $table->string('device', 80)->nullable()->default(null)->index();
            $table->string('os', 60)->nullable()->default(null)->index();
            $table->string('browser', 128)->nullable()->default(null)->index();
            $table->integer('load')->default(0)->index();
            $table->integer('click')->default(0)->index();
            $table->integer('view')->default(0)->index();
            $table->integer('share')->default(0)->index();
            $table->integer('comment')->default(0)->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('links_metrics');
    }
};
