<?php

namespace App\Http\Controllers;

use App\Http\Controllers\API;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class Links extends API
{
    public function getLink(Request $request, ?string $link_id = null): object
    {
        if (($params = API::doValidate($r, [
            'link_id' => ['required', 'string'],
        ], $request->all(), ['link_id' => $link_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($link = API::validateLink($r, $params)) instanceof JsonResponse) {
            return $link;
        }

        try {
            $link->increment('clicks');
        } catch (\Exception $e) {
            $message = (object)[
                'type' => 'error',
                'message' => __('Failed to redirect link.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = [
                    'message' => $e->getMessage(),
                ];
            }
            $r->messages[] = (object) $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return redirect($link->url);
    }
}
