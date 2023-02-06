<?php

namespace App\Models;

use App\Casts\Base64;
use App\Http\Controllers\API;
use App\Models\Links;
use App\Models\LiveStreamMedias;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class LiveStreamProducts extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'livestreams_products';
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
        'promoted',
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
        'promoted' => 'boolean',
        'views' => 'integer',
        'clicks' => 'integer',
        'deleted_at' => 'timestamp',
    ];

    public function getImages()
    {
        $qry = $this->hasMany(LiveStreamProductsImages::class, 'product_id', 'id')->select('media_id')->get();
        $images = array_map(function ($i) {
            $media = LiveStreamMedias::where('id', $i['media_id'])->first();
            if (!$media) return null;
            return API::getMediaCdnUrl($media->path);
        }, $qry->toArray()) ?? [];
        return array_filter($images);
    }

    public function getImagesDetails()
    {
        $qry = $this->hasMany(LiveStreamProductsImages::class, 'product_id', 'id')->get();
        $images = array_map(function ($i) {
            $media = LiveStreamMedias::where('id', $i['media_id'])->first();
            if (!$media) return null;
            $image = new \stdClass();
            $image->id = $i['id'];
            $image->media_id = $media->id;
            $image->url = match ($media->s3_available) {
                null => API::getMediaUrl($media->id),
                default => API::getMediaCdnUrl($media->path)
            };
            $image->alt = $media->alt;
            $image->mime = $media->mime;
            $image->width = $media->width;
            $image->height = $media->height;
            return $image;
        }, $qry->toArray()) ?? [];
        return array_filter($images);
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
        return $this->join('livestream_product_groups', 'livestream_product_groups.product_id', '=', 'livestreams_products.id')
            ->where('livestream_product_groups.stream_id', $stream_id)
            ->select('livestreams_products.*');
    }

    public function getProductsByStoryID(string $story_id): mixed
    {
        return $this->join('livestream_product_groups', 'livestream_product_groups.product_id', '=', 'livestreams_products.id')
            ->where('livestream_product_groups.story_id', $story_id)
            ->select('livestreams_products.*');
    }

    public function getGroups()
    {
        return $this->hasMany(LiveStreamProductGroups::class, 'product_id', 'id');
    }
}
