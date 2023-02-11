<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use App\Models\LiveStreamProductGroups as mLiveStreamProductGroups;
use App\Models\LiveStreamProducts as mLiveStreamProducts;
use App\Models\LiveStreamProductsImages as mLiveStreamProductsImages;
use App\Models\LiveStreamMedias as mLiveStreamMedias;
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
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
            'title' => ['required', 'string', 'min:4', 'max:110'],
            'link' => ['required', 'string', 'url', 'max:300'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', 'integer', 'min:0', 'max:1'],
            'currency' => ['nullable', 'string', 'size:3', 'in:' . implode(',', API::$valid_currencies)],
            'description' => ['nullable', 'string', 'max:2000'],
            'images' => ['nullable', 'string'],
            'images_url' => ['nullable', 'array', 'min:1', 'max:10'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        $product_id = Str::uuid()->toString();

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
                'company_id' => $params['company_id'],
                'title' => $params['title'],
                'status' => $params['status'] ?? false,
                'currency' => $params['currency'] ?? 'BRL',
                'description' => $params['description'] ?? null,
                'price' => $params['price'],
            ];

            if (isset($params['link'])) {
                $params['link'] = trim($params['link']);
                if (($link = API::registerLink($params['link'], $r)) instanceof JsonResponse) {
                    return $link;
                }
                $data['link_id'] = $link->id;
            }

            $product = mLiveStreamProducts::create($data);

            if (isset($params['images'])) {
                $params['images'] = trim($params['images']);
                $params['images'] = explode(';', $params['images']);
                $params['images'] = array_map(function ($image) {
                    return trim($image);
                }, $params['images']);
                $params['images'] = array_filter($params['images'], function ($image) {
                    return !empty($image);
                });

                foreach ($params['images'] as $media_id) {
                    if (!Uuid::isValid($media_id)) {
                        $message = (object) [
                            'type' => 'error',
                            'message' => 'Invalid image id',
                        ];
                        if (config('app.debug')) {
                            $message->debug = 'Image id is not a valid UUID';
                        }
                        $r->messages[] = $message;
                        continue;
                    }

                    $media = Cache::remember('media_by_id_' . $media_id, now()->addSeconds(30), function () use ($media_id) {
                        return mLiveStreamMedias::where('id', '=', $media_id)->first();
                    });

                    if ($media === null) {
                        $message = (object) [
                            'type' => 'error',
                            'message' => __('Media not found'),
                        ];
                        if (config('app.debug')) {
                            $message->debug = 'Image not found in database';
                        }
                        $r->messages[] = $message;
                        continue;
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
                    }
                }
            }

            if (isset($params['images_url'])) {
                $params['images_url'] = array_map(function ($image) {
                    return trim($image);
                }, $params['images_url']);
                $params['images_url'] = array_filter($params['images_url'], function ($url) {
                    $url = trim($url);
                    if (empty($url)) {
                        return false;
                    }
                    if (!filter_var($url, FILTER_VALIDATE_URL)) {
                        return false;
                    }
                    return true;
                });
                if (count($params['images_url']) === 0) {
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
                foreach ($params['images_url'] as $url) {
                    if (($media = API::registerMediaFromUrl($url, r: $r)) instanceof JsonResponse) {
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

        $product->refresh();

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Product created successfully.'),
        ];
        $r->data = (object) [
            'id' => $product_id,
            'created' => now()->toISOString(),
            'images' => $product->getImagesDetails(),
            'link' => (object)[
                'id' => $link->id,
            ],
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function addStreamProduct(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'product_id' => ['required', 'string', 'size:36', 'uuid'],
            'stream_id' => ['required', 'string', 'size:36', 'uuid'],
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
            'get_product' => ['nullable', new strBoolean],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
            return $stream;
        }

        if (($product = API::getProduct($r, $params['product_id'])) instanceof JsonResponse) {
            return $product;
        }

        $params['get_product'] = $params['get_product'] ?? false;

        $group = new mLiveStreamProductGroups();
        $getGroup = $group->where('product_id', '=', $product->id)->where('stream_id', $stream->id)->first();

        if ($getGroup !== null) {
            $message = (object) [
                'type' => 'warning',
                'message' => __('The product is already in the stream.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_OK);
        }

        $id = Str::uuid()->toString();

        $group->id = $id;
        $group->stream_id = $stream->id;
        $group->product_id = $product->id;
        if (!$group->save()) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Product could not be added to the stream.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Product added to the stream successfully.'),
        ];

        if ($params['get_product'] === true) {
            $product->images = $product->getImagesDetails();
            $product->link = $product->getLink();

            $group = $product->getGroup();
            $product->promoted = $group !== null ? boolval($group->promoted) : false;
            unset($group->promoted);

            $product->group = $group;

            $product->created = $product->created_at;
            $data = (object) [
                'created_at' => now()->toISOString(),
                'product' => $product,
            ];
        } else {
            $data = (object) [
                'group_id' => $id,
                'created_at' => now()->toISOString(),
            ];
        }

        $r->data = $data;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function removeStreamProduct(Request $request): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'product_id' => ['required', 'string', 'size:36', 'uuid'],
            'stream_id' => ['required', 'string', 'size:36', 'uuid'],
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
            'get_product' => ['nullable', new strBoolean],
        ], $request->all())) instanceof JsonResponse) {
            return $params;
        }

        if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
            return $stream;
        }

        if (($product = API::getProduct($r, $params['product_id'])) instanceof JsonResponse) {
            return $product;
        }

        $params['get_product'] = $params['get_product'] ?? false;

        $group = mLiveStreamProductGroups::where('product_id', '=', $product->id)->where('stream_id', $stream->id);

        if ($group->first() === null) {
            $message = (object) [
                'type' => 'warning',
                'message' => __('The product is not in the stream.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_OK);
        }

        if (!$group->delete()) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Product could not be removed from the stream.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Product removed from the stream successfully.'),
        ];

        if ($params['get_product'] === true) {
            $product->images = $product->getImagesDetails();
            $product->link = $product->getLink();

            $group = $product->getGroup();
            $product->promoted = $group !== null ? boolval($group->promoted) : false;
            unset($group->promoted);

            $product->group = $group;

            $product->created = $product->created_at;
            $data = (object) [
                'deleted_at' => now()->toISOString(),
                'product' => $product,
            ];
        } else {
            $data = (object) [
                'deleted_at' => now()->toISOString(),
            ];
        }

        $r->data = $data;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function updateProductPromoteStatus(Request $request, ?string $group_ip = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'group_ip' => ['required', 'string', 'size:36', 'uuid'],
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
        ], $request->all(), ['group_ip' => $group_ip])) instanceof JsonResponse) {
            return $params;
        }

        if (($group = API::getProductGroup($r, $params['group_ip'])) instanceof JsonResponse) {
            return $group;
        }

        try {
            $group->promoted = ($group->promoted === true ? false : true);
            $group->save();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Product could not be updated.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Product promoted status updated successfully.'),
        ];
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function getByCompanyID(Request $request, ?string $company_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'company_id' => ['required', 'string', 'size:36', 'uuid'],
            'token' => ['nullable', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'order_by' => ['nullable', 'string', 'in:id,created_at,deleted_at,price,views,clicks'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
            'group_attached' => ['nullable', new strBoolean],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        $products = [];
        $products_total = 0;

        try {
            $params['offset'] = $params['offset'] ?? 0;
            $params['limit'] = $params['limit'] ?? 80;
            $params['order_by'] = $params['order_by'] ?? 'created_at';
            $params['order'] = $params['order'] ?? 'asc';
            $params['group_attached'] = $params['group_attached'] ?? false;
            $has_token = isset($params['token']);

            $products = match ($has_token) {
                true => Cache::remember('products_by_company_' . $company_id . '_with_token', now()->addSecond(), function () use ($company_id, $params) {
                    $products = mLiveStreamProducts::where('company_id', '=', $company_id)
                        ->where('deleted_at', '=', null)->offset($params['offset'])
                        ->limit($params['limit'])
                        ->orderBy($params['order_by'], $params['order'])
                        ->get();
                    return $products->map(function ($product) use ($params) {
                        $product->images = $product->getImagesDetails();
                        $product->link = $product->getLink();

                        $group = $product->getGroup();
                        $product->promoted = $group !== null ? boolval($group->promoted) : false;
                        unset($group->promoted);

                        if ($params['group_attached']) {
                            $product->group = $group;
                        }

                        $product->created = $product->created_at;
                        return $product;
                    });
                }),
                default => Cache::remember('products_by_company_' . $company_id . '_without_token', now()->addSeconds(3), function () use ($company_id, $params) {
                    $products = mLiveStreamProducts::where('company_id', '=', $company_id)
                        ->where('status', '=', 1)
                        ->where('deleted_at', '=', null)->offset($params['offset'])
                        ->limit($params['limit'])
                        ->orderBy($params['order_by'], $params['order'])
                        ->get();
                    $products = $products->map(function ($product) use ($params) {
                        $product->images = $product->getImages();
                        $product->link = API::getLinkUrl($product->link_id);

                        $group = $product->getGroup();
                        $product->promoted = $group !== null ? boolval($group->promoted) : false;
                        unset($group->promoted);

                        $product->makeHidden(['status']);
                        return $product;
                    });
                    return $products;
                })
            };

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
            'token' => ['nullable', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
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
            $params['limit'] = $params['limit'] ?? 80;
            $params['order_by'] = $params['order_by'] ?? 'created_at';
            $params['order'] = $params['order'] ?? 'asc';
            $has_token = isset($params['token']);

            $products = match ($has_token) {
                true => Cache::remember('products_by_stream_' . $stream_id . '_with_token', now()->addSeconds(3), function () use ($stream_id, $params) {
                    $products = new mLiveStreamProducts();
                    $products = $products->getProductsByStreamID($stream_id)
                        ->offset($params['offset'])
                        ->limit($params['limit'])
                        ->orderBy($params['order_by'], $params['order'])
                        ->get();
                    return $products->map(function ($product) {
                        $product->images = $product->getImagesDetails();
                        $product->link = API::getLinkUrl($product->link_id);

                        $group = $product->getGroup();
                        $group->makeHidden(['promoted']);

                        $product->promoted = $group !== null ? boolval($group->promoted) : false;

                        $product->group = $group;
                        $product->makeVisible(['created_at']);
                        return $product;
                    });
                }),
                default => Cache::remember('products_by_stream_' . $stream_id, now()->addSeconds(3), function () use ($stream_id, $params) {
                    $products = new mLiveStreamProducts();
                    $products = $products->getProductsByStreamID($stream_id)
                        ->offset($params['offset'])
                        ->limit($params['limit'])
                        ->orderBy($params['order_by'], $params['order'])
                        ->get();
                    return $products->map(function ($product) {
                        $product->images = $product->getImages();
                        $product->link = API::getLinkUrl($product->link_id);

                        $group = $product->getGroup();
                        $group->makeHidden(['promoted']);

                        $product->promoted = $group !== null ? boolval($group->promoted) : false;

                        $product->group = $group;
                        $product->makeHidden(['status', 'group_id']);
                        return $product;
                    });
                })
            };

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
            'offset' => $params['offset'],
            'limit' => $params['limit'],
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
            'token' => ['nullable', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
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
            $params['limit'] = $params['limit'] ?? 80;
            $params['order_by'] = $params['order_by'] ?? 'created_at';
            $params['order'] = $params['order'] ?? 'asc';
            $has_token = isset($params['token']);
            

            $products = match ($has_token) {
                true => Cache::remember('products_by_story_' . $story_id . '_with_token', now()->addSeconds(3), function () use ($story_id, $params) {
                    $products = new mLiveStreamProducts();
                    $products = $products->getProductsByStoryID($story_id)
                        ->offset($params['offset'])
                        ->limit($params['limit'])
                        ->orderBy($params['order_by'], $params['order'])
                        ->get();
                    return $products->map(function ($product) {
                        $product->images = $product->getImagesDetails();
                        $product->link = API::getLinkUrl($product->link_id);

                        $group = $product->getGroup();
                        $group->makeHidden(['promoted']);

                        $product->promoted = $group !== null ? boolval($group->promoted) : false;

                        $product->group = $group;
                        $product->makeVisible(['created_at']);
                        return $product;
                    });
                }),
                default => Cache::remember('products_by_story_' . $story_id, now()->addSeconds(3), function () use ($story_id, $params) {
                    $products = new mLiveStreamProducts();
                    $products = $products->getProductsByStoryID($story_id)
                        ->offset($params['offset'])
                        ->limit($params['limit'])
                        ->orderBy($params['order_by'], $params['order'])
                        ->get();
                    return $products->map(function ($product) {
                        $product->images = $product->getImages();
                        $product->link = API::getLinkUrl($product->link_id);

                        $group = $product->getGroup();
                        $group->makeHidden(['promoted']);

                        $product->promoted = $group !== null ? boolval($group->promoted) : false;

                        $product->group = $group;
                        $product->makeHidden(['status', 'group_id']);
                        return $product;
                    });
                })
            };

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
            'offset' => $params['offset'],
            'limit' => $params['limit'],
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
            'token' => ['nullable', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
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
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
            'status' => ['nullable', 'integer', 'min:0', 'max:1'],
            'currency' => ['nullable', 'string', 'size:3', 'in:' . implode(',', API::$valid_currencies)],
            'description' => ['nullable', 'string', 'max:2000'],
            'link' => ['nullable', 'string', 'url', 'max:300'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'title' => ['nullable', 'string', 'min:4', 'max:110'],
        ], $request->all(), ['product_id' => $product_id])) instanceof JsonResponse) {
            return $params;
        }

        $params['status'] = isset($params['status']) ? $params['status'] : null;
        $params['currency'] = isset($params['currency']) ? strtoupper($params['currency']) : null;
        $params['price'] = isset($params['price']) ? $params['price'] : null;
        $params['link'] = isset($params['link']) ? trim($params['link']) : null;
        $params['title'] = isset($params['title']) ? $params['title'] : null;
        $params['description'] = isset($params['description']) ? $params['description'] : null;

        if ($params['status'] === null && $params['currency'] === null && $params['description'] === null && $params['link'] === null && $params['price'] === null && $params['title'] === null) {
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
            if (isset($params['status'])) {
                $product->status = $params['status'];
            }
            if (isset($params['currency'])) {
                $product->currency = $params['currency'];
            }
            if (isset($params['description'])) {
                $product->description = $params['description'];
            }
            if (isset($params['link'])) {
                if (($link = API::registerLink($params['link'], $r)) instanceof JsonResponse) {
                    return $link;
                }
                $product->link_id = $link->id;
            }
            if (isset($params['price'])) {
                $product->price = $params['price'];
            }
            if (isset($params['title'])) {
                $product->title = $params['title'];
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

        $product->images = $product->getImagesDetails();
        $product->link = $product->getLink();

        unset($product->id);

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Product updated.'),
        ];
        $r->data = $product;
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function addImage(Request $request, ?string $media_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'media_id' => ['required', 'string', 'size:36', 'uuid'],
            'product_id' => ['required', 'string', 'size:36', 'uuid'],
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
        ], $request->all(), ['media_id' => $media_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($media = API::getMedia($r, $params['media_id'])) instanceof JsonResponse) {
            return $media;
        }

        if (($product = API::getProduct($r, $params['product_id'])) instanceof JsonResponse) {
            return $product;
        }

        if (($image_id = $product->addImage($media_id)) === false) {
            $r->messages[] = (object) [
                'type' => 'error',
                'message' => __('Product image could not be added.'),
            ];
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->success = true;
        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Product image added.'),
        ];
        $r->data = (object) [
            'image_id' => $image_id,
            'created_at' => now(),
        ];
        return response()->json($r, Response::HTTP_OK);
    }

    public function removeImage(Request $request, ?string $image_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'image_id' => ['required', 'string', 'size:36', 'uuid'],
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
        ], $request->all(), ['image_id' => $image_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($image = API::getImageProduct($r, $image_id)) instanceof JsonResponse) {
            return $image;
        }

        try {
            $image->delete();
        } catch (\Exception $e) {
            $message = [
                'type' => 'error',
                'message' => __('Image could not be deleted.'),
            ];
            if (config('app.debug')) {
                $message['debug'] = __($e->getMessage());
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $r->success = true;
        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Product image deleted.'),
        ];
        $r->data = (object) [
            'deleted_at' => now(),
        ];
        return response()->json($r, Response::HTTP_OK);
    }

    public function productDelete(Request $request, ?string $product_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'product_id' => ['required', 'string', 'size:36', 'uuid'],
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
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
        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Product successfully deleted.'),
        ];
        $r->data = (object) [
            'deleted_at' => $now,
        ];
        return response()->json($r, Response::HTTP_OK);
    }

    public function productMetricViews(Request $request, ?string $product_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'product_id' => ['required', 'string', 'size:36', 'uuid'],
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
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
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
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
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:livestream_company_tokens,token'],
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
