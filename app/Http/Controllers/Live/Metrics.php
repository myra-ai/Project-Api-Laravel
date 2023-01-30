<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use App\Models\LiveStreams as mLiveStreams;
use App\Models\WebLogs as mWebLogs;
use App\Rules\strBoolean;
use App\Rules\Timestamp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Metrics extends API
{
    public function getMostAccessedIp(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'last_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'top' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        $last_days = $params['last_days'] ?? 7;
        $top = $params['top'] ?? 3;
        $date = now()->subDays($last_days)->format('Y-m-d H:i:s.000000');

        $data = mWebLogs::selectRaw('request_ip, count(*) as total')
            ->where('request_date', '>=', $date)
            ->where('request_ip', '!=', null)
            ->groupBy('request_ip')
            ->orderBy('total', 'desc')
            ->limit($top)
            ->get();

        $data = $data->map(function ($item) {
            return (object) [
                'ip' => $item->request_ip,
                'requests' => $item->total,
            ];
        });

        $r->data = $data;

        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getMostAccessedCity(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'last_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'top' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        $last_days = $params['last_days'] ?? 7;
        $top = $params['top'] ?? 3;
        $date = now()->subDays($last_days)->format('Y-m-d H:i:s.000000');

        $data = mWebLogs::selectRaw('request_ip_city, count(*) as total')
            ->where('request_date', '>=', $date)
            ->where('request_ip_city', '!=', null)
            ->groupBy('request_ip_city')
            ->orderBy('total', 'desc')
            ->limit($top)
            ->get();

        $data = $data->map(function ($item) {
            return (object) [
                'city' => $item->request_ip_city,
                'requests' => $item->total,
            ];
        });

        $r->data = $data;

        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getMostAccessedCountry(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'last_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'top' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        $last_days = $params['last_days'] ?? 7;
        $top = $params['top'] ?? 3;
        $date = now()->subDays($last_days)->format('Y-m-d H:i:s.000000');

        $data = mWebLogs::selectRaw('request_ip_country, count(*) as total')
            ->where('request_date', '>=', $date)
            ->where('request_ip_country', '!=', null)
            ->groupBy('request_ip_country')
            ->orderBy('total', 'desc')
            ->limit($top)
            ->get();

        $data = $data->map(function ($item) {
            return (object) [
                'country' => $item->request_ip_country,
                'requests' => $item->total,
            ];
        });

        $r->data = $data;

        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getMostAccessedReferer(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'last_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'top' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        $last_days = $params['last_days'] ?? 7;
        $top = $params['top'] ?? 3;
        $date = now()->subDays($last_days)->format('Y-m-d H:i:s.000000');

        $data = mWebLogs::selectRaw('request_referer, count(*) as total')
            ->where('request_date', '>=', $date)
            ->where('request_referer', '!=', null)
            ->groupBy('request_referer')
            ->orderBy('total', 'desc')
            ->limit($top)
            ->get();

        $data = $data->map(function ($item) {
            return (object) [
                'referer' => $item->request_referer,
                'requests' => $item->total,
            ];
        });

        $r->data = $data;

        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getTotalAccessByDays(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'start' => ['nullable', 'integer', 'min:0', 'max:100'],
            'end' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        $start = $params['start'] ?? 0;
        $start = now()->subDays($start)->format('Y-m-d H:i:s.000000');
        $end = $params['end'] ?? 7;
        $end = now()->subDays($end)->format('Y-m-d H:i:s.000000');

        $data = mWebLogs::selectRaw('request_date, count(*) as total')
            ->where('request_date', '>=', $end)
            ->where('request_date', '<=', $start)
            ->groupBy('request_date')
            ->orderBy('request_date', 'asc')
            ->get();

        $data = $data->map(function ($item) {
            return (object) [
                'date' => $item->request_date,
                'requests' => $item->total,
            ];
        });

        $r->data = $data;

        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getAverageAccessByDays(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'start' => ['nullable', 'integer', 'min:0', 'max:100'],
            'end' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        $start = $params['start'] ?? 0;
        $start = now()->subDays($start)->format('Y-m-d H:i:s.000000');
        $end = $params['end'] ?? 7;
        $end = now()->subDays($end)->format('Y-m-d H:i:s.000000');

        $data = mWebLogs::selectRaw('request_date, count(*) as total')
            ->where('request_date', '>=', $end)
            ->where('request_date', '<=', $start)
            ->groupBy('request_date')
            ->orderBy('request_date', 'asc')
            ->get();

        $data = $data->map(function ($item) {
            return (object) [
                'date' => $item->request_date,
                'requests' => $item->total,
            ];
        });

        $r->data = $data;

        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getMetrics(Request $request, ?string $stream_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['stream_id' => $stream_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
            return $stream;
        }

        $r->data = (object) [
            'viewers' => $stream->viewers,
            'likes' => $stream->likes,
            'dislikes' => $stream->dislikes,
            'comments' => $stream->comments,
            'widget_views' => $stream->widget_views,
            'widget_clicks' => $stream->widget_clicks,
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function streamMetricWidgetViews(Request $request, ?string $stream_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['stream_id' => $stream_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
            return $stream;
        }

        $r->data = (object) [
            'widget_views' => $stream->widget_views
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function streamAddMetricWidgetViews(Request $request, ?string $stream_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['stream_id' => $stream_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
            return $stream;
        }

        try {
            $stream->increment('widget_views');
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to add live stream widget loads.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->data = (object) [
            'widget_views' => $stream->widget_views
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function streamMetricWidgetClicks(Request $request, ?string $stream_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['stream_id' => $stream_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
            return $stream;
        }

        $r->data = (object) [
            'widget_clicks' => $stream->widget_clicks
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function streamAddMetricWidgetClicks(Request $request, ?string $stream_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['stream_id' => $stream_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
            return $stream;
        }

        try {
            $stream->increment('widget_clicks');
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to add live stream widget clicks.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->data = (object) [
            'widget_clicks' => $stream->widget_clicks
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }
}
