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
        Schema::create('livestream_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->index();
            $table->foreign('company_id')->references('id')->on('livestream_companies')->onDelete('cascade');
            $table->char('primary_color', 8)->nullable()->default(null);
            $table->char('cta_color', 8)->nullable()->default(null);
            $table->char('accent_colors', 8)->nullable()->default(null);
            $table->char('text_chat_color', 8)->nullable()->default(null);
            $table->longText('notification_text', 12)->nullable()->default(null);
            $table->string('notification_email', 255)->nullable()->default(null)->index();
            $table->string('rtmp_key', 80)->nullable()->default(null);
            $table->uuid('avatar')->nullable()->default(null)->index();
            $table->foreign('avatar')->references('id')->on('livestream_medias')->onDelete('cascade');
            $table->uuid('logo')->nullable()->default(null)->index();
            $table->foreign('logo')->references('id')->on('livestream_medias')->onDelete('cascade');
            $table->boolean('stories_is_embedded')->default(true);
            $table->boolean('livestream_autoopen')->default(false);
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
        Schema::dropIfExists('livestream_settings');
    }
};
