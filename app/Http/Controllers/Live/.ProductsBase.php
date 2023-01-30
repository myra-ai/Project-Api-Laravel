<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use App\Models\LiveStreamProducts as mLiveStreamProducts;
use App\Rules\strBoolean;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class Products extends API
{
    public function doCreate(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'story_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'active' => ['nullable', new strBoolean],
            'currency' => ['nullable', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:255'],
            'images' => ['nullable', 'array'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        $stream = null;
        $story = null;

        if (isset($params['stream_id'])) {
            if (($stream = API::getLiveStream($r, $params)) instanceof JsonResponse) {
                return $stream;
            }
        } else if (isset($params['story_id'])) {
            if (($story = API::getStory($r, $params)) instanceof JsonResponse) {
                return $story;
            }
        }

        if ($stream === null && $story === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Stream ID or Story ID is required.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $active = $request->input('active', null);
        $currency = $request->input('currency', 'BRL');
        $description = $request->input('description', null);
        $images = $request->input('images', null);
        $link = $request->input('link', null);
        $price = $request->input('price', 0);
        $promoted = $request->input('promoted', null);
        $title = $request->input('title', null);

        if ($link === null) {
            $message = [
                'type' => 'error',
                'message' => __('Link is required.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if (strlen($link) < 4) {
            $message = [
                'type' => 'error',
                'message' => __('Link is too short.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if (strlen($link) > (int) config('app.product_max_link_length', 1000)) {
            $message = [
                'type' => 'error',
                'message' => __('Link is too long.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if ($title === null) {
            $message = [
                'type' => 'error',
                'message' => __('Title is required.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $title = trim($title);
        if (strlen($title) < 4) {
            $message = [
                'type' => 'error',
                'message' => __('Title is too short.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if (strlen($title) > (int) config('app.product_max_title_length', 100)) {
            $message = [
                'type' => 'error',
                'message' => __('Title is too long.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if (!is_numeric($price)) {
            $message = [
                'type' => 'error',
                'message' => __('Invalid price.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if ($currency !== 'BRL') {
            $currency = preg_replace('/[^a-zA-Z]/', '', $currency);
            $currency = strtoupper($currency);
            if (!in_array($currency, ['USD', 'EUR', 'GBP', 'JPY', 'AUD', 'CAD', 'CHF', 'CNY', 'HKD', 'NZD', 'SEK', 'SGD'])) {
                $message = [
                    'type' => 'error',
                    'message' => __('Invalid currency.'),
                ];
                $r->messages[] = $message;
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }
        }

        if ($description !== null) {
            $description = trim($description);
            if (strlen($description) < 4) {
                $message = [
                    'type' => 'error',
                    'message' => __('Description is too short.'),
                ];
                $r->messages[] = $message;
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }

            if (strlen($description) > (int) config('app.product_max_description_length', 1000)) {
                $message = [
                    'type' => 'error',
                    'message' => __('Description is too long.'),
                ];
                $r->messages[] = $message;
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }
        }

        if ($images !== null) {
            $images = explode(config('app.product_images_separator', ';'), $images);

            if (count($images) < (int) config('app.product_min_images', 1)) {
                $message = [
                    'type' => 'error',
                    'message' => __('Too few images.'),
                ];
                $r->messages[] = $message;
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }

            if (count($images) > (int) config('app.product_max_images', 8)) {
                $message = [
                    'type' => 'warning',
                    'message' => __('Too many images, only the first :count will be used.', ['count' => config('app.product_max_images', 8)]),
                ];
                $r->messages[] = $message;
                $images = array_slice($images, 0, 8);
            }

            $images = array_filter($images, function ($image) {
                return filter_var($image, FILTER_VALIDATE_URL) !== false;
            });

            $images = array_map(function ($image) {
                $image = trim($image);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $image);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $exec = curl_exec($ch);
                $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                curl_close($ch);
                return $url;
            }, $images);

            $images = array_filter($images);
            $images = array_unique($images);
            $images = array_values($images);
        }

        if ($active !== null) {
            if (filter_var($active, FILTER_VALIDATE_BOOLEAN)) {
                $active = true;
            } else {
                $active = false;
            }
        } else {
            $active = true;
        }

        if ($promoted !== null) {
            if (filter_var($promoted, FILTER_VALIDATE_BOOLEAN)) {
                $promoted = true;
            } else {
                $promoted = false;
            }
        } else {
            $promoted = false;
        }

        try {
            $product = new mLiveStreamProducts();
            $product->stream_id = $stream !== null ? $stream->id : null;
            $product->story_id = $storie !== null ? $storie->id : null;
            $product->title = $title;
            $product->description = $description;
            $product->link = $link;
            $product->price = $price;
            $product->currency = $currency;
            $product->is_active = $active;
            $product->promoted = $promoted;
            $product->images = $images;
            $product->save();
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to register product.'),
            ];
            if (env('APP_DEBUG', false)) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->data = (object) [
            'id' => (int) $product->id,
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function products(Request $request, ?string $live_id = null, ?string $story_id = null): JsonResponse
    {
        $stream = null;
        $storie = null;
        if ($live_id !== null && ($stream = API::validateLiveId($live_id, $r)) instanceof JsonResponse) {
            return $stream;
        } elseif ($story_id !== null && ($storie = API::validateStorieId($story_id, $r)) instanceof JsonResponse) {
            return $storie;
        }

        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 100);

        $products_count = 0;
        $products = [];

        try {
            $cache = 'products_';
            $cache = $stream !== null ? $cache . 'lvID:' . $stream->id : null;
            $cache = $storie !== null ? $cache . 'stID:' . $storie->id : null;
            if ($stream === null && $storie === null) {
                $cache = $cache . 'all';
            }
            $cache = $cache . '_o:' . $offset . '_l:' . $limit;
            $products = Cache::remember($cache, now()->addSeconds(3), function () use ($offset, $limit, $stream, $storie) {
                $qry = match (true) {
                    $stream !== null => mLiveStreamProducts::where('stream_id', '=', $stream->id),
                    $storie !== null => mLiveStreamProducts::where('story_id', '=', $storie->id),
                    default => new mLiveStreamProducts(),
                };
                return $qry->where('is_active', '=', true)->where('deleted_at', '=', null)->orderBy('promoted', 'desc')->orderBy('created_at', 'desc')->offset($offset)->limit($limit)->get();
            });
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to retrieve products.'),
            ];
            if (env('APP_DEBUG', false)) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = (object) $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $products_count = (int) Cache::remember('products_count', now()->addSeconds(10), function () use ($stream, $storie) {
                $qry = match (true) {
                    $stream !== null => mLiveStreamProducts::where('stream_id', '=', $stream->id),
                    $storie !== null => mLiveStreamProducts::where('story_id', '=', $storie->id),
                    default => new mLiveStreamProducts(),
                };
                return $qry->where('is_active', '=', true)->where('deleted_at', '=', null)->count();
            });
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to retrieve products count.'),
            ];
            if (env('APP_DEBUG', false)) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = (object) $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $count = count($products);

        $r->data = $products;

        $r->data_info = [
            'offset' => $offset,
            'limit' => $limit,
            'count' => $count,
            'total' => $products_count,
        ];

        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function productSpecific($product_id): JsonResponse
    {
        if (($product = API::validateProductID($product_id, $r)) instanceof JsonResponse) {
            return $product;
        }

        if ($product->deleted_at !== null) {
            $message = [
                'type' => 'error',
                'message' => __('Product is deleted.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_NOT_FOUND);
        }

        $r->data = (object)[
            'created_at' => $product->created_at,
            'currency' => $product->currency,
            'description' => $product->description,
            'id' => $product->id,
            'images' => $product->images,
            'is_active' => $product->is_active,
            'link' => $product->link,
            'price' => $product->price,
            'promoted' => $product->promoted,
            'title' => $product->title,
        ];

        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function productUpdate(Request $request, ?string $product_id = null): JsonResponse
    {
        if (($product = API::validateProductID($product_id, $r)) instanceof JsonResponse) {
            return $product;
        }

        $title = $request->input('title', null);
        $description = $request->input('description', null);
        $price = $request->input('price', null);
        $currency = $request->input('currency', null);
        $active = $request->input('active', null);
        $promoted = $request->input('promoted', null);
        $link = $request->input('link', null);

        if ($title === null && $description === null && $price === null && $currency === null && $active === null && $promoted === null && $link === null) {
            $message = [
                'type' => 'error',
                'message' => __('No data to update.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if ($price !== null) {
            if (!is_numeric($price)) {
                $message = [
                    'type' => 'error',
                    'message' => __('Invalid price.'),
                ];
                $r->messages[] = $message;
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }
        }

        if ($currency !== null) {
            $currency = preg_replace('/[^a-zA-Z]/', '', $currency);
            $currency = strtoupper($currency);
            if (!in_array($currency, ['BRL', 'USD', 'EUR', 'GBP', 'JPY', 'AUD', 'CAD', 'CHF', 'CNY', 'HKD', 'NZD', 'SEK', 'SGD'])) {
                $message = [
                    'type' => 'error',
                    'message' => __('Invalid currency.'),
                ];
                $r->messages[] = $message;
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }
        }

        if ($link !== null) {
            if (strlen($link) < 4) {
                $message = [
                    'type' => 'error',
                    'message' => __('Link is too short.'),
                ];
                $r->messages[] = $message;
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }

            if (strlen($link) > (int) config('app.product_max_link_length', 1000)) {
                $message = [
                    'type' => 'error',
                    'message' => __('Link is too long.'),
                ];
                $r->messages[] = $message;
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }
        }

        if ($title !== null) {
            $title = trim($title);
            if (strlen($title) < 4) {
                $message = [
                    'type' => 'error',
                    'message' => __('Title is too short.'),
                ];
                $r->messages[] = $message;
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }

            if (strlen($title) > (int) config('app.product_max_title_length', 100)) {
                $message = [
                    'type' => 'error',
                    'message' => __('Title is too long.'),
                ];
                $r->messages[] = $message;
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }
        }

        if ($description !== null) {
            $description = trim($description);
            if (strlen($description) < 4) {
                $message = [
                    'type' => 'error',
                    'message' => __('Description is too short.'),
                ];
                $r->messages[] = $message;
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }

            if (strlen($description) > (int) config('app.product_max_description_length', 1000)) {
                $message = [
                    'type' => 'error',
                    'message' => __('Description is too long.'),
                ];
                $r->messages[] = $message;
                return response()->json($r, Response::HTTP_BAD_REQUEST);
            }
        }

        if ($active !== null) {
            $active = false;
        }

        if ($promoted !== null) {
            $promoted = false;
        }

        try {
            if ($title !== null) {
                $product->title = $title;
            }
            if ($description !== null) {
                $product->description = $description;
            }
            if ($link !== null) {
                $product->link = $link;
            }
            if ($price !== null) {
                $product->price = $price;
            }
            if ($currency !== null) {
                $product->currency = $currency;
            }
            if ($active !== null) {
                $product->is_active = $active;
            }
            if ($promoted !== null) {
                $product->promoted = $promoted;
            }
            $product->save();
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to update live stream product.'),
            ];
            if (env('APP_DEBUG', false)) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->data = [
            'id' => $product->id,
            'title' => $product->title,
            'description' => $product->description,
            'price' => $product->price,
            'currency' => $product->currency,
            'created_at' => $product->created_at,
        ];

        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function productDelete(?string $product_id = null): JsonResponse
    {
        if (($product = API::validateProductID($product_id, $r)) instanceof JsonResponse) {
            return $product;
        }

        try {
            $product->deleted_at = now();
            $product->save();
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to delete live stream product.'),
            ];
            if (env('APP_DEBUG', false)) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->success = true;
        $r->data = (object) [
            'id' => $product->id,
        ];
        return response()->json($r, Response::HTTP_OK);
    }

    public function productMetricViews(?string $product_id = null): JsonResponse
    {
        if (($product = API::validateProductID($product_id, $r)) instanceof JsonResponse) {
            return $product;
        }

        try {
            $product->views++;
            $product->save();
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to update live stream product.'),
            ];
            if (env('APP_DEBUG', false)) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->success = true;
        $r->data = 'OK';
        return response()->json($r, Response::HTTP_OK);
    }

    public function productMetricClicks(?string $product_id = null): JsonResponse
    {
        if (($product = API::validateProductID($product_id, $r)) instanceof JsonResponse) {
            return $product;
        }

        try {
            $product->clicks++;
            $product->save();
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to update live stream product.'),
            ];
            if (env('APP_DEBUG', false)) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->success = true;
        $r->data = 'OK';
        return response()->json($r, Response::HTTP_OK);
    }

    public function productMetrics(?string $product_id = null): JsonResponse
    {
        if (($product = API::validateProductID($product_id, $r)) instanceof JsonResponse) {
            return $product;
        }

        $r->success = true;
        $r->data = (object) [
            'views' => $product->views,
            'clicks' => $product->clicks,
        ];
        return response()->json($r, Response::HTTP_OK);
    }
}
