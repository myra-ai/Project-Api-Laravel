<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class WebLogs extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'web_logs';
    protected $primaryKey = 'id';
    public $timestamps = false;
    public $connection = 'web_logs';
    protected $dateFormat = 'Y-m-d H:i:s.u';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'request_date',
        'software_name',
        'software_version',
        'origin',
        'request_protocol',
        'request_method',
        'request_scheme',
        'request_host',
        'request_port',
        'request_path',
        'request_query',
        'response_status',
        'request_time',
        'connection_time',
        'request_completion',
        'cache_status',
        'content_size',
        'content_type',
        'content_length',
        'response_size',
        'request_size',
        'request_referer',
        'request_ssl_cipher',
        'request_ssl_protocol',
        'request_ip',
        'request_ip_city',
        'request_ip_region',
        'request_ip_region_code',
        'request_ip_country',
        'request_ip_country_code',
        'request_ip_continent',
        'request_ip_continent_code',
        'request_ip_lat',
        'request_ip_lon',
        'request_ip_timezone',
        'request_ip_currency',
        'request_ip_asn',
        'request_ip_org',
        'request_agent',
        'request_agent_browser',
        'request_agent_browser_version',
        'request_agent_browser_platform',
        'request_agent_browser_platform_version',
        'request_agent_browser_device',
        'request_agent_browser_device_model',
        'request_agent_browser_device_grade',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'request_date' => 'timestamp',
        'software_name' => 'string',
        'software_version' => 'string',
        'origin' => 'string',
        'request_protocol' => 'string',
        'request_method' => 'string',
        'request_scheme' => 'string',
        'request_host' => 'string',
        'request_port' => 'integer',
        'request_path' => 'string',
        'request_query' => 'string',
        'response_status' => 'integer',
        'request_time' => 'float',
        'connection_time' => 'float',
        'request_completion' => 'string',
        'cache_status' => 'string',
        'content_size' => 'string',
        'content_type' => 'string',
        'content_length' => 'integer',
        'response_size' => 'integer',
        'request_size' => 'integer',
        'request_referer' => 'string',
        'request_ssl_cipher' => 'string',
        'request_ssl_protocol' => 'string',
        'request_ip' => 'string',
        'request_ip_city' => 'string',
        'request_ip_region' => 'string',
        'request_ip_region_code' => 'string',
        'request_ip_country' => 'string',
        'request_ip_country_code' => 'string',
        'request_ip_continent' => 'string',
        'request_ip_continent_code' => 'string',
        'request_ip_lat' => 'decimal:10',
        'request_ip_lon' => 'decimal:10',
        'request_ip_timezone' => 'string',
        'request_ip_currency' => 'string',
        'request_ip_asn' => 'string',
        'request_ip_org' => 'string',
        'request_agent' => 'string',
        'request_agent_browser' => 'string',
        'request_agent_browser_version' => 'string',
        'request_agent_browser_platform' => 'string',
        'request_agent_browser_platform_version' => 'string',
        'request_agent_browser_device' => 'string',
        'request_agent_browser_device_model' => 'string',
        'request_agent_browser_device_grade' => 'string',
    ];
}
