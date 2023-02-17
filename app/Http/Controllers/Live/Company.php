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
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
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
            'token' => ['nullable', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompany($r, $company_id)) instanceof JsonResponse) {
            return $company;
        }

        $stream = new mLiveStreams();
        $avatar = $company->getAvatar();
        $logo = $company->getLogo();

        $r->data = (object) [
            "stream_id" => $stream->getLatestStreamID($company->id),
            "avatar" => match (isset($params['token'])) {
                true => $avatar,
                default => (object) [
                    'url' => $avatar->url,
                    'mime' => $avatar->mime,
                ]
            },
            "logo" => match (isset($params['token'])) {
                true => $logo,
                default => (object) [
                    'url' => $logo->url,
                    'mime' => $logo->mime,
                ]
            },
            "primary_color" => $company->primary_color,
            "cta_color" => $company->cta_color,
            "accent_color" => $company->accent_color,
            "text_chat_color" => $company->text_chat_color,
            "font" => $company->font,
            "stories_is_embedded" => $company->stories_is_embedded,
            "livestream_autoopen" => $company->livestream_autoopen,
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doUpdateCompanySettings(Request $request, $company_id): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid'],
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
            'primary_color' => ['nullable', 'string', 'regex:/^[a-fA-F0-9]{6}$/'],
            'cta_color' => ['nullable', 'string', 'regex:/^[a-fA-F0-9]{6}$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^[a-fA-F0-9]{6}$/'],
            'text_chat_color' => ['nullable', 'string', 'regex:/^[a-fA-F0-9]{6}$/'],
            'font' => ['nullable', 'integer', 'min:0', 'max:23'],
            'stories_is_embedded' => ['nullable', 'boolean'],
            'livestream_autoopen' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'string', 'uuid', 'exists:medias,id'],
            'logo' => ['nullable', 'string', 'uuid', 'exists:medias,id'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompany($r, $company_id)) instanceof JsonResponse) {
            return $company;
        }

        $params['primary_color'] = isset($params['primary_color']) ? $params['primary_color'] : $company->primary_color;
        $params['cta_color'] = isset($params['cta_color']) ? $params['cta_color'] : $company->cta_color;
        $params['accent_color'] = isset($params['accent_color']) ? $params['accent_color'] : $company->accent_color;
        $params['text_chat_color'] = isset($params['text_chat_color']) ? $params['text_chat_color'] : $company->text_chat_color;
        $params['font'] = isset($params['font']) ? intval($params['font']) : $company->font;
        $params['stories_is_embedded'] = isset($params['stories_is_embedded']) ? filter_var($params['stories_is_embedded'], FILTER_VALIDATE_BOOLEAN) : $company->stories_is_embedded;
        $params['livestream_autoopen'] = isset($params['livestream_autoopen']) ? filter_var($params['livestream_autoopen'], FILTER_VALIDATE_BOOLEAN) : $company->livestream_autoopen;
        $params['avatar'] = isset($params['avatar']) ? $params['avatar'] : $company->avatar;
        $params['logo'] = isset($params['logo']) ? $params['logo'] : $company->logo;

        try {
            $company->primary_color = $params['primary_color'];
            $company->cta_color = $params['cta_color'];
            $company->accent_color = $params['accent_color'];
            $company->text_chat_color = $params['text_chat_color'];
            $company->font = $params['font'];
            $company->stories_is_embedded = $params['stories_is_embedded'];
            $company->livestream_autoopen = $params['livestream_autoopen'];
            $company->avatar = $params['avatar'];
            $company->logo = $params['logo'];
            $company->save();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => 'Failed to update company settings.',
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => 'Company settings updated successfully.',
        ];
        $r->data = (object) [
            'updated_at' => now()
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }
}
