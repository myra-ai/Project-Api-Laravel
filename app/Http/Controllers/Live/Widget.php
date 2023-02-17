<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class Widget extends API
{
    public function getWidget(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
            'company_id' => ['required', 'string', 'size:36', 'uuid'],
            'stream_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'swipe_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'story_id' => ['nullable', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompany($r, $params['company_id'])) instanceof JsonResponse) {
            return $company;
        }

        $params['company_id'] = isset($params['company_id']) ? trim($params['company_id']) : $company->id;
        $params['stream_id'] = isset($params['stream_id']) ? trim($params['stream_id']) : null;
        $params['swipe_id'] = isset($params['swipe_id']) ? trim($params['swipe_id']) : null;
        $params['story_id'] = isset($params['story_id']) ? trim($params['story_id']) : null;

        $dataset = [];

        if (isset($params['company_id'])) {
            $dataset['data-company-id'] = $params['company_id'];
        }
        if (isset($params['stream_id'])) {
            $dataset['data-stream-id'] = $params['stream_id'];
        }
        if (isset($params['swipe_id'])) {
            $dataset['data-swipe-id'] = $params['swipe_id'];
        }
        if (isset($params['story_id'])) {
            $dataset['data-story-id'] = $params['story_id'];
        }

        $dataset = implode(' ', array_map(
            function ($v, $k) {
                return sprintf('%s="%s"', $k, $v);
            },
            $dataset,
            array_keys($dataset)
        ));

        $r->data = (object) [
            'widget' => preg_replace('/\s+/', ' ', '<div id="widget"></div><script type="application/javascript" src="https://cdn.gobliver.com/widget/js/main.js" ' . $dataset . ' async></script>'),
            'embed' => preg_replace('/\s+/', ' ', '<div id="widget"></div><script type="application/javascript" src="https://cdn.gobliver.com/widget/js/main.js" ' . $dataset . ' data-force-embed="true" async></script>'),
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }
}
