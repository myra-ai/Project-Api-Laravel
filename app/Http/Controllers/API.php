<?php

namespace App\Http\Controllers;

use App\Http\StreamServices\AntMedia\Stream as AntMediaStream;
use App\Http\StreamServices\Mux\Stream as MuxStream;
use App\Models\Links as mLinks;
use App\Models\LiveStreamCompanies as mLiveStreamCompanies;
use App\Models\LiveStreamCompanyTokens as mLiveStreamCompanyTokens;
use App\Models\LiveStreamCompanyUsers as mLiveStreamCompanyUsers;
use App\Models\LiveStreamMedias as mLiveStreamMedias;
use App\Models\LiveStreamMetrics as mLiveStreamMetrics;
use App\Models\LiveStreamProductGroups as mLiveStreamProductGroups;
use App\Models\LiveStreamProducts as mLiveStreamProducts;
use App\Models\LiveStreamProductsImages as mLiveStreamProductsImages;
use App\Models\LiveStreams as mLiveStreams;
use App\Models\Stories as mStories;
use App\Models\StoryMetrics as mStoryMetrics;
use App\Models\SwipeMetrics as mSwipeMetrics;
use Browser;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use GeoIp2\Database\Reader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Image;
use Ramsey\Uuid\Uuid;

class API extends Controller
{
    const MEDIA_TYPE_UNKNOWN = 0;
    const MEDIA_TYPE_IMAGE = 1;
    const MEDIA_TYPE_IMAGE_THUMBNAIL = 2;
    const MEDIA_TYPE_VIDEO = 3;
    const MEDIA_TYPE_AUDIO = 4;
    const MEDIA_TYPE_DOCUMENT = 5;
    const MEDIA_TYPE_ARCHIVE = 6;

    const CACHE_TIME = 3;
    const COMMENTS_CACHE_TIME = 1;
    const LIVESTREAM_CACHE_TIME = 3;
    const METRICS_CACHE_TIME = 30;
    const PRODUCTS_CACHE_TIME = 3;
    const STORY_CACHE_TIME = 3;

    const STORY_STATUS_DRAFT = 0;
    const STORY_STATUS_ACTIVE = 1;
    const STORY_STATUS_ARCHIVE = 2;
    const STORY_STATUS_DELETED = 3;

    const STORY_NOT_PUBLISHED = 0;
    const STORY_PUBLISHED = 1;

    const MEDIA_RAW_BY_ID_URL = '/media/raw/id/';
    const MEDIA_THUMBNAIL_RAW_BY_ID_URL = '/media/raw/thumbnail/';
    const MEDIA_RAW_BY_PATH_URL = '/media/raw/';
    const LINK_URL = '/l/';

    public static array $media_types = [
        self::MEDIA_TYPE_UNKNOWN => 'unknown',
        self::MEDIA_TYPE_IMAGE => 'image',
        self::MEDIA_TYPE_IMAGE_THUMBNAIL => 'image_thumbnail',
        self::MEDIA_TYPE_VIDEO => 'video',
        self::MEDIA_TYPE_AUDIO => 'audio',
        self::MEDIA_TYPE_DOCUMENT => 'document',
        self::MEDIA_TYPE_ARCHIVE => 'archive',
    ];

    public static array $valid_currencies = [
        'BRL',
        'USD',
        'EUR',
        'GBP',
        'JPY',
        'AUD',
        'CAD',
        'CHF',
        'CNY',
        'HKD',
        'NZD',
        'SEK',
        'SGD'
    ];

    public static function INIT()
    {
        return (object) [
            'success' => false,
            'messages' => [],
            'data' => null,
        ];
    }

    public static function getMediaUrl(?string $media_id = null): ?string
    {
        if ($media_id === null || empty($media_id)) {
            return null;
        }
        return url(self::MEDIA_RAW_BY_ID_URL . $media_id);
    }

    public static function getMediaThumbnailUrl(?string $media_id = null): ?string
    {
        if ($media_id === null || empty($media_id)) {
            return null;
        }
        return url(self::MEDIA_THUMBNAIL_RAW_BY_ID_URL . $media_id);
    }

    public static function getMediaCdnUrl(string $path)
    {
        return url(env('CDN_URL') . $path);
    }

    public static function getLinkUrl(?string $link_url = null): ?string
    {
        if ($link_url === null || empty($link_url)) {
            return null;
        }
        return url(self::LINK_URL . $link_url);
    }

