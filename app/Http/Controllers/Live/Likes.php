<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class Likes extends API
{
    public function addLike(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'story_id' => ['nullable', 'string', 'size:36', 'uuid'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        $params['stream_id'] = isset($params['stream_id']) ? trim($params['stream_id']) : null;
        $params['story_id'] = isset($params['story_id']) ? trim($params['story_id']) : null;

        if (isset($params['stream_id'])) {
            if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
                return $stream;
            }
        } else if (isset($params['story_id'])) {
            if (($story = API::getStory($r, $params['story_id'])) instanceof JsonResponse) {
                return $story;
            }
        } else {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Invalid stream or story ID.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        try {
            if ($params['stream_id'] !== null) {
                $stream->increment('likes');
                API::registerStreamMetric($request, $params, [
                    'like' => 1,
                ]);
                Cache::put('stream_by_id_' . $stream->id, $stream, now()->addSeconds(self::CACHE_TIME));
            } else {
                $story->increment('likes');
                API::registerStoryMetric($request, $params, [
                    'like' => 1,
                ]);
                Cache::put('story_by_id_' . $story->id, $story, now()->addSeconds(self::CACHE_TIME));
            }
        } catch (\Exception $e) {
            $message = (object)[
                'type' => 'error',
                'message' => __('Failed to add like.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->data = (object) [
            'likes' => match ($params['stream_id'] !== null) {
                true => $stream->likes,
                default => $story->likes,
            },
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function removeLike(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'story_id' => ['nullable', 'string', 'size:36', 'uuid'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        $params['stream_id'] = isset($params['stream_id']) ? trim($params['stream_id']) : null;
        $params['story_id'] = isset($params['story_id']) ? trim($params['story_id']) : null;

        if (isset($params['stream_id'])) {
            if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
                return $stream;
            }
        } else if (isset($params['story_id'])) {
            if (($story = API::getStory($r, $params['story_id'])) instanceof JsonResponse) {
                return $story;
            }
        } else {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Stream ID or Story ID is required.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        try {
            if ($params['stream_id'] !== null) {
                if ($stream->likes > 1) {
                    $stream->decrement('likes');
                    API::registerStreamMetric($request, $params, [
                        'unlike' => 1,
                    ]);
                    Cache::put('stream_by_id_' . $stream->id, $stream, now()->addSeconds(self::CACHE_TIME));
                } else {
                    $r->messages[] = (object) [
                        'type' => 'warning',
                        'message' => __('No likes to remove.'),
                    ];
                    $stream->likes = 0;
                }
            } else {
                if ($story->likes > 1) {
                    $story->decrement('likes');
                    API::registerStoryMetric($request, $params, [
                        'unlike' => 1,
                    ]);
                    Cache::put('story_by_id_' . $story->id, $story, now()->addSeconds(self::CACHE_TIME));
                } else {
                    $r->messages[] = (object) [
                        'type' => 'warning',
                        'message' => __('No likes to remove.'),
                    ];
                    $story->likes = 0;
                }
            }
        } catch (\Exception $e) {
            $message = (object)[
                'type' => 'error',
                'message' => __('Failed to remove like.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->data = (object) [
            'likes' => match ($params['stream_id'] !== null) {
                true => $stream->likes,
                default => $story->likes,
            },
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getLikes(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'story_id' => ['nullable', 'string', 'size:36', 'uuid'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        $params['stream_id'] = isset($params['stream_id']) ? trim($params['stream_id']) : null;
        $params['story_id'] = isset($params['story_id']) ? trim($params['story_id']) : null;

        if ($params['stream_id'] !== null) {
            if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
                return $stream;
            }
        } else if ($params['story_id'] !== null) {
            if (($story = API::getStory($r, $params['story_id'])) instanceof JsonResponse) {
                return $story;
            }
        } else {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Stream ID or Story ID is required.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $r->data = (object) [
            'likes' => match ($params['stream_id'] !== null) {
                true => $stream->likes,
                default => $story->likes,
            },
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }
}
