<?php

namespace App\Http\StreamServices\Mux;

class Metrics extends Handler
{
    public static function getMetrics(string $live_id)
    {
        $metrics = self::request()->get('/video/v1/live-streams/' . $live_id . '/metrics')->object();

        if (isset($metrics->data)) {
            $metrics = $metrics->data;
        }

        return $metrics;
    }
}