    public static function getExtensionByMime(string $mime): string
    {
        return match (strtolower($mime)) {
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/bmp' => 'bmp',
            'image/vnd.microsoft.icon' => 'ico',
            'image/tiff' => 'tiff',
            'image/x-icon' => 'ico',
            'image/vnd.wap.wbmp' => 'wbmp',
            'image/x-xbitmap' => 'xbm',
            'image/x-xpixmap' => 'xpm',
            'image/x-xwindowdump' => 'xwd',
            'image/x-portable-anymap' => 'pnm',
            'image/x-portable-bitmap' => 'pbm',
            'video/x-msvideo' => 'avi',
            'video/mpeg' => 'mpeg',
            'video/quicktime' => 'mov',
            'video/x-ms-wmv' => 'wmv',
            'video/x-ms-asf' => 'asf',
            'video/x-ms-asf-plugin' => 'asx',
            'video/x-msvideo' => 'avi',
            'video/x-sgi-movie' => 'movie',
            'video/x-matroska' => 'mkv',
            'video/x-mng' => 'mng',
            'video/x-ms-wm' => 'wm',
            'video/flv' => 'flv',
            'video/x-flv' => 'flv',
            'video/mp4' => 'mp4',
            'video/ogg' => 'ogv',
            'video/webm' => 'webm',
            'video/x-m4v' => 'm4v',
            'video/3gpp' => '3gp',
            'video/3gpp2' => '3g2',
            'audio/mpeg' => 'mp3',
            'audio/x-ms-wma' => 'wma',
            'audio/x-ms-wax' => 'wax',
            'audio/x-ms-wmv' => 'wmv',
            'audio/x-ms-wvx' => 'wvx',
            'audio/x-ms-wm' => 'wm',
            'audio/x-ms-asx' => 'asx',
            'audio/x-ms-asf' => 'asf',
            'audio/x-aiff' => 'aif',
            'audio/x-mpegurl' => 'm3u',
            'audio/x-pn-realaudio' => 'ram',
            'audio/x-pn-realaudio-plugin' => 'rpm',
            'audio/x-realaudio' => 'ra',
            'audio/x-wav' => 'wav',
            'audio/x-matroska' => 'mka',
            'audio/flac' => 'flac',
            'audio/mp4' => 'mp4',
            'audio/ogg' => 'ogg',
            'audio/webm' => 'webm',
            'audio/midi' => 'mid',
            'audio/midi' => 'midi',
            'audio/midi' => 'kar',
            'audio/midi' => 'rmi',
            'audio/x-m4a' => 'm4a',
            default => throw new \Exception('Unknown image type: ' . $mime),
        };
    }

    public static function getTypeByMime(string $mime): int
    {
        return match (strtolower($mime)) {
            'image/jpeg' => self::MEDIA_TYPE_IMAGE,
            'image/jpg' => self::MEDIA_TYPE_IMAGE,
            'image/png' => self::MEDIA_TYPE_IMAGE,
            'image/gif' => self::MEDIA_TYPE_IMAGE,
            'image/webp' => self::MEDIA_TYPE_IMAGE,
            'image/svg+xml' => self::MEDIA_TYPE_IMAGE,
            'image/bmp' => self::MEDIA_TYPE_IMAGE,
            'image/vnd.microsoft.icon' => self::MEDIA_TYPE_IMAGE,
            'image/tiff' => self::MEDIA_TYPE_IMAGE,
            'image/x-icon' => self::MEDIA_TYPE_IMAGE,
            'image/vnd.wap.wbmp' => self::MEDIA_TYPE_IMAGE,
            'image/x-xbitmap' => self::MEDIA_TYPE_IMAGE,
            'image/x-xpixmap' => self::MEDIA_TYPE_IMAGE,
            'image/x-xwindowdump' => self::MEDIA_TYPE_IMAGE,
            'image/x-portable-anymap' => self::MEDIA_TYPE_IMAGE,
            'image/x-portable-bitmap' => self::MEDIA_TYPE_IMAGE,
            'video/x-msvideo' => self::MEDIA_TYPE_VIDEO,
            'video/mpeg' => self::MEDIA_TYPE_VIDEO,
            'video/quicktime' => self::MEDIA_TYPE_VIDEO,
            'video/x-ms-wmv' => self::MEDIA_TYPE_VIDEO,
            'video/x-ms-asf' => self::MEDIA_TYPE_VIDEO,
            'video/x-ms-asf-plugin' => self::MEDIA_TYPE_VIDEO,
            'video/x-msvideo' => self::MEDIA_TYPE_VIDEO,
            'video/x-sgi-movie' => self::MEDIA_TYPE_VIDEO,
            'video/x-matroska' => self::MEDIA_TYPE_VIDEO,
            'video/x-mng' => self::MEDIA_TYPE_VIDEO,
            'video/x-ms-wm' => self::MEDIA_TYPE_VIDEO,
            'video/flv' => self::MEDIA_TYPE_VIDEO,
            'video/x-flv' => self::MEDIA_TYPE_VIDEO,
            'video/mp4' => self::MEDIA_TYPE_VIDEO,
            'video/ogg' => self::MEDIA_TYPE_VIDEO,
            'video/webm' => self::MEDIA_TYPE_VIDEO,
            'video/x-m4v' => self::MEDIA_TYPE_VIDEO,
            'video/3gpp' => self::MEDIA_TYPE_VIDEO,
            'video/3gpp2' => self::MEDIA_TYPE_VIDEO,
            'audio/mpeg' => self::MEDIA_TYPE_AUDIO,
            'audio/x-ms-wma' => self::MEDIA_TYPE_AUDIO,
            'audio/x-ms-wax' => self::MEDIA_TYPE_AUDIO,
            'audio/x-ms-wmv' => self::MEDIA_TYPE_AUDIO,
            'audio/x-ms-wvx' => self::MEDIA_TYPE_AUDIO,
            'audio/x-ms-wm' => self::MEDIA_TYPE_AUDIO,
            'audio/x-ms-asx' => self::MEDIA_TYPE_AUDIO,
            'audio/x-ms-asf' => self::MEDIA_TYPE_AUDIO,
            'audio/x-aiff' => self::MEDIA_TYPE_AUDIO,
            'audio/x-mpegurl' => self::MEDIA_TYPE_AUDIO,
            'audio/x-pn-realaudio' => self::MEDIA_TYPE_AUDIO,
            'audio/x-pn-realaudio-plugin' => self::MEDIA_TYPE_AUDIO,
            'audio/x-realaudio' => self::MEDIA_TYPE_AUDIO,
            'audio/x-wav' => self::MEDIA_TYPE_AUDIO,
            'audio/x-matroska' => self::MEDIA_TYPE_AUDIO,
            'audio/flac' => self::MEDIA_TYPE_AUDIO,
            'audio/mp4' => self::MEDIA_TYPE_AUDIO,
            'audio/ogg' => self::MEDIA_TYPE_AUDIO,
            'audio/webm' => self::MEDIA_TYPE_AUDIO,
            default => throw new \Exception('Unknown type: ' . $mime),
        };
    }

