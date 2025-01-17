<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use App\Models\Swipes as mSwipes;
use App\Rules\strBoolean;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Ramsey\Uuid\Uuid;

class Swipes extends API
{
    public function doCreate(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
            'company_id' => ['required', 'string', 'size:36', 'uuid', 'exists:companies,id'],
            'title' => ['required', 'string', 'min:4', 'max:60'],
            'status' => ['nullable', 'regex:/^[a-z0-9\s]+$/i', 'in:0,draft,1,ready,2,active,3,archived'],
            'published' => ['nullable', new strBoolean],
            'stories' => ['nullable', 'string'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        $params['title'] = isset($params['title']) ? $params['title'] : null;
        $params['status'] = isset($params['status']) ? match (strtolower($params['status'])) {
            '0', 'draft' => API::SWIPE_STATUS_DRAFT,
            '1', 'ready' => API::SWIPE_STATUS_READY,
            '2', 'active' => API::SWIPE_STATUS_ACTIVE,
            '3', 'archived' => API::SWIPE_STATUS_ARCHIVED,
            default => API::SWIPE_STATUS_DRAFT,
        }
            : API::SWIPE_STATUS_DRAFT;
        $params['stories'] = isset($params['stories']) ? $params['stories'] : null;
        $params['published'] = isset($params['published']) ? filter_var($params['published'], FILTER_VALIDATE_BOOLEAN) : false;

        if ($params['stories'] !== null) {
            $stories = explode(';', $params['stories']);
            $stories = array_map(function ($story) {
                return trim($story);
            }, $stories);
            $stories = array_filter($stories, function ($story) {
                return Uuid::isValid($story);
            });

            if (count($stories) > config('livestream.max_swipe_stories')) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => __('Swipe cannot have more than :max stories', ['max' => config('livestream.max_swipe_stories')]),
                ];
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }

            $params['status'] = API::SWIPE_STATUS_READY;

            if ($params['published']) {
                $params['status'] = API::SWIPE_STATUS_ACTIVE;
            }
        } else {
            $params['published'] = false;
        }

