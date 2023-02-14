<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use App\Models\LiveStreamMedias as mLiveStreamMedias;
use App\Models\LiveStreamProductGroups as mLiveStreamProductGroups;
use App\Models\Stories as mStories;
use App\Rules\strBoolean;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class Stories extends API
{
    public function doCreate(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid', 'exists:livestream_companies,id'],
            'title' => ['required', 'string', 'min:4', 'max:100'],
            'media_id' => ['nullable', 'string', 'size:36', 'uuid', 'exists:livestream_medias,id'],
            'products' => ['nullable', 'string'],
            'get_story' => ['nullable', new StrBoolean],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        $params['title'] = isset($params['title']) ? trim($params['title']) : null;
        $params['media_id'] = isset($params['media_id']) ? trim($params['media_id']) : null;
        $params['products'] = isset($params['products']) ? trim($params['products']) : null;
        $params['get_story'] = isset($params['get_story']) ? filter_var($params['get_story'], FILTER_VALIDATE_BOOLEAN) : false;

        $story_id = Str::uuid()->toString();

        if (mStories::where('id', '=', $story_id)->exists()) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Story ID already exists.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        try {
            $data = [
                'id' => $story_id,
                'company_id' => $params['company_id'],
                'title' => $params['title'],
                'media_id' => $params['media_id'],
            ];

            if ($params['media_id'] !== null) {
                if (!mLiveStreamMedias::where('id', '=', $params['media_id'])->exists()) {
                    $message = (object)[
                        'type' => 'warning',
                        'message' => __('Invalid media ID.'),
                    ];
                    $r->messages[] = $message;
                    return response()->json($r, Response::HTTP_BAD_REQUEST);
                }
                $data['status'] = 'ACTIVE';
            }

            mStories::create($data);

            if ($params['products'] !== null) {
                $params['products'] = explode(';', $params['products']);
                $params['products'] = array_map('trim', $params['products']);
                $params['products'] = array_unique($params['products']);
                $params['products'] = array_values($params['products']);
                $params['products'] = array_filter($params['products'], function ($value) {
                    if (empty($value) || is_null($value) || $value === '') {
                        return false;
                    }
                    if (!Uuid::isValid($value)) {
                        return false;
                    }
                    if (mLiveStreamMedias::where('id', '=', $value)->exists()) {
                        return false;
                    }
                    return true;
                });

                if (count($params['products']) === 0) {
                    $message = (object)[
                        'type' => 'warning',
                        'message' => __('No valid products found.'),
                    ];
                    $r->messages[] = $message;
                } else {
                    foreach ($params['products'] as $product_id) {
                        $group_id = Str::uuid()->toString();
                        $group = [
                            'id' => $group_id,
                            'product_id' => $product_id,
                            'story_id' => $story_id,
                        ];
                        mLiveStreamProductGroups::create($group);
                    }
                }
            }
        } catch (\Exception $e) {
            $message = (object)[
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

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Story created successfully.'),
        ];

        $r->data = (object) [
            'id' => $story_id,
        ];

        if ($params['get_story']) {
            if (!($story = API::getStory($r, $story_id)) instanceof JsonResponse) {
                $r->data = API::story($story);
                $r->data->id = $story_id;
            }
        }
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

        $params['title'] = isset($params['title']) ? trim($params['title']) : null;
        $params['media_id'] = isset($params['media_id']) ? trim($params['media_id']) : null;
        $params['status'] = isset($params['status']) ? trim(strtoupper($params['status'])) : null;
        $params['publish'] = isset($params['publish']) ? filter_var($params['publish'], FILTER_VALIDATE_BOOLEAN) : null;

        try {
            if ($params['title'] !== null) {
                $story->title = $params['title'];
            }

            if ($params['media_id'] !== null) {
                $story->media_id = $params['media_id'];
            }

            if ($params['status'] !== null) {
                if ($params['status'] === 'ACTIVE') {
                    if ($story->media_id && $params['media_id'] === null) {
                        $r->messages[] = (object) [
                            'type' => 'error',
                            'message' => __('Story must have a media to be active.'),
                        ];
                        return response()->json($r, Response::HTTP_BAD_REQUEST);
                    }
                }

                if ($params['status'] === 'DELETED') {
                    if ($story->publish === true) {
                        $r->messages[] = (object) [
                            'type' => 'error',
                            'message' => __('Story must be unpublished to be deleted.'),
                        ];
                        return response()->json($r, Response::HTTP_BAD_REQUEST);
                    }
                }

                $story->status = $params['status'];
            }

            if ($params['publish'] !== null) {
                if (
                    ($params['publish'] === true && $story->status !== 'ACTIVE') ||
                    ($params['status'] !== null && ($params['publish'] === true && $params['status'] !== 'ACTIVE'))
                ) {
                    $r->messages[] = (object) [
                        'type' => 'error',
                        'message' => __('Story must be active to be published.'),
                        'debug' => [
                            'status' => $story->status,
                            'publish' => $params['publish'],
                        ],
                    ];
                    return response()->json($r, Response::HTTP_BAD_REQUEST);
                }

                $story->publish = $params['publish'];
            }
            $story->save();
        } catch (\Exception $e) {
            $message = (object)[
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

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Story updated successfully.'),
        ];

        $r->data = (object) [
            'updated_at' => now()
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
                $message['debug'] = $e->getMessage();
            }
            $r->messages[] = $message;
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Story deleted.'),
        ];
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

        $params['offset'] = isset($params['offset']) ? intval($params['offset']) : 0;
        $params['limit'] = isset($params['limit']) ? intval($params['limit']) : 80;
        $params['order_by'] = isset($params['order_by']) ? $params['order_by'] : 'created_at';
        $params['order'] = isset($params['order']) ? $params['order'] : 'asc';
        $params['only_published'] = isset($params['only_published']) ? filter_var($params['only_published'], FILTER_VALIDATE_BOOLEAN) : true;

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

            $stories = Cache::remember($cache_tag, now()->addSeconds(API::CACHE_TIME), function () use ($params) {
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

            $stories_count = Cache::remember($cache_tag . '_count', now()->addSeconds(API::CACHE_TIME), function () use ($params) {
                return mStories::where('company_id', '=', $params['company_id'])->count();
            });
        } catch (\Exception $e) {
            $message = (object)[
                'type' => 'error',
                'message' => __('Failed to get story list.'),
            ];
            if (env('APP_DEBUG', false)) {
                $message['debug'] = $e->getMessage();
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

        $r->data = API::story($story);
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getSwipes(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid', 'exists:livestream_companies,id'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        $r->data = API::story($story);
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function addStoryToSwipe(Request $request, ?string $story_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'story_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['story_id' => $story_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($story = API::getStory($r, $params['story_id'])) instanceof JsonResponse) {
            return $story;
        }

        $r->data = API::story($story);
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }
}