    public static function getFileChecksum(string $file, bool $is_thumbnail): string
    {
        $sha1 = sha1_file($file);
        $sha1 = sha1($sha1 . ($is_thumbnail ? '1' : '0'));
        return substr($sha1, 0, 8) . '-' . substr($sha1, 8, 4) . '-' . substr($sha1, 12, 4) . '-' . substr($sha1, 16, 4) . '-' . substr($sha1, 20, 12);
    }

    public static function getUUIDWithData(mixed ...$data): string
    {
        $data = array_map(function ($item) {
            if (is_array($item)) {
                return implode('-', $item);
            }
            return $item;
        }, $data);
        $data = array_filter($data);
        $data = array_values($data);
        $data = array_map(function ($item) {
            return (string) $item;
        }, $data);
        $sha1 = sha1(implode('-', $data));
        return substr($sha1, 0, 8) . '-' . substr($sha1, 8, 4) . '-' . substr($sha1, 12, 4) . '-' . substr($sha1, 16, 4) . '-' . substr($sha1, 20, 12);
    }

    public static function doGenerateLinkID(int $length = 8): string
    {
        $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_.';
        return substr(str_shuffle(str_repeat($pool, 4)), 0, $length);
    }

    public static function getPrefRange(string $range): array
    {
        $pref_range = (object) [
            'today' => [
                'start' => now()->startOfDay()->format('Y-m-d H:i:s.u'),
                'end' => now()->endOfDay()->format('Y-m-d H:i:s.u'),
            ],
            'yesterday' => [
                'start' => now()->subDays(1)->startOfDay()->format('Y-m-d H:i:s.u'),
                'end' => now()->subDays(1)->endOfDay()->format('Y-m-d H:i:s.u'),
            ],
            'week' => [
                'start' => now()->startOfWeek()->format('Y-m-d H:i:s.u'),
                'end' => now()->endOfWeek()->format('Y-m-d H:i:s.u'),
            ],
            'month' => [
                'start' => now()->startOfMonth()->format('Y-m-d H:i:s.u'),
                'end' => now()->endOfMonth()->format('Y-m-d H:i:s.u'),
            ],
            'year' => [
                'start' => now()->startOfYear()->format('Y-m-d H:i:s.u'),
                'end' => now()->endOfYear()->format('Y-m-d H:i:s.u'),
            ],
        ];

        return $pref_range->{$range} ?? [];
    }

