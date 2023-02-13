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
        Schema::create('stories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->index();
            $table->foreign('company_id')->references('id')->on('livestream_companies')->onDelete('cascade');
            $table->uuid('media_id')->nullable()->default(null)->index();
            $table->foreign('media_id')->references('id')->on('livestream_medias')->onDelete('cascade');
            $table->string('title', 255);
            $table->boolean('publish')->default(false);
            $table->string('status', 16)->default('DRAFT');
            $table->bigInteger('clicks')->unsigned()->default(0)->index();
            $table->bigInteger('comments')->unsigned()->default(0)->index();
            $table->bigInteger('dislikes')->unsigned()->default(0)->index();
            $table->bigInteger('likes')->unsigned()->default(0)->index();
            $table->bigInteger('loads')->unsigned()->default(0)->index();
            $table->bigInteger('shares')->unsigned()->default(0)->index();
            $table->bigInteger('views')->unsigned()->default(0)->index();
            $table->timestamp('deleted_at', 6)->nullable()->default(null);
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
        Schema::dropIfExists('stories');
    }
};