        try {
            $swipe = new mSwipes();
            $id = null;
            $swipe->createSwipe($params, $id);
            $swipe->refresh();
            if ($id === null) {
                $message = (object) [
                    'type' => 'error',
                    'message' => 'Could not create swipe',
                ];
                if (config('app.debug')) {
                    $message->debug = 'Returned ID is null';
                }
                return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            if ($params['stories'] !== null) {
                foreach ($stories as $story) {
                    $swipe->attachStory([
                        'swipe_id' => $id,
                        'story_id' => $story,
                    ]);
                }
            }
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => 'Could not create swipe',
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => 'Swipe created',
        ];
        $r->data = (object) [
            'id' => $id,
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doUpdate(Request $request, ?string $swipe_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
            'swipe_id' => ['required', 'string', 'size:36', 'uuid'],
            'title' => ['nullable', 'string', 'min:4', 'max:60'],
            'status' => ['nullable', 'in:0,draft,1,ready,2,active,3,archived'],
            'published' => ['nullable', new strBoolean],
        ], $request->all(), ['swipe_id' => $swipe_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($swipe = API::getSwipe($params['swipe_id'], $r)) instanceof JsonResponse) {
            return $swipe;
        }

        $params['title'] = isset($params['title']) ? trim($params['title']) : null;
        $params['status'] = isset($params['status']) ? match (strtolower(trim($params['status']))) {
            '0', 'draft' => API::SWIPE_STATUS_DRAFT,
            '1', 'ready' => API::SWIPE_STATUS_READY,
            '2', 'active' => API::SWIPE_STATUS_ACTIVE,
            '3', 'archived' => API::SWIPE_STATUS_ARCHIVED,
            default => API::SWIPE_STATUS_DRAFT,
        }
            : null;
        $params['published'] = isset($params['published']) ? filter_var($params['published'], FILTER_VALIDATE_BOOLEAN) : null;

        $cache_tag = 'swipe_' . $params['swipe_id'];

        try {
            $message = null;
            $updated_swipe = $swipe->updateSwipe($params, $message);
            if ($updated_swipe === null) {
                $r->messages[] = (object) [
                    'type' => 'warning',
                    'message' => 'No changes made',
                ];
                return response()->json($r, Response::HTTP_OK);
            } else if ($updated_swipe === false) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => $message !== null ? $message : 'Could not update swipe',
                ];
                return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
            } else {
                Cache::forget($cache_tag);
            }
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => 'Could not create swipe',
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => 'Swipe updated successfully',
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doDelete(Request $request, ?string $swipe_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
            'swipe_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['swipe_id' => $swipe_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($swipe = API::getSwipe($params['swipe_id'], $r)) instanceof JsonResponse) {
            return $swipe;
        }

        try {
            Cache::forget('swipe_' . $params['swipe_id']);
            if (!$swipe->deleteSwipe($params['swipe_id'])) {
                $message = (object) [
                    'type' => 'error',
                    'message' => 'Swipe already deleted',
                ];
                $r->messages[] = $message;
                return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => 'Could not delete swipe',
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => 'Swipe deleted successfully',
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getListByCompanyId(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid', 'exists:companies,id'],
            'token' => ['nullable', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
            'order_by' => ['nullable', 'string', 'in:created_at,updated_at'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:80'],
            'show_attached' => ['nullable', new strBoolean],
            'attached_detailed' => ['nullable', new strBoolean],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        $params['show_attached'] = isset($params['show_attached']) ? filter_var($params['show_attached'], FILTER_VALIDATE_BOOLEAN) : false;
        $params['attached_detailed'] = isset($params['attached_detailed']) ? filter_var($params['attached_detailed'], FILTER_VALIDATE_BOOLEAN) : false;
        $params['token'] = isset($params['token']) ? trim($params['token']) : null;

        if (($swipes = API::getSwipes($params['company_id'], $r, $params)) instanceof JsonResponse) {
            return $swipes;
        }

        $cache_tag = 'swipes_' . $params['company_id'];
        $cache_tag .= sha1(implode('_', $params));

        $data = [];

        try {
            $data = Cache::remember($cache_tag, now()->addSeconds(API::CACHE_TTL_SWIPES), function () use ($swipes, $params) {
                return $swipes->map(function ($swipe) use ($params) {
                    $swipe->makeHidden(['company_id', 'updated_at', 'deleted_at']);
                    if ($params['token'] === null) {
                        $swipe->makeHidden('created_at');
                    }
                    if ($params['show_attached']) {
                        $swipe->stories = match ($params['attached_detailed']) {
                            true => $swipe->getAttachedStoriesDetailed(),
                            default => $swipe->getAttachedStories()->map(function ($story) {
                                $story->makeHidden(['swipe_id', 'created_at', 'updated_at']);
                                return $story;
                            })
                        };
                    }
                    return $swipe;
                });
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => 'Could not get swipes',
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->data = $data;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getStoriesBySwipeId(Request $request, ?string $swipe_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'swipe_id' => ['required', 'string', 'size:36', 'uuid'],
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:80'],
            'filter' => ['nullable', 'string', 'in:all,attached,unattached,status,published'],
            'filter_status' => ['nullable', 'regex:/^[a-z0-9\s]+$/i', 'in:0,draft,1,ready,2,active,3,archived'],
            'filter_publish' => ['nullable', new strBoolean],
            'filter_from' => ['nullable', 'date'],
            'filter_to' => ['nullable', 'date'],
            'order_by' => ['nullable', 'string', 'in:selected,title,created_at,updated_at'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
            'is_attached' => ['nullable', new strBoolean],
            'search' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9\s\p{P}]+$/u', 'min:1', 'max:100'],
        ], $request->all(), ['swipe_id' => $swipe_id])) instanceof JsonResponse) {
            return $params;
        }

        $params['offset'] = isset($params['offset']) ? intval($params['offset']) : 0;
        $params['limit'] = isset($params['limit']) ? intval($params['limit']) : 80;
        $params['filter'] = isset($params['filter']) ? strtolower($params['filter']) : 'all';
        $params['filter_status'] = isset($params['filter_status']) ? match (strtolower($params['filter_status'])) {
            '0', 'draft' => API::STORY_STATUS_DRAFT,
            '1', 'ready' => API::STORY_STATUS_READY,
            '2', 'active' => API::STORY_STATUS_ACTIVE,
            '3', 'archived' => API::STORY_STATUS_ARCHIVED,
            default => API::STORY_STATUS_DRAFT,
        }
            : null;
        $params['filter_publish'] = isset($params['filter_publish']) ? filter_var($params['filter_publish'], FILTER_VALIDATE_BOOLEAN) : null;
        $params['filter_from'] = isset($params['filter_from']) ? $params['filter_from'] : null;
        $params['filter_to'] = isset($params['filter_to']) ? $params['filter_to'] : null;
        $params['order_by'] = isset($params['order_by']) ? match (strtolower($params['order_by'])) {
            'selected' => 'selected',
            'title' => 'title',
            'updated_at' => 'updated_at',
            default => 'created_at',
        }
        : 'created_at';
        $params['order'] = isset($params['order']) ? $params['order'] : 'desc';
        $params['is_attached'] = isset($params['is_attached']) ? filter_var($params['is_attached'], FILTER_VALIDATE_BOOLEAN) : false;
        $params['search'] = isset($params['search']) ? $params['search'] : null;

        if (($swipe = API::getSwipe($params['swipe_id'], $r)) instanceof JsonResponse) {
            return $swipe;
        }

        $cache_tag = 'swipe_stories_' . $params['swipe_id'];
        $cache_tag .= sha1(implode('_', $params));

        $data = [];
        $r->data_info = [];

        try {
            $data = Cache::remember($cache_tag, now()->addSeconds(API::CACHE_TTL_SWIPES), function () use ($swipe, $params, &$r) {
                return $swipe->getStories($params, $r->data_info);
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => 'Could not get stories',
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->data = $data;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getById(Request $request, ?string $swipe_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'swipe_id' => ['required', 'string', 'size:36', 'uuid'],
            'token' => ['nullable', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
            'attached_show' => ['nullable', new strBoolean],
            'attached_detailed' => ['nullable', new strBoolean],
        ], $request->all(), ['swipe_id' => $swipe_id])) instanceof JsonResponse) {
            return $params;
        }

        $params['attached_show'] = isset($params['attached_show']) ? filter_var($params['attached_show'], FILTER_VALIDATE_BOOLEAN) : false;
        $params['attached_detailed'] = isset($params['attached_detailed']) ? filter_var($params['attached_detailed'], FILTER_VALIDATE_BOOLEAN) : false;
        $params['token'] = isset($params['token']) ? trim($params['token']) : null;

        if (($swipe = API::getSwipe($params['swipe_id'], $r)) instanceof JsonResponse) {
            return $swipe;
        }

        if ($params['token'] === null) {
            $swipe->makeHidden(['id', 'company_id', 'created_at', 'updated_at', 'deleted_at']);
        } else {
            $swipe->makeHidden(['id', 'company_id', 'updated_at', 'deleted_at']);
        }

        if ($params['attached_show']) {
            if ($params['attached_detailed']) {
                $swipe->stories = $swipe->getAttachedStoriesDetailed();
            } else {
                $swipe->stories = $swipe->getAttachedStories()->map(function ($story) {
                    $story->makeHidden(['swipe_id', 'created_at', 'updated_at']);
                    return $story;
                });
            }
        }

        $r->data = $swipe;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doAttachStory(Request $request, ?string $swipe_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'swipe_id' => ['required', 'string', 'size:36', 'uuid'],
            'story_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['swipe_id' => $swipe_id])) instanceof JsonResponse) {
            return $params;
        }
        if (($swipe = API::getSwipe($params['swipe_id'], $r)) instanceof JsonResponse) {
            return $swipe;
        }

        if (($story = API::getStory($r, $params['story_id'])) instanceof JsonResponse) {
            return $story;
        }

        try {
            if ($swipe->countAttachedStories() >= config('livestream.max_swipe_stories')) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => 'This swipe already has the maximum number of stories attached',
                ];
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }
            if (!$swipe->attachStory($params)) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => 'This story is already attached to this swipe',
                ];
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => 'Could not attach story to swipe',
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => 'Story attached to swipe',
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doDetachStory(Request $request, ?string $swipe_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'swipe_id' => ['required', 'string', 'size:36', 'uuid'],
            'story_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['swipe_id' => $swipe_id])) instanceof JsonResponse) {
            return $params;
        }
        if (($swipe = API::getSwipe($params['swipe_id'], $r)) instanceof JsonResponse) {
            return $swipe;
        }

        if (($story = API::getStory($r, $params['story_id'])) instanceof JsonResponse) {
            return $story;
        }

        try {
            if (!$swipe->detachStory($params)) {
                $r->messages[] = (object) [
                    'type' => 'error',
                    'message' => 'This story is not attached to this swipe',
                ];
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => 'Could not detach story from swipe',
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => 'Story detached from swipe',
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }
}
