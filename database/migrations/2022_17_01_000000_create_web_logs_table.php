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
        Schema::connection('web_logs')->create('web_logs', function (Blueprint $table) {
            $table->char('id', 80)->primary();
            $table->timestamp('request_date', 6)->index();
            $table->string('software_name', 32);
            $table->string('software_version', 16);
            $table->string('origin', 16)->index();
            $table->string('request_protocol', 16)->index();
            $table->string('request_method', 8)->index();
            $table->string('request_scheme', 8);
            $table->string('request_host', 64)->index();
            $table->integer('request_port');
            $table->text('request_path')->nullable()->default(null);
            $table->text('request_query')->nullable()->default(null);
            $table->integer('response_status')->nullable()->default(null)->index();
            $table->float('request_time')->nullable()->default(null)->index();
            $table->float('connection_time')->nullable()->default(null)->index();
            $table->string('request_completion', 8)->nullable()->default(null)->index();
            $table->string('cache_status', 8)->nullable()->default(null)->index();
            $table->integer('content_size')->default(0);
            $table->string('content_type', 128)->nullable()->default(null)->index();
            $table->integer('content_length')->default(0)->index();
            $table->bigInteger('response_size')->default(0)->index();
            $table->bigInteger('request_size')->default(0)->index();
            $table->text('request_referer')->nullable()->default(null)->index();
            $table->string('request_ssl_cipher', 128)->nullable()->default(null)->index();
            $table->string('request_ssl_protocol', 96)->nullable()->default(null)->index();
            $table->string('request_ip', 64)->nullable()->default(null)->index();
            $table->string('request_ip_city', 96)->nullable()->default(null)->index();
            $table->string('request_ip_region', 64)->nullable()->default(null);
            $table->string('request_ip_region_code', 3)->nullable()->default(null);
            $table->string('request_ip_country', 64)->nullable()->default(null)->index();
            $table->string('request_ip_country_code', 3)->nullable()->default(null);
            $table->string('request_ip_continent', 64)->nullable()->default(null)->index();
            $table->string('request_ip_continent_code', 3)->nullable()->default(null);
            $table->decimal('request_ip_lat', 10, 7)->nullable()->default(null)->index();
            $table->decimal('request_ip_lon', 10, 7)->nullable()->default(null)->index();
            $table->string('request_ip_timezone', 24)->nullable()->default(null);
            $table->string('request_ip_currency', 8)->nullable()->default(null);
            $table->string('request_ip_asn', 32)->nullable()->default(null)->index();
            $table->string('request_ip_org', 128)->nullable()->default(null)->index();
            $table->text('request_agent')->nullable()->default(null);
            $table->string('request_agent_browser')->nullable()->default(null)->index();
            $table->string('request_agent_browser_version')->nullable()->default(null);
            $table->string('request_agent_browser_platform')->nullable()->default(null)->index();
            $table->string('request_agent_browser_platform_version')->nullable()->default(null);
            $table->string('request_agent_browser_device')->nullable()->default(null)->index();
            $table->string('request_agent_browser_device_model')->nullable()->default(null);
            $table->string('request_agent_browser_device_grade')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('web_logs')->dropIfExists('web_logs');
    }
};
