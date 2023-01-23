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

        $stream = null;
        $story = null;

        if (isset($params['stream_id'])) {
            if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
                return $stream;
            }
        } else if (isset($params['story_id'])) {
            if (($story = API::getStory($r, $params['story_id'])) instanceof JsonResponse) {
                return $story;
            }
        }

        if ($stream === null && $story === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Stream ID or Story ID is required.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        try {
            if ($stream !== null) {
                $stream->increment('likes');
                Cache::put('stream_by_id_' . $stream->id, $stream, now()->addSeconds(self::CACHE_TIME));
            } else if ($story !== null) {
                $story->increment('likes');
                Cache::put('story_by_id_' . $story->id, $story, now()->addSeconds(self::CACHE_TIME));
            }
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to add like.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->data = (object) [
            'likes' => match (true) {
                $stream !== null => $stream->likes,
                $story !== null => $story->likes,
            },
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function removeLike(Request $request, ?string $stream_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'story_id' => ['nullable', 'string', 'size:36', 'uuid'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        $stream = null;
        $story = null;

        if (isset($params['stream_id'])) {
            if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
                return $stream;
            }
        } else if (isset($params['story_id'])) {
            if (($story = API::getStory($r, $params['story_id'])) instanceof JsonResponse) {
                return $story;
            }
        }

        if ($stream === null && $story === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Stream ID or Story ID is required.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        try {
            if ($stream !== null) {
                if ($stream->likes > 1) {
                    $stream->decrement('likes');
                    Cache::put('stream_by_id_' . $stream->id, $stream, now()->addSeconds(self::CACHE_TIME));
                } else {
                    $r->messages[] = (object) [
                        'type' => 'warning',
                        'message' => __('No likes to remove.'),
                    ];
                    $stream->likes = 0;
                }
            } else if ($story !== null) {
                if ($story->likes > 1) {
                    $story->decrement('likes');
                    Cache::put('story_by_id_' . $story->id, $story, now()->addSeconds(self::CACHE_TIME));
                } else {
                    $r->messages[] = (object) [
                        'type' => 'warning',
                        'message' => __('No likes to remove.'),
                    ];
                    $story->likes = 0;
                }
            } else {
                $message = (object) [
                    'type' => 'error',
                    'message' => __('Failed to remove like.'),
                ];
                if (config('app.debug')) {
                    $message->debug = __('No stream or story found.');
                }
                $r->messages[] = $message;
                return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to remove like.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->data = (object) [
            'likes' => match (true) {
                $stream !== null => $stream->likes,
                $story !== null => $story->likes,
            },
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getLikes(Request $request, ?string $stream_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'story_id' => ['nullable', 'string', 'size:36', 'uuid'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        $stream = null;
        $story = null;

        if (isset($params['stream_id'])) {
            if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
                return $stream;
            }
        } else if (isset($params['story_id'])) {
            if (($story = API::getStory($r, $params['story_id'])) instanceof JsonResponse) {
                return $story;
            }
        }

        if ($stream === null && $story === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Stream ID or Story ID is required.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $r->data = (object) [
            'likes' => match (true) {
                $stream !== null => $stream->likes,
                $story !== null => $story->likes,
            },
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }
}
