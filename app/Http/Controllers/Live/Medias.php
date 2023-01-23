<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use App\Rules\strBoolean;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class Medias extends API
{
    public function doUploadMediaByFile(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid'],
            'file' => ['required', 'file', 'mimes:jpeg,jpg,png,gif,mp4,mov,avi,webm,webp,heic,heif'],
            'is_thumbnail' => ['nullable', new strBoolean],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompany($r, $company_id)) instanceof JsonResponse) {
            return $company;
        }

        if (($media = API::registerMediaFromFile($params['file'], $params['is_thumbnail'], $r)) instanceof JsonResponse) {
            return $media;
        }

        $r->data = $media;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doUploadMediaByUrl(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid'],
            'url' => ['required', 'string', 'url'],
            'is_thumbnail' => ['nullable', new strBoolean],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompany($r, $company_id)) instanceof JsonResponse) {
            return $company;
        }

        if (($media = API::registerMediaFromUrl($params['url'], $params['is_thumbnail'], $r)) instanceof JsonResponse) {
            return $media;
        }

        $r->data = $media;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doDeleteMedia(Request $request, ?string $media_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'media_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['media_id' => $media_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($media = API::getMedia($r, $params['media_id'])) instanceof JsonResponse) {
            return $media;
        }

        try {
            $media->deleted_at = now()->format('Y-m-d H:i:s');
            $media->save();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to delete media.'),
            ];
            if (config('app.debug') === true) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $r->data = $media;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getMediaByID(Request $request, ?string $media_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'media_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['media_id' => $media_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($media = API::getMedia($r, $media_id)) instanceof JsonResponse) {
            return $media;
        }

        $r->data = $media;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getMediaRawByID(Request $request, ?string $media_id = null): mixed
    {
        if (($params = API::doValidate($r, [
            'media_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['media_id' => $media_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($media = API::getMedia($r, $media_id)) instanceof JsonResponse) {
            return $media;
        }

        if (($cdn = API::doSyncMediaWithCDN($media,$r)) instanceof JsonResponse) {
            return $cdn;
        }

        if ($cdn !== false) {
            return redirect()->away($cdn);
        }

        $file = Storage::disk('public')->get($media->path);

        if ($file === false) {
            $r->messages[] = [
                'type' => 'error',
                'message' => __('File not found.'),
            ];
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        return response($file, Response::HTTP_OK, [
            'Content-Type' => $media->mime,
            'Content-Length' => $media->size,
            'Content-Disposition' => 'inline; filename="' . $media->original_name . '"',
        ]);
    }

    public function getThumbnailRawByMediaID(Request $request, ?string $media_id = null): mixed
    {
        if (($params = API::doValidate($r, [
            'media_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['media_id' => $media_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($media = API::getMedia($r, $media_id)) instanceof JsonResponse) {
            return $media;
        }

        $thumbnail = $media->getThumbnail();

        if ($thumbnail === null) {
            $r->messages[] = [
                'type' => 'error',
                'message' => __('Thumbnail not found.'),
            ];
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        if (($cdn = API::doSyncMediaWithCDN($thumbnail,$r)) instanceof JsonResponse) {
            return $cdn;
        }

        if ($cdn !== false) {
            return redirect()->away($cdn);
        }

        $file = Storage::disk('public')->get($thumbnail->path);

        if ($file === false) {
            $r->messages[] = [
                'type' => 'error',
                'message' => __('File not found.'),
            ];
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        return response($file, Response::HTTP_OK, [
            'Content-Type' => $thumbnail->mime,
            'Content-Length' => $thumbnail->size,
            'Content-Disposition' => 'inline; filename="' . $thumbnail->original_name . '"',
        ]);
    }

    public function getMediaRawByPath(Request $request, ?string $path = null): mixed
    {
        if (($params = API::doValidate($r, [
            'path' => ['required', 'string', 'min:4', 'max:400'],
        ], $request->all(), ['path' => $path])) instanceof JsonResponse) {
            return $params;
        }

        if (($media = API::getMediaByPath($r, $path)) instanceof JsonResponse) {
            return $media;
        }

        $file = Storage::disk('public')->get($media->path);

        if ($file === false) {
            $r->messages[] = [
                'type' => 'error',
                'message' => __('File not found.'),
            ];
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        return response($file, Response::HTTP_OK, [
            'Content-Type' => $media->mime,
            'Content-Length' => $media->size,
            'Content-Disposition' => 'inline; filename="' . $media->original_name . '"',
        ]);
    }
}
