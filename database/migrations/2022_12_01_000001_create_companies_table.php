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
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('address', 255)->nullable()->default(null);
            $table->string('city', 80)->nullable()->default(null);
            $table->string('state', 80)->nullable()->default(null);
            $table->string('zip', 80)->nullable()->default(null);
            $table->string('country', 80)->nullable()->default(null);
            $table->char('primary_color', 8)->nullable()->default(null);
            $table->char('cta_color', 8)->nullable()->default(null);
            $table->char('accent_color', 8)->nullable()->default(null);
            $table->char('text_chat_color', 8)->nullable()->default(null);
            $table->string('rtmp_key', 80)->nullable()->default(null);
            $table->uuid('avatar')->nullable()->default(null)->index();
            $table->foreign('avatar')->references('id')->on('medias')->onDelete('cascade');
            $table->uuid('logo')->nullable()->default(null)->index();
            $table->foreign('logo')->references('id')->on('medias')->onDelete('cascade');
            $table->integer('font')->unsigned()->default(1);
            $table->boolean('stories_is_embedded')->default(true);
            $table->boolean('livestream_autoopen')->default(false);
            $table->timestamp('deleted_at', 6)->nullable();
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
        Schema::dropIfExists('companies');
    }
};
