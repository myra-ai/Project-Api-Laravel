<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use App\Models\LiveStreamComments as mLiveStreamComments;
use App\Rules\strBoolean;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class Comments extends API
{
    public function addComment(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['nullable', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
            'stream_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'story_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'text' => ['required', 'string', 'min:4', 'max:600'],
            'name' => ['required', 'string', 'min:4', 'max:60'],
            'email' => ['nullable', 'string', 'email', 'min:5', 'max:110'],
            'pinned' => ['nullable', 'string', new strBoolean],
            'is_streammer' => ['nullable', 'string', new strBoolean],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        $params['stream_id'] = isset($params['stream_id']) ? trim($params['stream_id']) : null;
        $params['story_id'] = isset($params['story_id']) ? trim($params['story_id']) : null;
        $params['text'] = isset($params['text']) ? trim($params['text']) : null;
        $params['name'] = isset($params['name']) ? trim($params['name']) : null;
        $params['email'] = isset($params['email']) ? trim($params['email']) : null;
        $params['pinned'] = isset($params['pinned']) ? filter_var($params['pinned'], FILTER_VALIDATE_BOOLEAN) : false;
        $params['is_streammer'] = isset($params['is_streammer']) ? filter_var($params['is_streammer'], FILTER_VALIDATE_BOOLEAN) : false;
        $params['token'] = isset($params['token']) ? trim($params['token']) : null;

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

        if ($params['is_streammer'] === false && $params['token'] === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Token is required.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        try {
            $qry = new mLiveStreamComments();
            if ($params['stream_id'] !== null) {
                $qry->stream_id = $stream->id;
                API::registerStreamMetric($request, $params, [
                    'comment' => 1,
                ]);
            } else if ($params['story_id'] !== null) {
                $qry->story_id = $story->id;
                API::registerStoryMetric($request, $params, [
                    'comment' => 1,
                ]);
            }
            $qry->text = $params['text'];
            $qry->name = $params['name'];
            $qry->email = $params['email'];
            $qry->pinned = $params['pinned'];
            $qry->is_streammer = $params['is_streammer'];
            $qry->created_at = now()->format('Y-m-d H:i:s.u');
            $qry->save();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to add comment.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            if ($params['stream_id'] !== null) {
                $stream->increment('comments');
            } else if ($params['story_id'] !== null) {
                $story->increment('comments');
            }
        } catch (\Exception $e) {
            // Ignore
        }

        $r->data = (object) [
            'id' => $qry->id,
        ];
        $r->data_info = (object) [
            'comments' => match ($params['stream_id'] !== null) {
                true => $stream->comments,
                default => $story->comments,
            },
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getComments(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['nullable', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
            'stream_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'story_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:80'],
            'order_by' => ['nullable', 'string', 'in:id,created_at,pinned,is_streammer'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
            'separe_pinned' => ['nullable', new strBoolean],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        $params['stream_id'] = isset($params['stream_id']) ? trim($params['stream_id']) : null;
        $params['story_id'] = isset($params['story_id']) ? trim($params['story_id']) : null;
        $params['offset'] = isset($params['offset']) ? intval($params['offset']) : 0;
        $params['limit'] = isset($params['limit']) ? intval($params['limit']) : 80;
        $params['order_by'] = isset($params['order_by']) ? trim($params['order_by']) : 'created_at';
        $params['order'] = isset($params['order']) ? trim($params['order']) : 'asc';
        $params['separe_pinned'] = isset($params['separe_pinned']) ? filter_var($params['separe_pinned'], FILTER_VALIDATE_BOOLEAN) : false;
        $params['token'] = isset($params['token']) ? trim($params['token']) : null;

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

        $comments = [];
        $pinned_comment = null;

        $cache_tag = match ($params['stream_id'] !== null) {
            true => 'stream_comments_list_' . $stream->id,
            default => 'story_comments_list_' . $story->id,
        };

        $cache_tag .= sha1(implode('_', $params));

        try {
            $comments = Cache::remember($cache_tag, now()->addSeconds(API::COMMENTS_CACHE_TIME), function () use ($params) {
                $qry = mLiveStreamComments::select(match ($params['token'] !== null) {
                    true => [
                        'id',
                        'text',
                        'name',
                        'pinned',
                        'is_streammer',
                    ],
                    default => [
                        'text',
                        'name',
                        'pinned',
                        'is_streammer',
                    ]
                });
                if ($params['stream_id'] !== null) {
                    $qry->where('stream_id', '=', $params['stream_id']);
                } else if ($params['story_id'] !== null) {
                    $qry->where('story_id', '=', $params['story_id']);
                }
                if ($params['separe_pinned']) {
                    $qry->where('pinned', '=', false);
                }
                $qry->offset($params['offset']);
                $qry->limit($params['limit']);
                $qry->orderBy($params['order_by'], $params['order']);
                return $qry->get();
            });

            if ($params['separe_pinned']) {
                $pinned_comment = Cache::remember($cache_tag . '_pinned', now()->addSeconds(API::COMMENTS_CACHE_TIME), function () use ($params) {
                    $qry = mLiveStreamComments::select([
                        'text',
                        'name',
                    ]);
                    if ($params['stream_id'] !== null) {
                        $qry->where('stream_id', '=', $params['stream_id']);
                    } else if ($params['story_id'] !== null) {
                        $qry->where('story_id', '=', $params['story_id']);
                    }
                    $qry->where('pinned', '=', true);
                    $qry->orderBy($params['order_by'], $params['order']);
                    return $qry->first();
                });
            }
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to get comments.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->data = $comments;
        $r->data_info = (object) [
            'pinned' => $pinned_comment,
            'offset' => $params['offset'],
            'limit' => $params['limit'],
            'count' => count($comments),
            'total' => match ($params['stream_id'] !== null) {
                true => $stream->comments_count,
                default => $story->comments_count,
            },
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getCommentsCount(Request $request): JsonResponse
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

        $count = 0;

        try {
            $count = match ($params['stream_id'] !== null) {
                true => Cache::remember('stream_comments_' . $params['stream_id'], now()->addSeconds(API::COMMENTS_CACHE_TIME), function () use ($params, $stream) {
                    $count = mLiveStreamComments::where('stream_id', '=', $params['stream_id'])->where('deleted_at', '=', null)->count();
                    $stream->comments = $count;
                    $stream->save();
                    return $count;
                }),
                default => Cache::remember('story_comments_' . $params['story_id'], now()->addSeconds(API::COMMENTS_CACHE_TIME), function () use ($params, $story) {
                    $count = mLiveStreamComments::where('story_id', '=', $params['story_id'])->where('deleted_at', '=', null)->count();
                    $story->comments = $count;
                    $story->save();
                    return $count;
                })
            };
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to get comments count.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->data = (object) [
            'comments' => $count,
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }
}
