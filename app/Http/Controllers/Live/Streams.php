<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use App\Http\StreamServices\AntMedia\Stream as AntMediaStream;
use App\Http\StreamServices\Mux\Stream as MuxStream;
use App\Models\LiveStreams as mLiveStreams;
use App\Rules\strBoolean;
use App\Rules\Timestamp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Streams extends API
{
    public function doCreate(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid', 'exists:livestream_companies,id'],
            'title' => ['required', 'string', 'min:4', 'max:110'],
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
            'note' => ['nullable', 'string', 'min:8', 'max:1000'],
            'sheduled_at' => ['nullable', new Timestamp],
            'audio_only' => ['nullable', new strBoolean],
            'orientation' => ['nullable', 'string', 'in:landscape,portrait'],
            'latency_mode' => ['nullable', 'string', 'in:low,normal'],
            'max_duration' => ['nullable', 'integer', 'min:60', 'max:43200'],
            'thumbnail_id' => ['nullable', 'string', 'size:36', 'uuid', 'exists:livestream_medias,id'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        $params['latency_mode'] = $params['latency_mode'] ?? 'low';
        $params['note'] = $params['note'] ?? null;
        $params['sheduled_at'] = $params['sheduled_at'] ?? null;
        $params['audio_only'] = $params['audio_only'] ?? false;
        $params['orientation'] = $params['orientation'] ?? 'landscape';
        $params['max_duration'] = $params['max_duration'] ?? 43200;
        $params['thumbnail_id'] = $params['thumbnail_id'] ?? null;

        $stream_id = Str::uuid()->toString();

        if (mLiveStreams::where('id', '=', $stream_id)->exists()) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Stream ID already exists.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $live = null;

        switch (strtolower(env('STREAM_SERVICE'))) {
            case 'mux':
                $live = MuxStream::doCreateLive($stream_id);
                break;
            case 'antmedia':
                $live = AntMediaStream::doCreateLive($stream_id, $params['latency_mode'], [
                    'name' => $params['title'],
                ]);
                if ($live === null || empty($live)) {
                    $r->messages[] = (object) [
                        'type' => 'error',
                        'message' => __('Stream service returned empty response.'),
                    ];
                    return response()->json($r, Response::HTTP_BAD_REQUEST);
                }
                try {
                    $stream = mLiveStreams::create([
                        'id' => $stream_id,
                        'company_id' => $params['company_id'],
                        'live_id' => $stream_id,
                        'stream_key' => $stream_id,
                        'title' => $params['title'],
                        'note' => $params['note'],
                        'latency_mode' => $params['latency_mode'],
                        'sheduled_at' => $params['sheduled_at'],
                        'audio_only' => $params['audio_only'],
                        'orientation' => $params['orientation'],
                        'max_duration' => $params['max_duration'],
                        'thumbnail_id' => $params['thumbnail_id'],
                        'status' => 'created',
                    ]);
                } catch (\Exception $e) {
                    $message = (object) [
                        'type' => 'error',
                        'message' => __('Stream could not be created.'),
                    ];
                    if (config('app.debug')) {
                        $message->debug = $e->getMessage();
                    }
                    $r->messages[] = $message;
                    return response()->json($r, Response::HTTP_BAD_REQUEST);
                }
                break;
            default:
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('Stream service not supported.'),
                ];
                return response()->json($r, Response::HTTP_BAD_REQUEST);
                break;
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Stream created successfully.'),
        ];
        $r->data = (object) [
            'id' => $stream_id,
            'sheduled_at' => $stream->sheduled_at,
            'thumbnail' => $stream->getThumbnailDetails(),
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doUpdate(Request $request, ?string $stream_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['required', 'string', 'size:36', 'uuid'],
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
            'title' => ['nullable', 'string', 'min:4', 'max:100'],
            'note' => ['nullable', 'string', 'min:8', 'max:1000'],
            'sheduled_at' => ['nullable', new Timestamp],
            'audio_only' => ['nullable', new strBoolean],
            'orientation' => ['nullable', 'string', 'in:landscape,portrait'],
            'latency_mode' => ['nullable', 'string', 'in:low,normal'],
            'max_duration' => ['nullable', 'integer', 'min:60', 'max:43200'],
            'thumbnail' => ['nullable', 'string', 'size:36', 'uuid', 'exists:livestream_medias,id'],
        ], $request->all(), ['stream_id' => $stream_id])) instanceof JsonResponse) {
            return $params;
        }

        if (count($params) < 2) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('No data to update.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
            return $stream;
        }

        try {
            foreach ($params as $key => $value) {
                if ($key === 'stream_id') {
                    continue;
                }
                if ($value === null) {
                    continue;
                }
                $stream->{$key} = $value;
            }
            $stream->save();
        } catch (\Exception $e) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Stream could not be updated.'),
                'exception' => $e->getMessage(),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $r->data = $params;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getByStreamID(Request $request, ?string $stream_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['required', 'string', 'size:36', 'uuid'],
            'token' => ['nullable', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
        ], $request->all(), ['stream_id' => $stream_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
            return $stream;
        }

        $r->data = $stream;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getWidgetByStreamID(Request $request, ?string $stream_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['stream_id' => $stream_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
            return $stream;
        }

        $stream->status = 'active';

        $r->data = (object) [
            'orientation' => $stream->orientation,
            'status' => $stream->status,
            'source' => $stream->source,
            'thumbnail' => $stream->thumbnail,
            'likes' => $stream->likes,
            'viewers' => $stream->viewers,
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getByCompanyID(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid', 'exists:livestream_companies,id'],
            'token' => ['nullable', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'order_by' => ['nullable', 'string', 'in:viewers,likes,status,created_at,updated_at'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        if (!isset($params['offset'])) {
            $params['offset'] = 0;
        }
        if (!isset($params['limit'])) {
            $params['limit'] = 50;
        }
        if (!isset($params['order_by'])) {
            $params['order_by'] = 'created_at';
        }
        if (!isset($params['order'])) {
            $params['order'] = 'desc';
        }

        $streams = null;
        $streams_count = 0;

        try {
            $streams = match (true) {
                isset($params['token']) => Cache::remember('streams_by_company_' . $params['company_id'] . '_with_token', now()->addSeconds(1), function () use ($params) {
                    return mLiveStreams::where('company_id', '=', $params['company_id'])
                        ->where('deleted_at', '=', null)
                        ->offset($params['offset'])
                        ->limit($params['limit'])
                        ->orderBy($params['order_by'], $params['order'])
                        ->get()->map(function ($stream) {
                            $stream->source = $stream->getSource();
                            $stream->thumbnail = $stream->getThumbnailDetails();
                            $stream->makeVisible([
                                'created_at',
                                'dislikes',
                                'duration',
                                'max_duration',
                                'note',
                                'shares',
                                'sheduled_at',
                                'viewers',
                                'widget_clicks',
                                'widget_views',
                            ]);
                            return $stream;
                        });
                }),
                !isset($params['token']) => Cache::remember('streams_by_company_' . $params['company_id'] . '_without_token', now()->addSeconds(1), function () use ($params) {
                    return mLiveStreams::where('company_id', '=', $params['company_id'])
                        ->offset($params['offset'])
                        ->limit($params['limit'])
                        ->orderBy($params['order_by'], $params['order'])
                        ->get()->map(function ($stream) {
                            $stream->source = $stream->getSource();
                            $stream->thumbnail = $stream->getThumbnail();
                            return $stream;
                        });
                }),
                default => throw new \Exception('Invalid token')
            };

            $streams_count = (int) Cache::remember('streams_company_count_' . $params['company_id'], now()->addSeconds(API::CACHE_TTL), function () use ($params) {
                return mLiveStreams::where('company_id', '=', $params['company_id'])->count();
            });
        } catch (\Exception $e) {
            $message = (object)[
                'type' => 'error',
                'message' => __('Stream could not be retrieved.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if ($streams === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Stream not found.'),
            ];
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        $r->data = $streams;
        $r->data_info = (object) [
            'offset' => $params['offset'],
            'limit' => $params['limit'],
            'count' => count($streams),
            'total' => $streams_count,
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doDelete(Request $request, ?string $stream_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid', 'exists:livestream_companies,id'],
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
            'stream_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['stream_id' => $stream_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
            return $stream;
        }

        if ($stream->company_id !== $params['company_id']) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Stream could not be deleted.'),
            ];
            if (config('app.debug')) {
                $message->debug = 'Stream company_id does not match.';
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($stream->status, ['created', 'idle', 'ended'])) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Stream could not be deleted.'),
            ];
            if (config('app.debug')) {
                $message->debug = 'Stream status is not valid.';
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $service_message = '';

        try {
            switch (strtolower(env('STREAM_SERVICE'))) {
                case 'mux':
                    $service = MuxStream::doCreateLive($stream_id);
                    $service_message = $service->message;
                    break;
                case 'antmedia':
                    // Need usage latency_mode to usage correctly base url
                    $service = AntMediaStream::doDeleteLive($stream_id, $stream->latency_mode);
                    if ($service->success === false) {
                        $message = (object) [
                            'type' => 'warning',
                            'message' => $service->message ?? __('Stream could not be deleted.'),
                        ];
                        if (config('app.debug')) {
                            $message->debug = 'Occured an error while deleting stream with AntMedia.';
                        }
                        $r->messages[] = $message;
                    }
                    $service_message = $service->message;
                    break;
                default:
                    $message = (object) [
                        'type' => 'error',
                        'message' => __('Stream service not found.'),
                    ];
                    if (config('app.debug')) {
                        $message->debug = 'No stream service found.';
                    }
                    $r->messages[] = $message;
                    return response()->json($r, Response::HTTP_BAD_REQUEST);
            }
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Stream could not be deleted.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        try {
            $stream = mLiveStreams::where('id', '=', $params['stream_id'])->first();
            $stream->status = 'deleted';
            $stream->deleted_at = now();
            $stream->save();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Stream could not be deleted.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Stream successfully deleted.'),
        ];
        $r->messages = array_reverse($r->messages);

        $r->data = [
            'service_message' => $service_message,
            'deleted_at' => $stream->deleted_at,
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getRTMP(Request $request, ?string $stream_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['required', 'string', 'size:36', 'uuid'],
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
        ], $request->all(), ['stream_id' => $stream_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
            return $stream;
        }

        $r->data = (object) [
            'url' => match (strtolower(env('STREAM_SERVICE'))) {
                'mux' => [
                    'rtmp' => env('MUX_RTMP_URL', null),
                    'rtmps' => env('MUX_RTMPS_URL', null),
                ],
                'antmedia' => [
                    'rtmp' => env('ANTMEDIA_RTMP_URL', null),
                    'rtmps' => env('ANTMEDIA_RTMPS_URL', null),
                ],
                default => null,
            },
            'key' => $stream->stream_key,
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getStatus(Request $request, ?string $stream_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['required', 'string', 'size:36', 'uuid'],
            'token' => ['nullable', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
        ], $request->all(), ['stream_id' => $stream_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
            return $stream;
        }

        if (($stream = API::getLiveStreamFromService($r, $stream)) instanceof JsonResponse) {
            return $stream;
        }

        $stream->source = $stream->getSource();
        $stream->thumbnail = $stream->getThumbnail();

        $r->data = (object) [
            'status' => $stream->status,
            'viewers' => $stream->viewers,
            'likes' => $stream->likes,
            'comments' => $stream->comments,
            'orientation' => $stream->orientation,
            'last_updated' => $stream->updated_at->timestamp,
            'source' => $stream->source,
            'thumbnail' => $stream->thumbnail,
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getCurrentViews(Request $request, ?string $stream_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['required', 'string', 'size:36', 'uuid'],
            'token' => ['nullable', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
        ], $request->all(), ['stream_id' => $stream_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
            return $stream;
        }

        if (($stream = API::getLiveStreamFromService($r, $stream)) instanceof JsonResponse) {
            return $stream;
        }

        $r->data = (object) [
            'viewers' => $stream->viewers,
        ];

        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }
}
