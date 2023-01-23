<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use App\Models\LiveStreamProductGroups as mLiveStreamProductGroups;
use App\Models\LiveStreamProducts as mLiveStreamProducts;
use App\Models\LiveStreamProductsImages as mLiveStreamProductsImages;
use App\Rules\strBoolean;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class Products extends API
{
    public function doCreateProduct(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid'],
            'title' => ['required', 'string', 'min:4', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'link' => ['required', 'string', 'url', 'max:255'],
            'active' => ['nullable', new strBoolean],
            'currency' => ['nullable', 'string', 'size:3', 'in:' . implode(',', API::$valid_currencies)],
            'description' => ['nullable', 'string', 'max:600'],
            'promoted' => ['nullable', new strBoolean],
            'images' => ['nullable', 'array', 'min:1', 'max:10'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        $product_id = Str::uuid()->toString();

        if (isset($params['active'])) {
            $params['active'] = $params['active'] === 'true' ? true : false;
        }
        if (isset($params['promoted'])) {
            $params['promoted'] = $params['promoted'] === 'true' ? true : false;
        }
        if (isset($params['currency'])) {
            $params['currency'] = strtoupper($params['currency']);
        }
        if (isset($params['description'])) {
            $params['description'] = trim($params['description']);
        }
        if (isset($params['title'])) {
            $params['title'] = trim($params['title']);
        }


        try {
            $data = [
                'id' => $product_id,
            ];
            $data['company_id'] = $params['company_id'];
            $data['title'] = $params['title'];
            $data['active'] = $params['active'] ?? false;
            $data['currency'] = $params['currency'] ?? 'BRL';
            $data['description'] = $params['description'] ?? null;
            $data['price'] = $params['price'];
            $data['promoted'] = $params['promoted'] ?? false;
            if (isset($params['link']) && !empty($params['link'])) {
                $params['link'] = trim($params['link']);
                if (($link = API::registerLink($params['link'], $r)) instanceof JsonResponse) {
                    return $link;
                }
                $data['link_id'] = $link->id;
            }
            $product = mLiveStreamProducts::create($data);

            if (isset($params['images'])) {
                $params['images'] = array_map(function ($image) {
                    return trim($image);
                }, $params['images']);
                $params['images'] = array_filter($params['images'], function ($url) {
                    $url = trim($url);
                    if (empty($url)) {
                        return false;
                    }
                    if (!filter_var($url, FILTER_VALIDATE_URL)) {
                        return false;
                    }
                    return true;
                });
                if (count($params['images']) === 0) {
                    $message = (object) [
                        'type' => 'error',
                        'message' => 'No valid images were provided',
                    ];
                    if (config('app.debug')) {
                        $message->debug = 'After filtering, no images were left';
                    }
                    $r->messages[] = $message;
                    return response()->json($r, Response::HTTP_BAD_REQUEST);
                }
                foreach ($params['images'] as $url) {
                    if (($media = API::registerMediaFromUrl($url, $r)) instanceof JsonResponse) {
                        return $media;
                    }

                    $image = new mLiveStreamProductsImages();
                    $id = Uuid::uuid5(Uuid::NAMESPACE_DNS, $product_id . $media->id)->toString();

                    if ($image->where('id', '=', $id)->exists()) {
                        $message = (object) [
                            'type' => 'warning',
                            'message' => 'Product image already exists',
                        ];
                        $r->messages[] = $message;
                        continue;
                    }

                    try {
                        $image->id = $id;
                        $image->product_id = $product_id;
                        $image->media_id = $media->id;
                        $image->save();
                    } catch (\Exception $e) {
                        $message = (object) [
                            'type' => 'error',
                            'message' => 'Failed to register product image',
                        ];
                        if (config('app.debug')) {
                            $message->debug = $e->getMessage();
                        }
                        $r->messages[] = $message;
                        return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
                    }
                }
            }
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Product could not be created.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Product created successfully.'),
        ];
        $r->data = $product;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function doCreateGroup(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'story_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'title' => ['nullable', 'string', 'min:4', 'max:255'],
            'products' => ['nullable', 'array'],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        $group = null;

        // try {
        //     $group = mLiveStreamProductGroups::create([
        //         'id' => Str::uuid()->toString();,
        //         'stream_id' => $params['stream_id'] ?? null,
        //         'story_id' => $params['story_id'] ?? null,
        //     ]);
        // } catch (\Exception $e) {
        //     $message = (object) [
        //         'type' => 'error',
        //         'message' => __('Product group could not be created.'),
        //     ];
        //     if (config('app.debug')) {
        //         $message->debug = $e->getMessage();
        //     }
        //     $r->messages[] = $message;
        //     return response()->json($r, Response::HTTP_BAD_REQUEST);
        // }

        $r->data = $params;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getByCompanyID(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'order_by' => ['nullable', 'string', 'in:id,created_at,deleted_at,price,views,clicks'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        $products = [];
        $products_total = 0;

        try {
            $params['offset'] = $params['offset'] ?? 0;
            $params['limit'] = $params['limit'] ?? 50;
            $params['order_by'] = $params['order_by'] ?? 'created_at';
            $params['order'] = $params['order'] ?? 'desc';

            $products = Cache::remember('products_by_company' . $company_id, now()->addSeconds(3), function () use ($company_id, $params) {
                $products = mLiveStreamProducts::where('company_id', '=', $company_id)
                    ->where('deleted_at', '=', null)->offset($params['offset'])
                    ->limit($params['limit'])
                    ->orderBy($params['order_by'], $params['order'])
                    ->get();
                $products = $products->map(function ($product) {
                    $product->images = $product->getImages();
                    $product->link = API::getLinkUrl($product->link_id);
                    return $product;
                });
                return $products;
            });

            $products_total = Cache::remember('products_total', now()->addSeconds(3), function () use ($company_id) {
                return mLiveStreamProducts::where('company_id', '=', $company_id)
                    ->where('deleted_at', '=', null)
                    ->count();
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Products could not be fetched.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $r->data = $products;

        $r->data_info = [
            'offset' => isset($params['offset']) ? $params['offset'] : 0,
            'limit' => isset($params['limit']) ? $params['limit'] : 50,
            'count' => count($products),
            'total' => $products_total,
        ];

        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getByStreamID(Request $request, ?string $stream_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'stream_id' => ['required', 'string', 'size:36', 'uuid'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'order_by' => ['nullable', 'string', 'in:id,created_at,deleted_at,price,views,clicks'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
        ], $request->all(), ['stream_id' => $stream_id])) instanceof JsonResponse) {
            return $params;
        }

        $products = [];
        $products_total = 0;

        try {
            $params['offset'] = $params['offset'] ?? 0;
            $params['limit'] = $params['limit'] ?? 50;
            $params['order_by'] = $params['order_by'] ?? 'created_at';
            $params['order'] = $params['order'] ?? 'desc';

            $products = Cache::remember('products_by_stream_' . $stream_id, now()->addSeconds(3), function () use ($stream_id, $params) {
                $products = new mLiveStreamProducts();
                $products = $products->getProductsByStreamID($stream_id)
                    ->offset($params['offset'])
                    ->limit($params['limit'])
                    ->orderBy($params['order_by'], $params['order'])
                    ->get()->map(function ($product) {
                        $product->images = $product->getImages();
                        $product->link = API::getLinkUrl($product->link_id);
                        return $product;
                    });
                return $products;
            });

            $products_total = Cache::remember('products_by_stream_total_', now()->addSeconds(3), function () use ($stream_id) {
                $products = new mLiveStreamProducts();
                $products = $products->getProductsByStreamID($stream_id)->count();
                return $products;
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Products could not be fetched.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $r->data = $products;

        $r->data_info = [
            'offset' => isset($params['offset']) ? $params['offset'] : 0,
            'limit' => isset($params['limit']) ? $params['limit'] : 50,
            'count' => count($products),
            'total' => $products_total,
        ];

        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getByStoryID(Request $request, ?string $story_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'story_id' => ['required', 'string', 'size:36', 'uuid'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'order_by' => ['nullable', 'string', 'in:id,created_at,deleted_at,price,views,clicks'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
        ], $request->all(), ['story_id' => $story_id])) instanceof JsonResponse) {
            return $params;
        }

        $products = [];
        $products_total = 0;

        try {
            $params['offset'] = $params['offset'] ?? 0;
            $params['limit'] = $params['limit'] ?? 50;
            $params['order_by'] = $params['order_by'] ?? 'created_at';
            $params['order'] = $params['order'] ?? 'desc';

            $products = Cache::remember('products_by_story_' . $story_id, now()->addSeconds(3), function () use ($story_id, $params) {
                $products = new mLiveStreamProducts();
                $products = $products->getProductsByStoryID($story_id)
                    ->offset($params['offset'])
                    ->limit($params['limit'])
                    ->orderBy($params['order_by'], $params['order'])
                    ->get()->map(function ($product) {
                        $product->images = $product->getImages();
                        $product->link = API::getLinkUrl($product->link_id);
                        return $product;
                    });
                return $products;
            });

            $products_total = Cache::remember('products_by_story_total_', now()->addSeconds(3), function () use ($story_id) {
                $products = new mLiveStreamProducts();
                $products = $products->getProductsByStoryID($story_id)->count();
                return $products;
            });
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Products could not be fetched.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $r->data = $products;

        $r->data_info = [
            'offset' => isset($params['offset']) ? $params['offset'] : 0,
            'limit' => isset($params['limit']) ? $params['limit'] : 50,
            'count' => count($products),
            'total' => $products_total,
        ];

        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getByProductID(Request $request, ?string $product_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'product_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['product_id' => $product_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($product = API::getProduct($r, $params['product_id'])) instanceof JsonResponse) {
            return $product;
        }

        $product->images = $product->getImages();
        $product->link = API::getLinkUrl($product->link_id);

        $r->data = $product;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function productUpdate(Request $request, ?string $product_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'product_id' => ['required', 'string', 'size:36', 'uuid'],
            'active' => ['nullable', new strBoolean],
            'currency' => ['nullable', 'string', 'size:3', 'in:' . implode(',', API::$valid_currencies)],
            'description' => ['nullable', 'string', 'max:600'],
            'link' => ['nullable', 'string', 'url', 'max:255'],
            'price' => ['nullable', 'integer', 'min:0'],
            'promoted' => ['nullable', new strBoolean],
            'title' => ['nullable', 'string', 'min:4', 'max:255'],
        ], $request->all(), ['product_id' => $product_id])) instanceof JsonResponse) {
            return $params;
        }

        if ($params['active'] === null && $params['currency'] === null && $params['description'] === null && $params['link'] === null && $params['price'] === null && $params['promoted'] === null && $params['title'] === null) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('No data to update.'),
            ];
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if (($product = API::getProduct($r, $product_id)) instanceof JsonResponse) {
            return $product;
        }

        try {
            if (isset($params['active'])) {
                $product->active = $params['active'];
            }
            if (isset($params['currency'])) {
                $product->currency = $params['currency'];
            }
            if (isset($params['description'])) {
                $product->description = $params['description'];
            }
            if (isset($params['link'])) {
                $product->link = $params['link'];
            }
            if (isset($params['price'])) {
                $product->price = $params['price'];
            }
            if (isset($params['promoted'])) {
                $product->promoted = $params['promoted'];
            }
            if (isset($params['title'])) {
                $params['link'] = trim($params['link']);
                if (($link = API::registerLink($params['link'], $r)) instanceof JsonResponse) {
                    return $link;
                }
                $product->link_id = $link->id;
            }
            $product->save();
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Product could not be updated.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->data = $product;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function productDelete(Request $request, ?string $product_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'product_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['product_id' => $product_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($product = API::getProduct($r, $product_id)) instanceof JsonResponse) {
            return $product;
        }

        $now = now()->format('Y-m-d H:i:s');

        try {
            $product->deleted_at = $now;
            $product->save();
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Product could not be deleted.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->success = true;
        $r->data = (object) [
            'deleted_at' => $now,
        ];
        return response()->json($r, Response::HTTP_OK);
    }

    public function productMetricViews(Request $request, ?string $product_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'product_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['product_id' => $product_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($product = API::getProduct($r, $product_id)) instanceof JsonResponse) {
            return $product;
        }

        try {
            $product->increment('views');
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to update live stream product.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->success = true;
        $r->data = 'OK';
        return response()->json($r, Response::HTTP_OK);
    }

    public function productMetricClicks(Request $request, ?string $product_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'product_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['product_id' => $product_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($product = API::getProduct($r, $product_id)) instanceof JsonResponse) {
            return $product;
        }

        try {
            $product->increment('clicks');
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Failed to update live stream product.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->success = true;
        $r->data = 'OK';
        return response()->json($r, Response::HTTP_OK);
    }

    public function productMetrics(Request $request, ?string $product_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'product_id' => ['required', 'string', 'size:36', 'uuid'],
        ], $request->all(), ['product_id' => $product_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($product = API::getProduct($r, $product_id)) instanceof JsonResponse) {
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
