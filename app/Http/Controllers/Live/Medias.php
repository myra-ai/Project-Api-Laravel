<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use App\Jobs\SyncWithS3;
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
            'alt' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9_\-\.@#\$ ,;]+$/u', 'max:110'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_thumbnail' => ['nullable', new strBoolean],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompany($r, $company_id)) instanceof JsonResponse) {
            return $company;
        }

        if (!isset($params['is_thumbnail'])) {
            $params['is_thumbnail'] = false;
        }

        if (!isset($params['alt'])) {
            $params['alt'] = '';
        }

        if (!isset($params['description'])) {
            $params['description'] = '';
        }

        if (($media = API::registerMediaFromFile($params['file'], $params['is_thumbnail'], $params['alt'], $params['description'], $r)) instanceof JsonResponse) {
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
            'alt' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9_\-\.@#\$ ,;]+$/u', 'max:110'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_thumbnail' => ['nullable', new strBoolean],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompany($r, $company_id)) instanceof JsonResponse) {
            return $company;
        }

        if (!isset($params['is_thumbnail'])) {
            $params['is_thumbnail'] = false;
        }

        if (!isset($params['alt'])) {
            $params['alt'] = '';
        }

        if (!isset($params['description'])) {
            $params['description'] = '';
        }

        if (($media = API::registerMediaFromUrl($params['url'], $params['is_thumbnail'], $params['alt'], $params['description'], $r)) instanceof JsonResponse) {
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

        if (($media = API::getMedia($params['media_id'], $r)) instanceof JsonResponse) {
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

        if (($media = API::getMedia($media_id, $r)) instanceof JsonResponse) {
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

        if (($media = API::getMedia($media_id, $r)) instanceof JsonResponse) {
            return $media;
        }

        if ($media->deleted_at !== null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('File not found.'),
            ];
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        if ($media->s3_available === null) {
            SyncWithS3::dispatch($media);
        }

        $file = Storage::disk('public')->get($media->path);

        if ($file === false) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('File not found.'),
            ];
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        return response($file, Response::HTTP_OK, [
            'Content-Type' => $media->mime,
            'Content-Length' => $media->size,
            'Content-Disposition' => 'inline; filename="' . $media->hash . '.' . $media->extension . '"',
        ]);
    }

    public function getThumbnailRawByMediaID(Request $request, ?string $media_id = null): mixed
    {
        if (($params = API::doValidate($r, [
            'media_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['media_id' => $media_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($media = API::getMedia($media_id, $r)) instanceof JsonResponse) {
            return $media;
        }

        if ($media->deleted_at !== null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('File not found.'),
            ];
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        if ($media->s3_available === null) {
            SyncWithS3::dispatch($media);
        }

        $thumbnail = $media->getThumbnail();

        if ($thumbnail === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Thumbnail not found.'),
            ];
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        $file = Storage::disk('public')->get($thumbnail->path);

        if ($file === false) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('File not found.'),
            ];
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        return response($file, Response::HTTP_OK, [
            'Content-Type' => $thumbnail->mime,
            'Content-Length' => $thumbnail->size,
            'Content-Disposition' => 'inline; filename="' . $media->hash . '.' . $media->extension . '"',
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
            $r->messages[] = (object) [
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

    public function doOptimizeMedia(Request $request, ?string $media_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'media_id' => ['required', 'string', 'min:4', 'max:400'],
        ], $request->all(), ['media_id' => $media_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($media = API::getMedia($media_id, $r)) instanceof JsonResponse) {
            return $media;
        }

        $params['thumbnail_width'] = isset($params['thumbnail_width']) ? intval($params['thumbnail_width']) : 96;
        $params['thumbnail_height'] = isset($params['thumbnail_height']) ? intval($params['thumbnail_height']) : 96;
        $params['thumbnail_mode'] = isset($params['thumbnail_mode']) ? $params['thumbnail_mode'] : 'fit';
        $params['thumbnail_keep_asp_ratio'] = isset($params['thumbnail_keep_asp_ratio']) ? filter_var($params['thumbnail_keep_asp_ratio'], FILTER_VALIDATE_BOOLEAN) : true;
        $params['thumbnail_quality'] = isset($params['thumbnail_quality']) ? intval($params['thumbnail_quality']) : 80;
        $params['thumbnail_blur'] = isset($params['thumbnail_blur']) ? filter_var($params['thumbnail_blur'], FILTER_VALIDATE_BOOLEAN) : false;
    }
}
