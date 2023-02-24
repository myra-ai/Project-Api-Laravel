<?php

namespace App\Http\Controllers;

use App\Rules\strBoolean;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Languages extends API
{
    public function getTranslations(Request $request, ?string $language): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'language' => ['required', 'string', 'min:2', 'max:5', 'regex:/^[a-zA-Z]{2}(-[a-zA-Z]{2})?$/'],
        ], $request->all(), ['language' => $language])) instanceof JsonResponse) {
            return $params;
        }

        $params['language'] = isset($params['language']) ? $params['language'] : 'en';

        $file = Storage::disk('languages')->path($params['language'] . '.json');

        if (!file_exists($file)) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Language not supported.'),
            ];
            if (config('app.debug')) {
                $message->details = $params['language'];
                $message->path = $file;
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $content = Storage::disk('languages')->get($params['language'] . '.json');

        try {
            $translations = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to get language translations.'),
            ];
            if (config('app.debug')) {
                $message->details = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Language translations retrieved successfully.'),
        ];
        $r->data = (object) [
            'translations' => $translations,
            'checksum' => sha1($content),
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function downloadTranslations(Request $request, ?string $language)
    {
        if (($params = API::doValidate($r, [
            'language' => ['required', 'string', 'min:2', 'max:5', 'regex:/^[a-zA-Z]{2}(-[a-zA-Z]{2})?$/'],
        ], $request->all(), ['language' => $language])) instanceof JsonResponse) {
            return $params;
        }

        $params['language'] = isset($params['language']) ? $params['language'] : 'en';

        $file = Storage::disk('languages')->path($params['language'] . '.json');

        if (!file_exists($file)) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Language not supported.'),
            ];
            if (config('app.debug')) {
                $message->details = $params['language'];
                $message->path = $file;
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        return response()->download($file, $params['language'] . '.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    public function getAvailableLanguages(Request $request): JsonResponse
    {
        $r = API::INIT();

        $languages = [];

        try {
            $languages = Storage::disk('languages')->files();
        } catch (\Exception $e) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Failed to get available languages.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $languages = array_map(function ($language) {
            $language = basename($language);
            $lang = Str::replaceLast('.json', '', $language);
            return (object) [
                'language' => $lang,
                'checksum' => sha1_file(Storage::disk('languages')->path($language)),
            ];
        }, $languages);

        $r->data = $languages;
        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Available languages retrieved successfully.'),
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }
}