    public static function doValidate(?object &$r = null, array $rules, array ...$fields): mixed
    {
        $r = $r ?? self::INIT();

        try {
            $validator = Validator::make(array_merge(...$fields), $rules);
        } catch (\Exception $e) {
            $r->messages[] = [
                'type' => 'error',
                'message' => __('Could not validate data.'),
            ];
            if (config('app.debug')) {
                $r->messages[] = [
                    'type' => 'debug',
                    'message' => $e->getMessage(),
                ];
            }
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($validator->fails()) {
            $r->messages[] = [
                'type' => 'error',
                'message' => $validator->errors()->first(),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $params = $validator->validated();

        if (isset($params['audio_only']) && $params['audio_only'] !== null) {
            $params['audio_only'] = filter_var($params['audio_only'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($params['pinned']) && $params['pinned'] !== null) {
            $params['pinned'] = filter_var($params['pinned'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($params['is_streammer']) && $params['is_streammer'] !== null) {
            $params['is_streammer'] = filter_var($params['is_streammer'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($params['group_attached']) && $params['group_attached'] !== null) {
            $params['group_attached'] = filter_var($params['group_attached'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($params['get_product']) && $params['get_product'] !== null) {
            $params['get_product'] = filter_var($params['get_product'], FILTER_VALIDATE_BOOLEAN);
        }

        return $params;
    }

    /**
     * Get Story from database
     * 
     * @param object $r
     * @param array $params
     * 
     * @return object
     */
    public static function getStory(?object &$r = null, string $story_id): object
    {
        $r = $r ?? self::INIT();

        $story = null;

        try {
            $story = Cache::remember('story_by_id_' . $story_id, now()->addSeconds(self::CACHE_TIME), function () use ($story_id) {
                return mStories::where('id', '=', $story_id)->where('deleted_at', '=', null)->first();
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Could not get story data.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($story === null) {
            $message = (object) [
                'type' => 'error',
                'message' => __('The story could not be found.'),
            ];
            if (config('app.debug')) {
                $message->debug = __('Story not found in database.');
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        return $story;
    }

    public static function story(object $story): object
    {
        $source = $story->getSource();
        $thumbnail = $story->getThumbnail();
        if ($source !== null) {
            $source = (object) [
                'id' => $source->id,
                'alt' => $source->alt,
                'mime' => $source->mime,
                'width' => $source->width,
                'height' => $source->height,
                'url' => $source->s3_available !== null ? API::getMediaCdnUrl($source->path) : API::getMediaUrl($source->id),
            ];
        }
        if ($thumbnail !== null) {
            $thumbnail = (object) [
                'id' => $thumbnail->id,
                'alt' => $thumbnail->alt,
                'mime' => $thumbnail->mime,
                'width' => $thumbnail->width,
                'height' => $thumbnail->height,
                'url' => $thumbnail->s3_available !== null ? API::getMediaCdnUrl($thumbnail->path) : API::getMediaUrl($thumbnail->id),
            ];
        }

        return (object) [
            'company_id' => $story->company_id,
            'title' => $story->title,
            'source' => $source,
            'thumbnail' => $thumbnail,
            'status' => $story->status,
            'publish' => $story->publish,
            'viewers' => $story->viewers,
            'clicks' => $story->clicks,
            'comments' => $story->comments,
            'dislikes' => $story->dislikes,
            'likes' => $story->likes,
            'opens' => $story->opens,
            'views' => $story->views,
            'created_at' => $story->created_at,
        ];
    }

    /**
     * Get live stream data from local database
     * 
     * @param object $r
     * @param array $params
     * 
     * @return object
     */
    public static function getLiveStream(?object &$r = null, string $stream_id): object
    {
        $r = $r ?? self::INIT();

        $stream = null;

        try {
            $stream = Cache::remember('stream_by_id_' . $stream_id, now()->addSeconds(self::CACHE_TIME), function () use ($stream_id) {
                $stream = mLiveStreams::where('id', '=', $stream_id)->where('deleted_at', '=', null)->first();
                if ($stream === null) {
                    return null;
                }
                $stream->source = $stream->getSource();
                $stream->thumbnail = $stream->getThumbnail();
                return $stream;
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Could not get stream data from database.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($stream === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Stream could not be found.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        return $stream;
    }

    /**
     * Get live stream from service (Mux or AntMedia)
     * Sync with local database and cache
     * 
     * @param object|null $r
     * @param array $params
     * 
     * @return object
     */
    public static function getLiveStreamFromService(?object &$r = null, object $stream, ?object &$thumbnail_media = null): object
    {
        try {
            $svc_stream = match (strtolower(env('STREAM_SERVICE'))) {
                'mux' => Cache::remember('mux_stream_' . $stream->live_id, now()->addSeconds(5), function () use ($stream) {
                    $svc = MuxStream::getStream($stream->live_id);
                    return $svc;
                }),
                'antmedia' => Cache::remember('antmedia_stream_' . $stream->live_id, now()->addSeconds(5), function () use ($stream) {
                    $svc = AntMediaStream::getStream($stream->live_id, $stream->latency_mode);
                    // Normalize status
                    $svc->status = match ($svc->status) {
                        'broadcasting' => 'active',
                        'created' => 'idle',
                        'finished' => 'ended',
                        default => $svc->status,
                    };
                    // Count all types of viewers
                    $svc->viewers = $svc->webRTCViewerCount + $svc->rtmpViewerCount + $svc->hlsViewerCount + $svc->dashViewerCount;
                    return $svc;
                }),
                default => null,
            };
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Stream could not be found.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if ($svc_stream === null) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Stream service not found.'),
            ];
            if (config('app.debug')) {
                $message->debug = __('The stream service (environment variables) could not be found.');
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        try {
            $stream->status = $svc_stream->status;
            $stream->viewers = $svc_stream->viewers;
            $stream->save();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Could not update stream data.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $stream;
    }

    public static function registerLink(string $link, ?object &$r = null): object
    {
        $r = $r ?? self::INIT();

        $http = Http::get($link);
        if ($http->failed()) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed when trying to access the link to validate it.'),
            ];
            if (config('app.debug')) {
                $message->debug = $http->body();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $real_url = $http->effectiveUri();
        $checksum = Uuid::uuid5(Uuid::NAMESPACE_URL, $real_url)->toString();

        try {
            $link = mLinks::where('checksum', '=', $checksum)->first();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to check link availability.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($link !== null) {
            return $link;
        }

        $id = self::doGenerateLinkID(10);

        try {
            $link = new mLinks();
            $link->id = $id;
            $link->checksum = $checksum;
            $link->url = $real_url;
            $link->created_at = now()->format('Y-m-d H:i:s');
            $link->save();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to register link.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $link->id = $id;

        return $link;
    }

    public static function mediaPathType(int $type): string
    {
        return match ($type) {
            self::MEDIA_TYPE_IMAGE => 'images/',
            self::MEDIA_TYPE_IMAGE_THUMBNAIL => 'images/thumbnails/',
            self::MEDIA_TYPE_VIDEO => 'videos/',
            self::MEDIA_TYPE_AUDIO => 'audios/',
            default => 'unknown/',
        };
    }

    public static function registerMediaFromFile($file, bool $is_thumbnail = false, string $alt = '', string $desc = '', ?object &$r = null): object
    {
        $r = $r ?? self::INIT();

        $original_name = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mime = $file->getMimeType();
        $type = $is_thumbnail === true ? self::MEDIA_TYPE_IMAGE_THUMBNAIL : self::getTypeByMime($mime);
        $base_checksum = Uuid::uuid5(Uuid::NAMESPACE_URL, Uuid::uuid4()->toString())->toString();

        $path_type = self::mediaPathType($type);

        $max_upload_size = match ($type) {
            self::MEDIA_TYPE_IMAGE => config('api.max_image_upload_size'),
            self::MEDIA_TYPE_IMAGE_THUMBNAIL => config('api.max_image_thumbnail_upload_size'),
            self::MEDIA_TYPE_VIDEO => config('api.max_video_upload_size'),
            self::MEDIA_TYPE_AUDIO => config('api.max_audio_upload_size'),
            default => config('api.max_unknown_upload_size'),
        };

        if ($file->getSize() > $max_upload_size) {
            $message = (object) [
                'type' => 'error',
                'message' => __('File size is too large.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $path = $path_type . $base_checksum . '.' . $extension;
        if ($file->storeAs('public', $path) === false) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to store file.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $path = storage_path('app/public/' . $path);

        return self::registerMedia($base_checksum, $path, $original_name, $mime, $is_thumbnail, $alt, $desc, $r);
    }

    public static function registerMediaFromUrl(string $url, bool $is_thumbnail = false, string $alt = '', string $desc = '', ?object &$r = null): object
    {
        $r = $r ?? self::INIT();

        try {
            $http = Http::get($url);
            if ($http->failed()) {
                $message = (object) [
                    'type' => 'error',
                    'message' => __('Failed to get image from url.'),
                ];
                if (config('app.debug')) {
                    $message->debug = $http->body();
                }
                $r->messages[] = $message;
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }
            $real_url = $http->effectiveUri();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to get image from url.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $original_name = basename($real_url);
        $original_name = preg_replace('/\?.*/', '', $original_name);
        $original_name = preg_replace('/#.*/', '', $original_name);
        $mime = $http->header('Content-Type');

        try {
            $extension = self::getExtensionByMime($mime);
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'warning',
                'message' => __('Failed to get image extension.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $type = $is_thumbnail === true ? self::MEDIA_TYPE_IMAGE_THUMBNAIL : self::getTypeByMime($mime);
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'warning',
                'message' => __('Failed to get image type.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $base_checksum = Uuid::uuid5(Uuid::NAMESPACE_URL, $real_url)->toString();

        $path_type = self::mediaPathType($type);

        if (Storage::put('public/' . $path_type . $base_checksum . '.' . $extension, $http->body()) === false) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to save image.'),
            ];
            if (config('app.debug')) {
                $message->debug = 'The image could not be saved.';
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $path = storage_path('app/public/' . $path_type . $base_checksum . '.' . $extension);

        return self::registerMedia($base_checksum, $path, $original_name, $mime, $is_thumbnail, $alt, $desc, $r);
    }

    public static function registerMedia(string $base_checksum, string $path, string $original_name, string $mime, bool $is_thumbnail = false, string $alt = '', string $desc = '', ?object &$r = null): object
    {
        $r = $r ?? self::INIT();

        try {
            $extension = self::getExtensionByMime($mime);
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'warning',
                'message' => __('Failed to get image extension.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $type = $is_thumbnail === true ? self::MEDIA_TYPE_IMAGE_THUMBNAIL : self::getTypeByMime($mime);
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'warning',
                'message' => __('Failed to get image type.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
        }

        $path_type = self::mediaPathType($type);
        $checksum = self::getFileChecksum($path, $is_thumbnail);

        if (($media = mLiveStreamMedias::where('checksum', '=', $checksum)->first()) !== null) {
            $message = (object) [
                'type' => 'warning',
                'message' => __('Media already exists.'),
            ];
            $r->messages[] = $message;
            return $media;
        }

        $duration = null;
        $height = null;
        $size = filesize($path);
        $width = null;

        $thumbnails = [];

        if ($type == self::MEDIA_TYPE_IMAGE || $type == self::MEDIA_TYPE_IMAGE_THUMBNAIL) {
            try {
                $image = Image::make($path);
                $width = $image->width();
                $height = $image->height();
            } catch (\Exception $e) {
                $message = (object) [
                    'type' => 'warning',
                    'message' => __('Failed to get image dimensions.'),
                ];
                if (config('app.debug')) {
                    $message->debug = $e->getMessage();
                }
                $r->messages[] = $message;
            }
        } elseif ($type == self::MEDIA_TYPE_VIDEO) {
            try {
                $ffmpeg = FFMpeg::create();
                $video = $ffmpeg->open($path);
                $width = $video->getStreams()->videos()->first()->get('width');
                $height = $video->getStreams()->videos()->first()->get('height');
                $duration = $video->getStreams()->videos()->first()->get('duration');
            } catch (\Exception $e) {
                $message = (object) [
                    'type' => 'warning',
                    'message' => __('Failed to get video dimensions or duration.'),
                ];
                if (config('app.debug')) {
                    $message->debug = $e->getMessage();
                }
                $r->messages[] = $message;
            }
            // Try creating a thumbnail
            try {
                $ffmpeg = FFMpeg::create();
                $video = $ffmpeg->open($path);

                # Create 5 thumbnails
                for ($i = 1; $i <= 5; $i++) {
                    $thumbnail = (object)[
                        'media' => null,
                        'original_name' => null,
                        'path' => null,
                        'checksum' => null,
                        'size' => 0,
                        'width' => null,
                        'height' => null,
                    ];

                    $thumbnail->original_name = $base_checksum . '-' . str_pad($i, 4, '0', STR_PAD_LEFT) . '.jpg';
                    $thumbnail->path = 'images/thumbnails/' . $thumbnail->original_name;
                    $thumbnail_path = storage_path('app/public/' . $thumbnail->path);

                    try {
                        $video->frame(TimeCode::fromSeconds($i))->save($thumbnail_path);

                        if (!Storage::disk('public')->exists($thumbnail->path)) {
                            continue;
                        }
                    } catch (\Exception $e) {
                        $message = (object) [
                            'type' => 'warning',
                            'message' => __('Failed to create video thumbnail.'),
                        ];
                        if (config('app.debug')) {
                            $message->debug = $e->getMessage();
                        }
                        $r->messages[] = $message;
                        continue;
                    }

                    $thumbnail->checksum = self::getFileChecksum($thumbnail_path, true);
                    $thumbnail->extension = 'jpg';
                    $thumbnail->height = $video->getStreams()->videos()->first()->get('height');
                    $thumbnail->mime = 'image/jpeg';
                    $thumbnail->size = filesize($thumbnail_path);
                    $thumbnail->type = self::MEDIA_TYPE_IMAGE_THUMBNAIL;
                    $thumbnail->width = $video->getStreams()->videos()->first()->get('width');

                    $thumbnails[] = $thumbnail;
                }
            } catch (\Exception $e) {
                $message = (object) [
                    'type' => 'warning',
                    'message' => __('Failed to create video thumbnail.'),
                ];
                if (config('app.debug')) {
                    $message->debug = $e->getMessage();
                }
                $r->messages[] = $message;
            }
        }

        try {
            $media = mLiveStreamMedias::where('checksum', '=', $checksum)->first();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to check if image already exists.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($media !== null) {
            $message = (object) [
                'type' => 'warning',
                'message' => __('Media already exists.'),
            ];
            $r->messages[] = $message;
            return $media;
        }

        $id = Str::uuid()->toString();

        try {
            $media = new mLiveStreamMedias();
            $media->id = $id;
            $media->checksum = $checksum;
            $media->original_name = $original_name;
            $media->path = $path_type . $base_checksum . '.' . $extension;
            $media->policy = 'public';
            $media->type = $type;
            $media->mime = $mime;
            $media->extension = $extension;
            $media->size = $size;
            $media->width = $width;
            $media->height = $height;
            $media->duration = $duration;
            $media->description = $desc;
            $media->alt = $alt;
            $media->created_at = now()->format('Y-m-d H:i:s.u');
            $media->save();
            $media->id = $id;

            if (count($thumbnails) > 0) {
                foreach ($thumbnails as $thumbnail) {
                    $thumbnail_media = new mLiveStreamMedias();
                    $thumbnail_media->id = Str::uuid()->toString();
                    $thumbnail_media->parent_id = $id;
                    $thumbnail_media->checksum = $thumbnail->checksum;
                    $thumbnail_media->original_name = $thumbnail->original_name;
                    $thumbnail_media->path = $thumbnail->path;
                    $thumbnail_media->policy = 'public';
                    $thumbnail_media->type =  $thumbnail->type;
                    $thumbnail_media->mime =  $thumbnail->mime;
                    $thumbnail_media->extension =  $thumbnail->extension;
                    $thumbnail_media->size = $thumbnail->size;
                    $thumbnail_media->width = $thumbnail->width;
                    $thumbnail_media->height = $thumbnail->height;
                    $thumbnail_media->duration = null;
                    $thumbnail_media->created_at = now()->format('Y-m-d H:i:s.u');
                    $thumbnail_media->save();
                }
            }
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to register media.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $media;
    }

    /**
     * Get live stream data from local database
     * 
     * @param object $r
     * @param array $params
     * 
     * @return object
     */
    public static function getProduct(?object &$r = null, string $product_id): object
    {
        $r = $r ?? self::INIT();

        $product = null;

        try {
            $product = Cache::remember('product_by_id_' . $product_id, now()->addSeconds(self::CACHE_TIME), function () use ($product_id) {
                return mLiveStreamProducts::where('id', '=', $product_id)->first();
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to get product data.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($product === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Product could not be found.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if ($product->deleted_at !== null) {
            $message = (object) [
                'type' => 'error',
                'message' => __('The product is excluded.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        return $product;
    }

    /**
     * Get live stream data from local database
     * 
     * @param object $r
     * @param array $params
     * 
     * @return object
     */
    public static function getProductGroup(?object &$r = null, string $group_id): object
    {
        $r = $r ?? self::INIT();

        $group = null;

        try {
            $group = Cache::remember('product_group_' . $group_id, now()->addSeconds(self::CACHE_TIME), function () use ($group_id) {
                return mLiveStreamProductGroups::where('id', '=', $group_id)->first();
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to get product group data.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($group === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Product group could not be found.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        return $group;
    }

    /**
     * Get live stream data from local database
     * 
     * @param object $r
     * @param array $params
     * 
     * @return object
     */
    public static function getImageProduct(?object &$r = null, string $image_id): object
    {
        $r = $r ?? self::INIT();

        $image = null;

        try {
            $image = Cache::remember('product_image_by_id_' . $image_id, now()->addSeconds(self::CACHE_TIME), function () use ($image_id) {
                return mLiveStreamProductsImages::where('id', '=', $image_id)->first();
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to get product image data.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($image === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Product image could not be found.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        return $image;
    }


    public static function getMedia(?object &$r = null, string $media_id): object
    {
        $r = $r ?? self::INIT();

        $media = null;

        try {
            $media = Cache::remember('media_by_id_' . $media_id, now()->addSeconds(30), function () use ($media_id) {
                return mLiveStreamMedias::where('id', '=', $media_id)->first();
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to get media data.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($media === null) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Media could not be found.'),
            ];
            if (config('app.debug')) {
                $message->debug = 'The media could not be found in the database.';
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if ($media->deleted_at !== null) {
            $message = (object) [
                'type' => 'error',
                'message' => __('The media is excluded.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        return $media;
    }

    public static function doSyncMediaWithCDN(?object $media, ?object &$r = null): mixed
    {
        $r = $r ?? self::INIT();

        if ($media === null) {
            return false;
        }

        if ($media->s3_available === null) {
            $in_s3 = false;
            if (Storage::disk('s3')->exists($media->path) === false) {
                $file = Storage::disk('public')->get($media->path);

                if ($file === false || $file === null) {
                    $r->messages[] = [
                        'type' => 'error',
                        'message' => __('File not found.'),
                    ];
                    return response()->json($r, Response::HTTP_NOT_FOUND);
                }

                $in_s3 = Storage::disk('s3')->put($media->path, $file, [
                    'ContentType' => $media->mime,
                    'ContentDisposition' => 'inline; filename="' . $media->original_name . '"',
                    'CacheControl' => 'max-age=31536000, public',
                    'x-aws-meta-checksum' => $media->checksum,
                    'ACL' => 'public-read',
                ]);
            } else {
                $in_s3 = true;
            }
            
            if ($in_s3 === true) {
                $media->s3_available = now()->format('Y-m-d H:i:s.u');
                $media->save();
            }
            return self::getMediaCdnUrl($media->path);
        }

        return self::getMediaCdnUrl($media->path);
    }

    public static function getMediaByPath(?object &$r = null, string $path): object
    {
        $r = $r ?? self::INIT();

        $media = null;

        try {
            $media = Cache::remember('media_by_path_' . $path, now()->addSeconds(self::CACHE_TIME), function () use ($path) {
                return mLiveStreamMedias::where('path', '=', $path)->first();
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to get media data.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($media === null) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Media could not be found.'),
            ];
            if (config('app.debug')) {
                $message->debug = 'The media could not be found in the database.';
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if ($media->deleted_at !== null) {
            $message = (object) [
                'type' => 'error',
                'message' => __('The media is excluded.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        return $media;
    }

    public static function getToken(string $token, ?object &$r = null): object
    {
        $r = $r ?? self::INIT();

        $t = null;

        try {
            $t = Cache::remember('token_' . Uuid::uuid5(Uuid::NAMESPACE_DNS, $token)->toString(), now()->addSeconds(self::CACHE_TIME), function () use ($token) {
                return mLiveStreamCompanyTokens::where('token', '=', $token)->where('expires_at', '>', now()->format('Y-m-d H:i:s.u'))->first();
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to get token data.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($t === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Token invalid.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        return $t;
    }

    public static function getCompanyUserByEmail(string $email, bool $skip_check_account = false, ?object &$r = null): ?object
    {
        $r = $r ?? self::INIT();

        $company_user = null;

        try {
            $company_user = Cache::remember('company_user_by_email_' . Uuid::uuid5(Uuid::NAMESPACE_DNS, $email)->toString(), now()->addSeconds(self::CACHE_TIME), function () use ($email) {
                return mLiveStreamCompanyUsers::where('email', '=', $email)->first();
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to get user data.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($skip_check_account && $company_user === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('User could not be found.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if ($skip_check_account && $company_user->deleted_at !== null) {
            $message = (object) [
                'type' => 'error',
                'message' => __('The user is excluded.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        return $company_user;
    }

    public static function getCompanyUserByToken(string $token, ?object &$r = null): object
    {
        $r = $r ?? self::INIT();

        $company_user = null;

        try {
            $company_user = Cache::remember('company_user_by_token_' . Uuid::uuid5(Uuid::NAMESPACE_DNS, $token)->toString(), now()->addSeconds(self::CACHE_TIME), function () use ($token) {
                $tokens = mLiveStreamCompanyTokens::where('token', '=', $token)->first();

                if ($tokens === null) {
                    return null;
                }

                return mLiveStreamCompanyUsers::where('id', '=', $tokens->user_id)->first();
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to get user data.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($company_user === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('User could not be found.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if ($company_user->deleted_at !== null) {
            $message = (object) [
                'type' => 'error',
                'message' => __('The user is excluded.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        return $company_user;
    }

    public static function getCompanyUserById(string $id, ?object &$r = null): object
    {
        $r = $r ?? self::INIT();

        $company_user = null;

        try {
            $company_user = Cache::remember('company_user_by_id_' . Uuid::uuid5(Uuid::NAMESPACE_DNS, $id)->toString(), now()->addSeconds(self::CACHE_TIME), function () use ($id) {
                return mLiveStreamCompanyUsers::where('id', '=', $id)->first();
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to get user data.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($company_user === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('User could not be found.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if ($company_user->deleted_at !== null) {
            $message = (object) [
                'type' => 'error',
                'message' => __('The user is excluded.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        return $company_user;
    }

    public static function getCompanyByToken(string $token, ?object &$r = null): object
    {
        $r = $r ?? self::INIT();

        $company = null;

        try {
            $company = Cache::remember('company_by_token_' . Uuid::uuid5(Uuid::NAMESPACE_DNS, $token)->toString(), now()->addSeconds(self::CACHE_TIME), function () use ($token, &$r) {
                $tokens = mLiveStreamCompanyTokens::where('token', '=', $token)->first();

                if ($tokens === null) {
                    $r->messages[] = (object) [
                        'type' => 'error',
                        'message' => __('Token is invalid.'),
                    ];
                    return response()->json($r, Response::HTTP_BAD_REQUEST);
                }

                $users = mLiveStreamCompanyUsers::where('id', '=', $tokens->user_id)->where('deleted_at', '=', null)->first();

                if ($users === null) {
                    $r->messages[] = (object) [
                        'type' => 'error',
                        'message' => __('Token is invalid.'),
                    ];
                    return response()->json($r, Response::HTTP_BAD_REQUEST);
                }

                return mLiveStreamCompanies::find($users->company_id);
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to get company data.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($company === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Token is invalid.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        return $company;
    }

    public static function getCompany(?object &$r = null, string $company_id): object
    {
        $r = $r ?? self::INIT();

        $company = null;

        try {
            $company = Cache::remember('company_' . $company_id, now()->addSeconds(self::CACHE_TIME), function () use ($company_id) {
                return mLiveStreamCompanies::where('id', '=', $company_id)->first();
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Failed to get company data.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($company === null) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Company could not be found.'),
            ];
            if (config('app.debug')) {
                $message->debug = __('The company could not be found in the database.');
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if ($company->deleted_at !== null) {
            $message = (object) [
                'type' => 'error',
                'message' => __('The company is excluded.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        return $company;
    }

    public static function validateLink(?object &$r = null, array $params): object
    {
        $r = $r ?? self::INIT();

        if (!isset($params['link_id'])) {
            $message = (object) [
                'type' => 'error',
                'message' => __('The link ID is missing.'),
            ];
            if (config('app.debug')) {
                $message->debug = 'The link ID is missing from the request.';
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if (!preg_match('/^[a-zA-Z0-9-_\.]+$/', $params['link_id'])) {
            $message = (object) [
                'type' => 'error',
                'message' => __('The link ID is invalid.'),
            ];
            if (config('app.debug')) {
                $message->debug = __('A link ID can only contain letters, numbers, dashes, underscores and dots.');
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $link = mLinks::where('id', '=', $params['link_id'])->where('deleted_at', '=', null)->first();

        if ($link === null) {
            $message = (object) [
                'type' => 'error',
                'message' => __('The link could not be found.'),
            ];
            if (config('app.debug')) {
                $message->debug = __('The link could not be found in the database.');
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        return $link;
    }

    public static function registerStreamMetric(Request $request, array $params, array $metrics, ?object &$r = null): object
    {
        $r = $r ?? self::INIT();

        $ip = $request->ip();
        $region = null;
        $state = null;
        $country = null;
        $userAgent = $request->userAgent();
        $browser = null;
        $device = null;
        $os = null;

        $metrics['load'] = isset($metrics['load']) ? $metrics['load'] : 0;
        $metrics['click'] = isset($metrics['click']) ? $metrics['click'] : 0;
        $metrics['like'] = isset($metrics['like']) ? $metrics['like'] : 0;
        $metrics['unlike'] = isset($metrics['unlike']) ? $metrics['unlike'] : 0;
        $metrics['dislike'] = isset($metrics['dislike']) ? $metrics['dislike'] : 0;
        $metrics['undislike'] = isset($metrics['undislike']) ? $metrics['undislike'] : 0;
        $metrics['view'] = isset($metrics['view']) ? $metrics['view'] : 0;
        $metrics['share'] = isset($metrics['share']) ? $metrics['share'] : 0;
        $metrics['comment'] = isset($metrics['comment']) ? $metrics['comment'] : 0;

        try {
            $db_city = new Reader('/usr/share/GeoIP2/GeoLite2-City.mmdb');
            $region = $db_city->city($ip);
            $state = $region->mostSpecificSubdivision->isoCode ?? null;
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            $db_country = new Reader('/usr/share/GeoIP2/GeoLite2-Country.mmdb');
            $country = $db_country->country($ip);
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            if (!empty($userAgent)) {
                $b = Browser::parse($userAgent);
                $browser = $b->browserFamily();
                $device = $b->deviceFamily();
                $os = $b->platformFamily();
            }
        } catch (\Exception $e) {
            // Ignore
        }

        $metric = [
            'stream_id' => $params['stream_id'],
            'created_at' => now()->format('Y-m-d H:i:s.u'),
            'ip' => $request->ip(),
            'region' => $region,
            'state' => $state,
            'country' => $country,
            'user_agent' => $userAgent,
            'device' => $device,
            'os' => $os,
            'browser' => $browser,
            'load' => $metrics['load'],
            'click' => $metrics['click'],
            'like' => $metrics['like'],
            'unlike' => $metrics['unlike'],
            'dislike' => $metrics['dislike'],
            'undislike' => $metrics['undislike'],
            'view' => $metrics['view'],
            'share' => $metrics['share'],
            'comment' => $metrics['comment'],
        ];

        try {
            mLiveStreamMetrics::insert($metric);
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => 'Error while saving story metrics',
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return (object) $metric;
    }

    public static function registerStoryMetric(Request $request, array $params, array $metrics, ?object &$r = null): object
    {
        $r = $r ?? self::INIT();

        $ip = $request->ip();
        $region = null;
        $state = null;
        $country = null;
        $userAgent = $request->userAgent();
        $browser = null;
        $device = null;
        $os = null;

        $metrics['load'] = isset($metrics['load']) ? $metrics['load'] : 0;
        $metrics['click'] = isset($metrics['click']) ? $metrics['click'] : 0;
        $metrics['like'] = isset($metrics['like']) ? $metrics['like'] : 0;
        $metrics['unlike'] = isset($metrics['unlike']) ? $metrics['unlike'] : 0;
        $metrics['dislike'] = isset($metrics['dislike']) ? $metrics['dislike'] : 0;
        $metrics['undislike'] = isset($metrics['undislike']) ? $metrics['undislike'] : 0;
        $metrics['view'] = isset($metrics['view']) ? $metrics['view'] : 0;
        $metrics['share'] = isset($metrics['share']) ? $metrics['share'] : 0;
        $metrics['comment'] = isset($metrics['comment']) ? $metrics['comment'] : 0;

        try {
            $db_city = new Reader('/usr/share/GeoIP2/GeoLite2-City.mmdb');
            $region = $db_city->city($ip);
            $state = $region->mostSpecificSubdivision->isoCode ?? null;
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            $db_country = new Reader('/usr/share/GeoIP2/GeoLite2-Country.mmdb');
            $country = $db_country->country($ip);
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            if (!empty($userAgent)) {
                $b = Browser::parse($userAgent);
                $browser = $b->browserFamily();
                $device = $b->deviceFamily();
                $os = $b->platformFamily();
            }
        } catch (\Exception $e) {
            // Ignore
        }

        $metric = [
            'story_id' => $params['story_id'],
            'created_at' => now()->format('Y-m-d H:i:s.u'),
            'ip' => $request->ip(),
            'region' => $region,
            'state' => $state,
            'country' => $country,
            'user_agent' => $userAgent,
            'device' => $device,
            'os' => $os,
            'browser' => $browser,
            'load' => $metrics['load'],
            'click' => $metrics['click'],
            'like' => $metrics['like'],
            'unlike' => $metrics['unlike'],
            'dislike' => $metrics['dislike'],
            'undislike' => $metrics['undislike'],
            'view' => $metrics['view'],
            'share' => $metrics['share'],
            'comment' => $metrics['comment'],
        ];

        try {
            mStoryMetrics::insert($metric);
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => 'Error while saving story metrics',
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return (object) $metric;
    }
}
