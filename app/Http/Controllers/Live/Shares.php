<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use App\Models\LiveStreamComments as mLiveStreamComments;
use App\Rules\strBoolean;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class Shares extends API
{
    public function createShare(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'story_id' => ['nullable', 'string', 'size:36', 'uuid'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

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
            $qry = new mLiveStreamComments();
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

        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getShare(Request $request, ?string $share_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'share_id' => ['nullable', 'string', 'size:36', 'uuid'],
        ], $request->all(),['share_id'=>$share_id])) instanceof JsonResponse) {
            return $params;
        }

        $params['share_id'] = isset($params['share_id']) ?: trim($params['share_id']);

        try {

        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to get share.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }
}
