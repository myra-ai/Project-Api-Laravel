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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable()->default(null)->index();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->json('permissions')->nullable()->default(null);
            $table->integer('role')->default(0)->index();
            $table->string('name', 110)->index();
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->timestamp('email_verified_at', 6)->nullable()->default(null)->index();
            $table->string('phone_country', 2)->default('BR')->index();
            $table->string('phone_country_dial', 5)->nullable()->default(null)->index();
            $table->string('phone', 80)->nullable()->default(null)->unique();
            $table->uuid('avatar')->nullable()->default(null)->index();
            $table->foreign('avatar')->references('id')->on('medias')->onDelete('cascade');
            $table->string('address', 255)->nullable()->default(null);
            $table->string('city', 80)->nullable()->default(null);
            $table->string('state', 80)->nullable()->default(null);
            $table->string('zip', 80)->nullable()->default(null);
            $table->string('country', 80)->nullable()->default(null);
            $table->timestamp('last_login', 6)->nullable()->default(null);
            $table->string('last_login_ip', 255)->nullable()->default(null)->index();
            $table->boolean('is_master')->default(false);
            $table->timestamp('deleted_at', 6)->nullable()->default(null)->index();
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
        Schema::dropIfExists('users');
    }
};
