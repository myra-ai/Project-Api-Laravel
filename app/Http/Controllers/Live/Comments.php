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
            'stream_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'story_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'text' => ['required', 'string', 'min:4', 'max:600'],
            'name' => ['required', 'string', 'min:4', 'max:120'],
            'email' => ['nullable', 'string', 'email', 'min:5', 'max:200'],
            'pinned' => ['nullable', 'string', new strBoolean],
            'is_streammer' => ['nullable', 'string', new strBoolean],
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
            $qry = new mLiveStreamComments();
            match (true) {
                $stream !== null => $qry->stream_id = $stream->id,
                $story !== null => $qry->story_id = $story->id,
            };
            $qry->text = $params['text'];
            $qry->name = $params['name'];
            $qry->email = $params['email'];
            $qry->pinned = isset($params['pinned']) ? $params['pinned'] : false;
            $qry->is_streammer = isset($params['is_streammer']) ? $params['is_streammer'] : false;
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
            match (true) {
                $stream !== null => $stream->increment('comments'),
                $story !== null => $story->increment('comments'),
            };
        } catch (\Exception $e) {
            // Ignore
        }

        $r->data = (object) [
            'id' => $qry->id,
        ];
        $r->data_info = (object) [
            'comments' => match (true) {
                $stream !== null => $stream->comments,
                $story !== null => $story->comments,
            },
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getComments(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'story_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'order_by' => ['nullable', 'string', 'in:created_at,pinned,is_streammer'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
            'separe_pinned' => ['nullable', new strBoolean],
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

        $comments = [];
        $pinned = null;

        try {
            $params['offset'] = isset($params['offset']) ? $params['offset'] : 0;
            $params['limit'] = isset($params['limit']) ? $params['limit'] : 100;
            $params['order_by'] = isset($params['order_by']) ? $params['order_by'] : 'created_at';
            $params['order'] = isset($params['order']) ? $params['order'] : 'asc';
            $params['separe_pinned'] = isset($params['separe_pinned']) ? $params['separe_pinned'] : false;

            $cache_tag = match (true) {
                $stream !== null => 'stream_comments_list_' . $stream->id,
                $story !== null => 'story_comments_list_' . $story->id,
            };

            $cache_tag .= implode('_', [
                $params['offset'],
                $params['limit'],
                $params['order_by'],
                $params['order'],
                $params['separe_pinned'],
            ]);

            $comments = Cache::remember($cache_tag, now()->addSeconds(1), function () use ($stream, $story, $params) {
                $qry = mLiveStreamComments::select([
                    'id',
                    'text',
                    'name',
                    'pinned',
                    'is_streammer',
                ]);
                match (true) {
                    $stream !== null => $qry->where('stream_id', '=', $stream->id),
                    $story !== null => $qry->where('story_id', '=', $story->id),
                };
                if (isset($params['order_by'])) {
                    $qry->orderBy($params['order_by'], $params['order']);
                }
                if (isset($params['separe_pinned']) && $params['separe_pinned']) {
                    $qry->where('pinned', '=', false);
                }
                if (isset($params['offset'])) {
                    $qry->offset($params['offset']);
                }
                if (isset($params['limit'])) {
                    $qry->limit($params['limit']);
                }
                return $qry->get();
            });

            if ($params['separe_pinned']) {
                $pinned = Cache::remember($cache_tag . '_pinned', now()->addSeconds(API::CACHE_TIME), function () use ($stream, $story, $params) {
                    $qry = mLiveStreamComments::select([
                        'text',
                        'name',
                    ]);
                    match (true) {
                        $stream !== null => $qry->where('stream_id', '=', $stream->id),
                        $story !== null => $qry->where('story_id', '=', $story->id),
                    };
                    $qry->where('pinned', '=', true);
                    if (isset($params['order_by'])) {
                        $qry->orderBy($params['order_by'], $params['order'] ?? 'asc');
                    }
                    return $qry->first();
                });
            }
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to get comments.'),
            ];
            if (config('app.debug')) {
                $message->debug = __($e->getMessage());
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->data = $comments;
        $r->data_info = (object) [
            'pinned' => $pinned,
            'offset' => (int) $params['offset'],
            'limit' => (int) $params['limit'],
            'count' => count($comments),
            'total' => match (true) {
                $stream !== null => $stream->comments,
                $story !== null => $story->comments,
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

        $count = 0;

        try {
            $count = Cache::remember('stream_comments_' . $stream->id, now()->addSeconds(5), function () use ($stream, $story) {
                if ($stream !== null) {
                    $count = mLiveStreamComments::where('stream_id', '=', $stream->id)->where('deleted_at', '=', null)->count();
                    $stream->comments = $count;
                    $stream->save();
                    return $count;
                } else if ($story !== null) {
                    $count = mLiveStreamComments::where('story_id', '=', $story->id)->where('deleted_at', '=', null)->count();
                    $story->comments = $count;
                    $story->save();
                    return $count;
                }
                return 0;
            });
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to calculate live stream comments.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = __($e->getMessage());
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
