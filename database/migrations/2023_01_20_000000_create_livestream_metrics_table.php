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
        Schema::create('livestream_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('stream_id')->index();
            $table->foreign('stream_id')->references('id')->on('livestreams')->onDelete('cascade');
            $table->integer('count_viewers')->default(0);
            $table->integer('count_comments')->default(0);
            $table->integer('count_likes')->default(0);
            $table->integer('count_dislikes')->default(0);
            $table->integer('count_shares')->default(0);

            $table->timestamp('checked_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('livestream_metrics');
    }
};
