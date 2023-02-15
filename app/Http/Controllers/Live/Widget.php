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
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
            'company_id' => ['required', 'string', 'size:36', 'uuid'],
            'stream_id' => ['nullable', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompany($r, $params['company_id'])) instanceof JsonResponse) {
            return $company;
        }

        $r->data = (object) [
            'js' => '<div id="widget"></div><script type="application/javascript" src="' . asset("js/core/widget.js") . '" data-company-id="' . $company->id . '" async></script>',
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }
}
