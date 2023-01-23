<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Rules\strBoolean;

class Widget extends API
{
    public function getWidgetStream(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompany($r, $params)) instanceof JsonResponse) {
            return $company;
        }

        $r->data = (object) [
            'js' => '<div id="live-widget"></div><script type="application/javascript" src="' . asset("js/core/live-widget.js") . '" data-company-id="' . $company->id . '" async></script>',
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getWidgetStory(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompany($r, $params)) instanceof JsonResponse) {
            return $company;
        }

        $r->data = (object) [
            'js' => '<div id="stories-widget"></div><script type="application/javascript" src="' . asset("js/core/storie-widget.js") . '" data-company-id="' . $company->id . '" async></script>',
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }
}
