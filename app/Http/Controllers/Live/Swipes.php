<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use App\Models\Swipes as mSwipes;
use App\Rules\strBoolean;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class Swipes extends API
{
    public function doCreate(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
            'company_id' => ['required', 'string', 'size:36', 'uuid', 'exists:livestream_companies,id'],
            'title' => ['required', 'string', 'max:60'],
            'status' => ['nullable', 'string', 'in:archived,draft,published'],
            'published' => ['nullable', new strBoolean],
            'story_id' => ['nullable', 'string', 'size:36', 'uuid', 'exists:stories,id'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        $params['title'] = isset($params['title']) ? trim($params['title']) : null;
        $params['status'] = isset($params['status']) ? trim($params['status']) : 'draft';

        try {
            $swipes = new mSwipes();
            $swipes->createSwipe($params);
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
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doUpdate(Request $request, ?string $swipe_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
            'swipe_id' => ['required', 'string', 'size:36', 'uuid'],
            'title' => ['required', 'string', 'max:60'],
            'status' => ['nullable', 'string', 'in:archived,draft,published'],
            'published' => ['nullable', new strBoolean],
        ], $request->all(), ['swipe_id' => $swipe_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($swipe = API::getSwipe($params['swipe_id'], $r)) instanceof JsonResponse) {
            return $swipe;
        }

        $params['title'] = isset($params['title']) ? trim($params['title']) : null;
        $params['status'] = isset($params['status']) ? trim($params['status']) : null;
        $params['published'] = isset($params['status']) ? trim($params['status']) : null;

        try {
            $swipe->updateSwipe($params);
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
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doDelete(Request $request, ?string $swipe_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
            'swipe_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['swipe_id' => $swipe_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($swipe = API::getSwipe($params['swipe_id'], $r)) instanceof JsonResponse) {
            return $swipe;
        }

        try {
            $swipe->deleteSwipe($params['swipe_id']);
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
            'message' => 'Swipe deleted',
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }


    public function getListByCompanyId(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid', 'exists:livestream_companies,id'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($swipes = API::getSwipes($params['company_id'], $r)) instanceof JsonResponse) {
            return $swipes;
        }

        $r->data = $swipes;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getById(Request $request, ?string $swipe_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'swipe_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['swipe_id' => $swipe_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($swipe = API::getSwipe($params['swipe_id'], $r)) instanceof JsonResponse) {
            return $swipe;
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
            $swipe->attachStory($params);
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
            $swipe->detachStory($params);
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
}
