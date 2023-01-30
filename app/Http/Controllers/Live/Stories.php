<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use App\Models\Stories as mStories;
use App\Models\LiveStreamMedias as mLiveStreamMedias;
use App\Rules\strBoolean;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Stories extends API
{
    public function doCreate(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid', 'exists:livestream_companies,id'],
            'title' => ['required', 'string', 'min:4', 'max:100'],
            'media_id' => ['nullable', 'string', 'size:36', 'uuid', 'exists:livestream_medias,id'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        $story_id = Str::uuid()->toString();

        if (mStories::where('id', '=', $story_id)->exists()) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Story ID already exists.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if (!isset($params['media_id'])) {
            $params['media_id'] = null;
        }

        try {
            mStories::create([
                'id' => $story_id,
                'company_id' => $params['company_id'],
                'title' => $params['title'],
                'media_id' => $params['media_id'],
            ]);
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to create story.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = [
                    'message' => $e->getMessage(),
                ];
            }
            $r->messages[] = (object) $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->data = [
            'id' => $story_id,
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doUpdate(Request $request, ?string $story_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'story_id' => ['required', 'string', 'size:36', 'uuid'],
            'title' => ['nullable', 'string', 'min:4', 'max:100'],
            'media_id' => ['nullable', 'string', 'size:36', 'uuid', 'exists:livestream_medias,id'],
            'status' => ['nullable', 'string', 'in:draft,active,deleted'],
            'publish' => ['nullable', new strBoolean],
        ], $request->all(), ['story_id' => $story_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($story = API::getStory($r, $params['story_id'])) instanceof JsonResponse) {
            return $story;
        }

        try {
            if (isset($params['title'])) {
                $story->title = $params['title'];
            }
            if (isset($params['media_id'])) {
                $story->media_id = $params['media_id'];
            }
            if (isset($params['status'])) {
                $story->status = $params['status'];
            }
            if (isset($params['publish'])) {
                if ($params['publish'] === true) {
                    if ($params['status'] !== 'active' || $story->status !== 'active') {
                        $r->messages[] = (object) [
                            'type' => 'error',
                            'message' => __('Story must be active to be published.'),
                        ];
                        return response()->json($r, Response::HTTP_BAD_REQUEST);
                    }
                }
                $story->publish = $params['publish'];
            }
            $story->save();
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to update story.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = [
                    'message' => $e->getMessage(),
                ];
            }
            $r->messages[] = (object) $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->data = (object) [
            'id' => $story->id,
            'title' => $story->title,
            'media_id' => $story->media_id,
            'status' => $story->status,
            'publish' => $story->publish,
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doDelete(Request $request, ?string $story_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'story_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['story_id' => $story_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($story = API::getStory($r, $params['story_id'])) instanceof JsonResponse) {
            return $story;
        }

        $now = now()->format('Y-m-d H:i:s');

        try {
            $story->deleted_at = $now;
            $story->save();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'warning',
                'message' => __('Failed to delete story.'),
            ];
            if (env('APP_DEBUG', false)) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = $message;
        }

        $r->data = (object) [
            'id' => $story->id,
            'deleted_at' => $now,
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getByStoryCompanyID(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid', 'exists:livestream_companies,id'],
            'only_published' => ['nullable', new strBoolean],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'order_by' => ['nullable', 'string', 'in:viewers,views,comments,clicks,opens,status,publish,created_at'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        $params['only_published'] = filter_var($params['only_published'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $params['offset'] = $params['offset'] ?? 0;
        $params['limit'] = $params['limit'] ?? 50;
        $params['order_by'] = $params['order_by'] ?? 'created_at';
        $params['order'] = $params['order'] ?? 'asc';

        $stories_count = 0;
        $stories = [];

        try {
            $cache_tag = 'stories_' . $params['company_id'];
            $cache_tag .= implode('_', [
                $params['only_published'],
                $params['offset'],
                $params['limit'],
                $params['order_by'],
                $params['order'],
            ]);

            $stories = Cache::remember($cache_tag, now()->addSeconds(3), function () use ($params) {
                return mStories::where('company_id', '=', $params['company_id'])
                    ->where('deleted_at', '=', null)
                    ->when($params['only_published'], function ($query) {
                        return $query->where('publish', '=', true);
                    })
                    ->orderBy($params['order_by'], $params['order'])
                    ->offset($params['offset'])
                    ->limit($params['limit'])
                    ->get()->map(function ($story) {
                        $source = $story->getSource();
                        $thumbnail = $story->getThumbnail();
                        if ($source !== null) {
                            $source = (object) [
                                'id' => $source->id,
                                'alt' => $source->alt,
                                'mime' => $source->mime,
                                'width' => $thumbnail->width,
                                'height' => $thumbnail->height,
                                'url' => $source->s3_available !== null ? API::getMediaCdnUrl($source->path) : API::getMediaUrl($source->id),
                            ];
                        }
                        if ($thumbnail !== null) {
                            $thumbnail = (object) [
                                'id' => $thumbnail->id,
                                'alt' => $thumbnail->alt,
                                'mime' => $thumbnail->mime,
                                'width' => $thumbnail->width,
                                'height' => $thumbnail->height,
                                'url' => $thumbnail->s3_available !== null ? API::getMediaCdnUrl($thumbnail->path) : API::getMediaUrl($thumbnail->id),
                            ];
                        }
                        return (object) [
                            'id' => $story->id,
                            'title' => $story->title,
                            'source' => $source,
                            'thumbnail' => $thumbnail,
                            'status' => $story->status,
                            'publish' => $story->publish,
                            'views' => $story->views,
                            'comments' => $story->comments,
                            'created_at' => $story->created_at,
                        ];
                    });
            });

            $stories_count = Cache::remember($cache_tag . '_count', now()->addSeconds(3), function () use ($params) {
                return mStories::where('company_id', '=', $params['company_id'])->count();
            });
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to get story list.'),
            ];
            if (env('APP_DEBUG', false)) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->data = $stories;
        $r->data_info = (object) [
            'offset' => $params['offset'],
            'limit' => $params['limit'],
            'count' => count($stories),
            'total' => $stories_count,
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getByStoryID(Request $request, ?string $story_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'story_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['story_id' => $story_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($story = API::getStory($r, $params['story_id'])) instanceof JsonResponse) {
            return $story;
        }

        $source = $story->getSource();
        $thumbnail = $story->getThumbnail();
        if ($source !== null) {
            $source = (object) [
                'id' => $source->id,
                'alt' => $source->alt,
                'mime' => $source->mime,
                'width' => $source->width,
                'height' => $source->height,
                'url' => $source->s3_available !== null ? API::getMediaCdnUrl($source->path) : API::getMediaUrl($source->id),
            ];
        }
        if ($thumbnail !== null) {
            $thumbnail = (object) [
                'id' => $thumbnail->id,
                'alt' => $thumbnail->alt,
                'mime' => $thumbnail->mime,
                'width' => $thumbnail->width,
                'height' => $thumbnail->height,
                'url' => $thumbnail->s3_available !== null ? API::getMediaCdnUrl($thumbnail->path) : API::getMediaUrl($thumbnail->id),
            ];
        }

        $r->data = (object) [
            'company_id' => $story->company_id,
            'title' => $story->title,
            'source' => $source,
            'thumbnail' => $thumbnail,
            'status' => $story->status,
            'publish' => $story->publish,
            'viewers' => $story->viewers,
            'clicks' => $story->clicks,
            'comments' => $story->comments,
            'dislikes' => $story->dislikes,
            'likes' => $story->likes,
            'opens' => $story->opens,
            'views' => $story->views,
            'created_at' => $story->created_at,
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }
}
