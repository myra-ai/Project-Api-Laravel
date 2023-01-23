<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use App\Models\LiveStreamSettings as mLiveStreamSettings;
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

        $settings = null;

        try {
            $settings = mLiveStreamSettings::where('company_id', '=', $company_id)->first();
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to get company settings.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = [
                    'message' => $e->getMessage(),
                ];
            }
            $r->messages[] = (object) $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($settings === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Company settings not found.'),
            ];
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        if (isset($settings->avatar)) {
            $settings->avatar = API::getMediaUrl($settings->avatar);
        }
        if (isset($settings->logo)) {
            $settings->logo = API::getMediaUrl($settings->logo);
        }

        $stream = new mLiveStreams();
        // $settings->stream_id = $stream->getLatestStreamID($company_id);
        $settings->stream_id = '1db4344c-43ed-41a8-a575-d54fe81a7ffa';

        $r->data = $settings;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }
}
