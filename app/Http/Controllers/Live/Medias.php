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
    public function doUploadMediaByFile(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/'],
            'file' => ['required', 'file', 'mimes:jpeg,jpg,png,gif,mp4,mov,avi,webm,webp,heic,heif'],
            'alt' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9_\-\.@#\$ ,;]+$/u', 'max:100'],
            'legend' => ['nullable', 'string', 'max:1000'],
            'type' => ['nullable', 'integer', 'min:1', 'max:' . count(API::$media_types)],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompanyByToken($params['token'], $r)) instanceof JsonResponse) {
            return $company;
        }

        $params['type'] = isset($params['type']) ? $params['type'] : null;
        $params['alt'] = isset($params['alt']) ? trim($params['alt']) : null;
        $params['legend'] = isset($params['legend']) ? trim($params['legend']) : null;

        if (($media = API::registerMediaFromFile($company->id, $params['file'], $params['type'], $params['alt'], $params['legend'], $r)) instanceof JsonResponse) {
            return $media;
        }

        $r->data = $media;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doUploadMediaByUrl(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/'],
            'url' => ['required', 'string', 'url'],
            'alt' => ['nullable', 'string', 'regex:/^[a-zA-Z0-9_\-\.@#\$ ,;]+$/u', 'max:100'],
            'legend' => ['nullable', 'string', 'max:1000'],
            'type' => ['nullable', 'integer', 'min:1', 'max:' . count(API::$media_types)],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        if (($company = API::getCompanyByToken($params['token'], $r)) instanceof JsonResponse) {
            return $company;
        }

        $params['type'] = isset($params['type']) ? $params['type'] : null;
        $params['alt'] = isset($params['alt']) ? trim($params['alt']) : null;
        $params['legend'] = isset($params['legend']) ? trim($params['legend']) : null;

        if (($media = API::registerMediaFromUrl($company->id, $params['url'], $params['type'], $params['alt'], $params['legend'], $r)) instanceof JsonResponse) {
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
            $message = (object) [
                'type' => 'error',
                'message' => __('File not found.'),
            ];
            if (config('app.debug') === true) {
                $message->debug = 'File is deleted.';
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        if ($media->s3_available === null) {
            SyncWithS3::dispatch($media);
        } else {
            return redirect()->away(API::getMediaCdnUrl($media->path));
        }

        $file = Storage::disk('public')->get($media->path);
        if ($file === false || $file === null) {
            $message = (object) [
                'type' => 'error',
                'message' => __('File not found.'),
            ];
            if (config('app.debug') === true) {
                $message->debug = 'File not found on disk.';
                $message->path = $media->path;
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        return response($file, Response::HTTP_OK, [
            'Content-Type' => $media->mime,
            'Content-Length' => $media->size,
            'Content-Disposition' => 'inline; filename="' . $media->file_name . '.' . $media->extension . '"',
            'x-amz-meta-media_id' => $media->id,
            'x-amz-meta-checksum' => $media->checksum,
            'x-amz-meta-media_type' => $media->type,
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
            $message = (object) [
                'type' => 'error',
                'message' => __('File not found.'),
            ];
            if (config('app.debug') === true) {
                $message->debug = 'File is deleted.';
            }
            $r->messages[] = $message;
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

        if ($thumbnail->deleted_at !== null) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Thumbnail not found.'),
            ];
            if (config('app.debug') === true) {
                $message->debug = __('Thumbnail is deleted.');
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        if ($thumbnail->s3_available === null) {
            SyncWithS3::dispatch($thumbnail);
        } else {
            return redirect()->away(API::getMediaCdnUrl($thumbnail->path));
        }

        $file = Storage::disk('public')->get($thumbnail->path);
        if ($file === false || $file === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('File not found.'),
            ];
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        return response($file, Response::HTTP_OK, [
            'Content-Type' => $thumbnail->mime,
            'Content-Length' => $thumbnail->size,
            'Content-Disposition' => 'inline; filename="' . $thumbnail->file_name . '.' . $thumbnail->extension . '"',
            'x-amz-meta-media_id' => $thumbnail->id,
            'x-amz-meta-checksum' => $thumbnail->checksum,
            'x-amz-meta-media_type' => $thumbnail->type,
        ]);
    }

    public function getMediaRawByPath(Request $request, ?string $path = null): mixed
    {
        if (($params = API::doValidate($r, [
            'path' => ['required', 'string', 'min:16', 'max:512'],
        ], $request->all(), ['path' => $path])) instanceof JsonResponse) {
            return $params;
        }

        if (($media = API::getMediaByPath($r, $path)) instanceof JsonResponse) {
            return $media;
        }

        if ($media->deleted_at !== null) {
            $message = (object) [
                'type' => 'error',
                'message' => __('File not found.'),
            ];
            if (config('app.debug') === true) {
                $message->debug = 'File is deleted.';
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        if ($media->s3_available === null) {
            SyncWithS3::dispatch($media);
        } else {
            return redirect()->away(API::getMediaCdnUrl($media->path));
        }

        $file = Storage::disk('public')->get($media->path);
        if ($file === false || $file === null) {
            $message = (object) [
                'type' => 'error',
                'message' => __('File not found.'),
            ];
            if (config('app.debug') === true) {
                $message->debug = 'File not found on disk.';
                $message->path = $media->path;
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        return response($file, Response::HTTP_OK, [
            'Content-Type' => $media->mime,
            'Content-Length' => $media->size,
            'Content-Disposition' => 'inline; filename="' . $media->file_name . '.' . $media->extension . '"',
            'x-amz-meta-media_id' => $media->id,
            'x-amz-meta-checksum' => $media->checksum,
            'x-amz-meta-media_type' => $media->type,
        ]);
    }

    public function doOptimizeMedia(Request $request, ?string $media_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'media_id' => ['required', 'string', 'min:4', 'max:400'],
            'width' => ['nullable', 'integer', 'min:1', 'max:4000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:4000'],
            'mode' => ['nullable', 'string', 'in:fit,fill,stretch'],
            'keep_asp_ratio' => ['nullable', new strBoolean],
            'quality' => ['nullable', 'integer', 'min:1', 'max:100'],
            'blur' => ['nullable', new strBoolean],
        ], $request->all(), ['media_id' => $media_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($media = API::getMedia($media_id, $r)) instanceof JsonResponse) {
            return $media;
        }

        $params['width'] = isset($params['width']) ? intval($params['width']) : 96;
        $params['height'] = isset($params['height']) ? intval($params['height']) : 96;
        $params['mode'] = isset($params['mode']) ? $params['mode'] : 'fit';
        $params['keep_asp_ratio'] = isset($params['keep_asp_ratio']) ? filter_var($params['keep_asp_ratio'], FILTER_VALIDATE_BOOLEAN) : true;
        $params['quality'] = isset($params['quality']) ? intval($params['quality']) : 80;
        $params['blur'] = isset($params['blur']) ? filter_var($params['blur'], FILTER_VALIDATE_BOOLEAN) : false;
    }
}
