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
        Schema::create('livestream_companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreign('tenant_id')->references('id')->on('tenant')->onDelete('cascade');
            $table->string('name', 100);
            $table->string('email', 80)->nullable()->default(null);
            $table->string('password', 255)->nullable()->default(null);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('token', 255)->nullable()->default(null);
            $table->string('phone', 80)->nullable()->default(null);
            $table->string('address', 255)->nullable()->default(null);
            $table->string('city', 80)->nullable()->default(null);
            $table->string('state', 80)->nullable()->default(null);
            $table->string('zip', 80)->nullable()->default(null);
            $table->string('country', 80)->nullable()->default(null);
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
        Schema::dropIfExists('livestream_companies');
    }
};
