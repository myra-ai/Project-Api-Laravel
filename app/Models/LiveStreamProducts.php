<?php

namespace App\Models;

use App\Casts\Base64;
use App\Http\Controllers\API;
use App\Jobs\MediaResizer;
use App\Models\Links;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class LiveStreamProducts extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'products';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'company_id',
        'title',
        'description',
        'price',
        'currency',
        'status',
        'link_id',
        'views',
        'clicks',
        'deleted_at',
    ];

    protected $hidden = [
        'views',
        'clicks',
        'link_id',
        'company_id',
        'created_at',
        'deleted_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'title' => Base64::class,
        'description' => Base64::class,
        'price' => 'float',
        'currency' => 'string',
        'status' => 'integer',
        'link_id' => 'string',
        'views' => 'integer',
        'clicks' => 'integer',
        'deleted_at' => 'timestamp',
    ];

    public function getImages(
        string $order_by = 'updated_at',
        string $order = 'asc',
        int $offset = 0,
        int $limit = 30
    ) {
        return $this->hasMany(LiveStreamProductsImages::class, 'product_id', 'id')->select('media_id')
            ->orderBy($order_by, $order)
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($i) {
                $media = API::media($i->media_id);
                if ($media === null) {
                    return null;
                }

                return match ($media->s3_available) {
                    null => API::getMediaUrl($media->id),
                    default => API::getMediaCdnUrl($media->path)
                };
            });
    }

    public function getImagesDetails(
        string $order_by = 'updated_at',
        string $order = 'asc',
        int $offset = 0,
        int $limit = 30
    ) {
        return $this->hasMany(LiveStreamProductsImages::class, 'product_id', 'id')
            ->orderBy($order_by, $order)
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($i) {
                $media = API::media($i->media_id);
                if ($media === null) {
                    return null;
                }

                return (object) [
                    'id' => $i->id,
                    'media_id' => $media->id,
                    'url' => match ($media->s3_available) {
                        null => API::getMediaUrl($media->id),
                        default => API::getMediaCdnUrl($media->path)
                    },
                    'alt' => $media->alt,
                    'mime' => $media->mime,
                    'width' => $media->width,
                    'height' => $media->height,
                ];
            });
    }

    public function getImagesDetailsOptimized(
        int $width = 256,
        int $height = 256,
        string $mode = 'resize',
        bool $keep_aspect_ratio = true,
        int $quality = 80,
        bool $blur = true,
        string $order_by = 'updated_at',
        string $order = 'asc',
        int $offset = 0,
        int $limit = 30
    ) {
        return $this->hasMany(LiveStreamProductsImages::class, 'product_id', 'id')
            ->orderBy($order_by, $order)
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($i) use ($width, $height, $mode, $keep_aspect_ratio, $quality, $blur, $order_by, $order, $offset) {
                $media = API::media($i->media_id);
                if ($media === null) {
                    return null;
                }

                if (($optimized = API::mediaSized($i->media_id, $width, $height, $order_by, $order, $offset)) === null) {
                    MediaResizer::dispatch($i->media_id, $width, $height, $mode, $keep_aspect_ratio, $quality, $blur, 'webp');
                } else {
                    return (object) [
                        'id' => $i->id,
                        'media_id' => $media->id,
                        'optimized_id' => $optimized->id,
                        'url' => match ($optimized->s3_available) {
                            null => API::getMediaUrl($optimized->id),
                            default => API::getMediaCdnUrl($optimized->path)
                        },
                        'alt' => $media->alt,
                        'mime' => $optimized->mime,
                        'width' => $optimized->width,
                        'height' => $optimized->height,
                    ];
                }

                return (object) [
                    'id' => $i->id,
                    'media_id' => $media->id,
                    'url' => match ($media->s3_available) {
                        null => API::getMediaUrl($media->id),
                        default => API::getMediaCdnUrl($media->path)
                    },
                    'alt' => $media->alt,
                    'mime' => $media->mime,
                    'width' => $media->width,
                    'height' => $media->height,
                ];
            });
    }


    public function addImage(string $media_id): bool | string
    {
        $id = Str::uuid()->toString();
        $image = new LiveStreamProductsImages();
        $image->id = $id;
        $image->product_id = $this->id;
        $image->media_id = $media_id;
        if (!$image->save()) {
            return false;
        }
        return $id;
    }

    public function removeImage(string $image_id): bool
    {
        $image = LiveStreamProductsImages::where('id', $image_id)->first();
        if (!$image) return false;
        return $image->delete();
    }

    public function getLink()
    {
        $link = $this->hasOne(Links::class, 'id', 'link_id')->first();
        if (!$link) return null;
        return $link;
    }

    public function company()
    {
        return $this->belongsTo(LiveStreamCompanies::class, 'company_id', 'id');
    }

    public function getProductsByStreamID(string $stream_id): mixed
    {
        return $this->join('product_groups', 'product_groups.product_id', '=', 'products.id')
            ->where('product_groups.stream_id', $stream_id)
            ->select('products.*', 'product_groups.id as group_id');
    }

    public function getProductsByStoryID(string $story_id): mixed
    {
        return $this->join('product_groups', 'product_groups.product_id', '=', 'products.id')
            ->where('product_groups.story_id', $story_id)
            ->select('products.*', 'product_groups.id as group_id');
    }

    public function groups()
    {
        return $this->hasMany(LiveStreamProductGroups::class, 'product_id', 'id');
    }

    public function getGroups()
    {
        return $this->groups()->get();
    }

    public function group()
    {
        return $this->hasOne(LiveStreamProductGroups::class, 'product_id', 'id');
    }

    public function getGroup()
    {
        return $this->group()->first();
    }

    public function isAttachedWithStory(string $story_id, &$promoted = false): bool
    {
        $qry = $this->hasOne(LiveStreamProductGroups::class, 'product_id', 'id')->where('story_id', $story_id)->exists();
        if (!$qry) {
            return false;
        }
        $promoted = $qry->promoted ?? false;
        return true;
    }

    public function isAttachedWithStream(string $stream_id, &$promoted = false): bool
    {
        $qry = $this->hasOne(LiveStreamProductGroups::class, 'product_id', 'id')->where('stream_id', $stream_id)->first();
        if (!$qry) {
            return false;
        }
        $promoted = $qry->promoted  ?? false;
        return true;
    }

    public function getProductsByCompanyId(
        string $company_id,
        int $offset = 0,
        int $limit = 80,
        string $order_by = 'created_at',
        string $order = 'ASC',
        bool $attached_only = false,
        ?string $attached_order = null,
        ?string $attached_story = null,
        ?string $attached_stream = null
    ) {
        return $this->where('company_id', '=', $company_id)
            ->where('deleted_at', '=', null)
            ->when($attached_story !== null, function ($query) use ($attached_story, $attached_only, $attached_order) {
                if ($attached_only) {
                    $qry = $query->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                        ->where('product_groups.story_id', '=', $attached_story);
                } else {
                    $qry = $query->leftjoin('product_groups', function ($join) use ($attached_story) {
                        $join->on('product_groups.product_id', '=', 'products.id')
                            ->where('product_groups.story_id', '=', $attached_story);
                    });
                }
                $qry->select('products.*', 'product_groups.promoted as promoted', self::raw('IF(product_groups.id IS NOT NULL, 1, 0) as attached'))
                    ->when($attached_order !== null, function ($query) use ($attached_order) {
                        return $query->orderBy('attached', $attached_order);
                    });
                return $qry;
            })
            ->when($attached_stream !== null, function ($query) use ($attached_stream, $attached_only, $attached_order) {
                if ($attached_only) {
                    $qry = $query->join('product_groups', 'product_groups.product_id', '=', 'products.id')
                        ->where('product_groups.stream_id', '=', $attached_stream);
                } else {
                    $qry = $query->leftjoin('product_groups', function ($join) use ($attached_stream) {
                        $join->on('product_groups.product_id', '=', 'products.id')
                            ->where('product_groups.stream_id', '=', $attached_stream);
                    });
                }
                $qry->select('products.*', 'product_groups.promoted as promoted', self::raw('IF(product_groups.id IS NOT NULL, 1, 0) as attached'))
                    ->when($attached_order !== null, function ($query) use ($attached_order) {
                        return $query->orderBy('attached', $attached_order);
                    });
                return $qry;
            })
            ->offset($offset)
            ->limit($limit)
            ->when($attached_order === null, function ($query) use ($order_by, $order) {
                return $query->orderBy($order_by, $order);
            })
            ->get();
    }
}
