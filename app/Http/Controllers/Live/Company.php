<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use App\Models\LiveStreamCompanies as mLiveStreamCompanies;
use App\Models\LiveStreams as mLiveStreams;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class Company extends API
{
    public function getCompanyByID(Request $request, $company_id): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompany($r, $company_id)) instanceof JsonResponse) {
            return $company;
        }

        $r->data = $company;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getCompanySettings(Request $request, $company_id): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompany($r, $company_id)) instanceof JsonResponse) {
            return $company;
        }

        $stream = new mLiveStreams();

        $r->data = (object) [
            "stream_id" => $stream->getLatestStreamID($company->id),
            "avatar" => API::getMediaUrl($company->avatar),
            "logo" => API::getMediaUrl($company->logo),
            "primary_color" => $company->primary_color,
            "cta_color" => $company->cta_color,
            "accent_color" => $company->accent_color,
            "text_chat_color" => $company->text_chat_color,
            "stories_is_embedded" => $company->stories_is_embedded,
            "livestream_autoopen" => $company->livestream_autoopen,
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }
}
