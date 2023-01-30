<?php

namespace App\Console\Commands\Metrics;

use App\Models\WebLogs as mWebLogs;
use Browser;
use GeoIp2\Database\Reader;
use Illuminate\Console\Command;

class DeployLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:metrics/deploy_logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read logs from /var/log/nginx/access.log and insert into database';

    private $path = '/var/log/nginx/';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Read all logs from /var/log/nginx/*.logs (With stream chunking at 8mb)
        // Insert into database
        // Remove logs readed from /var/log/nginx/*.logs
        // Return success

        // List log files in /var/log/nginx/
        $list = scandir($this->path);
        foreach ($list as $file) {
            if (substr($file, -4) === '.log') {
                $this->info('Reading file: ' . $file);
                $this->readLogs($this->path . $file);
            }
        }
        return Command::SUCCESS;
    }

    private function readLogs($file)
    {
        $handle = fopen($file, 'r');
        if ($handle) {
            $size_read = 0;
            $current_line = -1;
            while (($line = fgets($handle)) !== false) {
                $current_line++;
                $size_read += strlen($line);

                if (empty($line)) {
                    $this->error('[' . $current_line . ']: Skipped empty');
                    continue;
                }

                $line = str_replace("\0", '', $line);

                if (mb_detect_encoding($line, 'UTF-8', true) === false) {
                    $line = mb_convert_encoding($line, 'UTF-8');
                }

                $log = @json_decode($line) ?? null;
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error('[' . $current_line . ']: Skipped with error: ' . json_last_error_msg());
                    continue;
                }

                if ($log === null) {
                    $this->error('[' . $current_line . ']:  Skipped empty');
                    continue;
                }

                $log = (array) $log;
                $log = array_combine(
                    array_map(function ($key) {
                        $key = str_replace(['-', ' '], '_', $key);
                        $key = strtolower($key);
                        $key = trim($key);
                        return $key;
                    }, array_keys($log)),
                    array_values($log)
                );

                $weblog = new mWebLogs();

                foreach ($log as $key => $value) {
                    switch ($key) {
                        case 'time':
                            $value = \DateTime::createFromFormat('U.u', $value);
                            $value->setTimezone(new \DateTimeZone('America/Sao_Paulo'));
                            $weblog->request_date = $value->format('Y-m-d H:i:s.u');
                            break;
                        case 'version':
                            if (str_contains($value, '/')) {
                                $value = explode('/', $value);
                                $weblog->software_name = $value[0];
                                $weblog->software_version = $value[1];
                            } else {
                                $weblog->software_name = $value;
                            }
                            break;
                        case 'uuid':
                            $weblog->id = $value;
                            break;
                        case 'protocol':
                            $weblog->request_protocol = match (strtoupper($value)) {
                                'HTTP/1.0' => 'HTTP/1.0',
                                'HTTP/1.1' => 'HTTP/1.1',
                                'HTTP/2.0' => 'HTTP/2',
                                'HTTP/2' => 'HTTP/2',
                                'HTTP/3.0' => 'HTTP/3',
                                'HTTP/3' => 'HTTP/3',
                                default => $value,
                            };
                            break;
                        case 'method':
                            $weblog->request_method = match (strtoupper($value)) {
                                'GET' => 'GET',
                                'POST' => 'POST',
                                'PUT' => 'PUT',
                                'DELETE' => 'DELETE',
                                'HEAD' => 'HEAD',
                                'OPTIONS' => 'OPTIONS',
                                'PATCH' => 'PATCH',
                                'TRACE' => 'TRACE',
                                'CONNECT' => 'CONNECT',
                                default => $value,
                            };
                            break;
                        case 'scheme':
                            $weblog->request_scheme = match (strtoupper($value)) {
                                'HTTP' => 'HTTP',
                                'HTTPS' => 'HTTPS',
                                default => $value,
                            };
                            break;
                        case 'domain':
                            $weblog->request_host = strtolower($value);
                            break;
                        case 'port':
                            $weblog->request_port = $value;
                            break;
                        case 'uri':
                            $weblog->request_path = $value;
                            break;
                        case 'query':
                            $weblog->request_query = $value;
                            break;
                        case 'status':
                            $weblog->response_status = $value;
                            break;
                        case 'completed':
                            $weblog->request_completion = $value;
                            break;
                        case 'body_length':
                            $weblog->content_size = (int) $value;
                            break;
                        case 'bytes_out':
                            $weblog->response_size = (int) $value;
                            break;
                        case 'bytes_in':
                            $weblog->request_size = (int) $value;
                            break;
                        case 'request_time':
                            $weblog->request_time = (float) $value ?? null;
                            break;
                        case 'connection_time':
                            $weblog->connection_time = (float) $value ?? null;
                            break;
                        case 'ip':
                            $weblog->request_ip = null;
                            if (!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
                                break;
                            }
                            if (empty($value) || $value === null) {
                                break;
                            }
                            $weblog->request_ip = $value;

                            try {
                                $city = new Reader('/usr/share/GeoIP2/GeoLite2-City.mmdb');
                                $weblog->request_ip_city = $city->city($value)->city->name ?? null;
                                $weblog->request_ip_region = $city->city($value)->mostSpecificSubdivision->name ?? null;
                                $weblog->request_ip_region_code = $city->city($value)->mostSpecificSubdivision->isoCode ?? null;

                                $weblog->request_ip_lat = $city->city($value)->location->latitude ?? null;
                                $weblog->request_ip_lon = $city->city($value)->location->longitude ?? null;
                                $weblog->request_ip_timezone = $city->city($value)->location->timeZone ?? null;
                            } catch (\Exception $e) {
                                // Ignore
                            }

                            try {
                                $country = new Reader('/usr/share/GeoIP2/GeoLite2-Country.mmdb');
                                $weblog->request_ip_country = $country->country($value)->country->name ?? null;
                                $weblog->request_ip_country_code = $country->country($value)->country->isoCode ?? null;
                                $weblog->request_ip_continent = $country->country($value)->continent->name ?? null;
                                $weblog->request_ip_continent_code = $country->country($value)->continent->code ?? null;
                                $weblog->request_ip_currency = $country->country($value)->country->currency ?? null;
                            } catch (\Exception $e) {
                                // Ignore
                            }

                            try {
                                $asn = new Reader('/usr/share/GeoIP2/GeoLite2-ASN.mmdb');
                                $weblog->request_ip_asn = $asn->asn($value)->autonomousSystemNumber ?? null;
                                $weblog->request_ip_org = $asn->asn($value)->autonomousSystemOrganization ?? null;
                            } catch (\Exception $e) {
                                // Ignore
                            }

                            break;
                        case 'agent':
                            $weblog->request_agent = $value;
                            try {
                                $browser = Browser::parse($value);
                                $weblog->request_agent_browser = $browser->browserFamily();
                                $weblog->request_agent_browser_version = $browser->browserVersion();
                                $weblog->request_agent_browser_platform = $browser->platformFamily();
                                $weblog->request_agent_browser_platform_version = $browser->platformVersion();
                                $weblog->request_agent_browser_device = $browser->deviceFamily();
                                $weblog->request_agent_browser_device_model = $browser->deviceModel();
                                $weblog->request_agent_browser_device_grade = $browser->mobileGrade();
                            } catch (\Exception $e) {
                                // Ignore
                            }
                            break;
                        case 'referer':
                            $weblog->request_referer = $value;
                            break;
                        case 'ssl_cipher':
                            $weblog->request_ssl_cipher = $value;
                            break;
                        case 'ssl_protocol':
                            $weblog->request_ssl_protocol = $value;
                            break;
                        case 'origin':
                            $weblog->origin = $value;
                            break;
                        default:
                            break;
                    }
                }

                try {
                    $id = $weblog->id;
                    $weblog->save();
                    $this->info('[' . $current_line . ']: Inserting log: ' . $id);
                } catch (\Exception $e) {
                    if ($e->getCode() === '23000') {
                        $this->warn('[' . $current_line . ']: Already inserted: ' . $id);
                        continue;
                    } else {
                        $this->warn('[' . $current_line . ']: Error inserting log [' . $id . ']: ' . $e->getMessage());
                    }
                }
            }

            fclose($handle);
        } else {
            $this->error('Error opening file: ' . $file);
        }
    }
}
