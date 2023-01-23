<?php

namespace App\Models;

use App\Casts\Base64;
use App\Http\Controllers\API;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class LiveStreamProducts extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'livestreams_products';
    protected $primaryKey = 'id';
    public $timestamps = true;

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
        'is_active',
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
        'deleted_at',
        'created_at',
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
        'is_active' => 'boolean',
        'link_id' => 'string',
        'promoted' => 'boolean',
        'views' => 'integer',
        'clicks' => 'integer',
        'deleted_at' => 'timestamp',
    ];

    public function images()
    {
        return $this->hasMany(LiveStreamProductsImages::class, 'product_id', 'id');
    }

    public function getImages()
    {
        $qry = $this->images()->select('media_id')->get();
        $images = array_map(function ($i) {
            return API::getMediaUrl($i['media_id']);
        }, $qry->toArray()) ?? [];


        // $product->images()->select('media_id')->get()->map(function ($i) {
        // return API::getMediaUrl($i['media_id']);
        // }) ?? [];
        return $images;
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
}
