<?php

namespace App\Http\Controllers\Live;

use App\Http\Controllers\API;
use App\Models\LiveStreamMedias as mLiveStreamMedias;
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
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
            'title' => ['required', 'string', 'min:4', 'max:110'],
            'link' => ['nullable', 'string', 'url', 'max:300'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', 'integer', 'min:0', 'max:1'],
            'currency' => ['nullable', 'string', 'size:3', 'in:' . implode(',', API::$valid_currencies)],
            'description' => ['nullable', 'string', 'max:2000'],
            'images' => ['nullable', 'string'],
            'images_url' => ['nullable', 'string'],
            'get_product' => ['nullable',  new strBoolean],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        $product_id = Str::uuid()->toString();

        $params['currency'] = isset($params['currency']) ? strtoupper($params['currency']) : 'BRL';
        $params['status'] = isset($params['status']) ? filter_var($params['status'], FILTER_VALIDATE_BOOLEAN) : false;
        $params['description'] = isset($params['description']) ? trim($params['description']) : null;
        $params['title'] = isset($params['title']) ? trim($params['title']) : null;
        $params['price'] = isset($params['price']) ? (float) $params['price'] : 0;
        $params['get_product'] = isset($params['get_product']) ? filter_var($params['get_product'], FILTER_VALIDATE_BOOLEAN) : false;
        $params['link'] = isset($params['link']) ? trim($params['link']) : null;
        $params['images_url'] = isset($params['images_url']) ? trim($params['images_url']) : null;

        try {
            $data = [
                'id' => $product_id,
                'company_id' => $params['company_id'],
                'title' => $params['title'],
                'status' => $params['status'],
                'currency' => $params['currency'],
                'description' => $params['description'],
                'price' => $params['price'],
            ];

            if ($params['link'] !== null) {
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

                    $media = Cache::remember('media_by_id_' . $media_id, now()->addSeconds(API::CACHE_TTL), function () use ($media_id) {
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

            if ($params['images_url'] !== null) {
                $params['images_url'] = explode(';', $params['images_url']);
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
                    if (($media = API::registerMediaFromUrl($params['company_id'], $url, r: $r)) instanceof JsonResponse) {
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

        $r->data = (object) [
            'id' => $product_id,
            'created' => now()->toISOString(),
        ];

        if ($params['get_product']) {
            $check_interval = 300; // seconds
            $timeout = 3; // seconds
            $start = microtime(true);

            // Wait for the product to be created (This method is not ideal, but work with database replication)
            while (true) {
                $product = mLiveStreamProducts::where('id', '=', $product_id)->first();
                if ($product !== null) {
                    break;
                }
                if (microtime(true) - $start >= $timeout) {
                    $message = (object) [
                        'type' => 'error',
                        'message' => __('Product could not be found'),
                    ];
                    if (config('app.debug')) {
                        $message->debug = __('Product could not be found after :timeout seconds', ['timeout' => $timeout]);
                    }
                    $r->messages[] = $message;
                    return response()->json($r, Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                usleep($check_interval * 1000);
            }

            $r->data->images = $product->getImagesDetails();
            $r->data->link = $product->getLink();
        }
        $r->success = true;
        return response()->json($r, Response::HTTP_OK);
    }

    public function addStreamOrStoryProduct(Request $request, ?string $product_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
            'product_id' => ['required', 'string', 'size:36', 'uuid'],
            'stream_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'story_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'get_product' => ['nullable', new strBoolean],
        ], $request->all(), ['product_id' => $product_id])) instanceof JsonResponse) {
            return $params;
        }

        $params['stream_id'] = isset($params['stream_id']) ? $params['stream_id'] : null;
        $params['story_id'] = isset($params['story_id']) ? $params['story_id'] : null;
        $params['get_product'] = isset($params['get_product']) ? filter_var($params['get_product'], FILTER_VALIDATE_BOOLEAN) : false;

        if (($product = API::getProduct($params['product_id'], $r)) instanceof JsonResponse) {
            return $product;
        }

        if ($params['stream_id'] !== null) {
            if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
                return $stream;
            }
            $group = new mLiveStreamProductGroups();
            $getGroup = $group->where('product_id', '=', $product->id)->where('stream_id', $stream->id)->first();
        } else if ($params['story_id'] !== null) {
            if (($story = API::getStory($r, $params['story_id'])) instanceof JsonResponse) {
                return $story;
            }
            $group = new mLiveStreamProductGroups();
            $getGroup = $group->where('product_id', '=', $product->id)->where('story_id', $story->id)->first();
        } else {
            $message = (object) [
                'type' => 'error',
                'message' => __('Stream or story id is required.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if ($getGroup !== null) {
            $message = (object) [
                'type' => 'warning',
                'message' => __('The product is already added.'),
            ];
            if (config('app.debug')) {
                $message->debug = (object) [
                    'product_id' => $product->id,
                    'stream_id' => $params['stream_id'],
                    'story_id' => $params['story_id'],
                    'group' => $getGroup,
                ];
            }
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_OK);
        }

        $id = Str::uuid()->toString();

        $group->id = $id;
        if ($params['stream_id'] !== null) {
            $group->stream_id = $stream->id;
        } else if ($params['story_id'] !== null) {
            $group->story_id = $story->id;
        }
        $group->product_id = $product->id;
        if (!$group->save()) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Product could not be added.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Product added successfully.'),
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

    public function removeStreamOrStoryProduct(Request $request, ?string $product_id = null): JsonResponse
    {
        if (($params = API::doValidate($r, [
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
            'product_id' => ['required', 'string', 'size:36', 'uuid'],
            'stream_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'story_id' => ['nullable', 'string', 'size:36', 'uuid'],
            'get_product' => ['nullable', new strBoolean],
        ], $request->all(), ['product_id' => $product_id])) instanceof JsonResponse) {
            return $params;
        }

        $params['stream_id'] = isset($params['stream_id']) ? $params['stream_id'] : null;
        $params['story_id'] = isset($params['story_id']) ? $params['story_id'] : null;
        $params['get_product'] = isset($params['get_product']) ? filter_var($params['get_product'], FILTER_VALIDATE_BOOLEAN) : false;

        if (($product = API::getProduct($params['product_id'], $r)) instanceof JsonResponse) {
            return $product;
        }

        if ($params['stream_id'] !== null) {
            if (($stream = API::getLiveStream($r, $params['stream_id'])) instanceof JsonResponse) {
                return $stream;
            }
            $group = mLiveStreamProductGroups::where('product_id', '=', $product->id)->where('stream_id', $stream->id);
        } else if ($params['story_id'] !== null) {
            if (($story = API::getStory($r, $params['story_id'])) instanceof JsonResponse) {
                return $story;
            }
            $group = mLiveStreamProductGroups::where('product_id', '=', $product->id)->where('story_id', $story->id);
        } else {
            $message = (object) [
                'type' => 'error',
                'message' => __('Stream or story id is required.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        if ($group->first() === null) {
            $message = (object) [
                'type' => 'warning',
                'message' => __('The product is not added.'),
            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_OK);
        }

        if (!$group->delete()) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Product could not be removed.'),

            ];
            $r->messages[] = $message;
            return response()->json($r, Response::HTTP_BAD_REQUEST);
        }

        $r->messages[] = (object) [
            'type' => 'success',
            'message' => __('Product removed successfully.'),
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
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
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
            'token' => ['nullable', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'order_by' => ['nullable', 'string', 'in:id,created_at,deleted_at,price,views,clicks'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
            'groups' => ['nullable', new strBoolean],
            'story_attached' => ['nullable', 'string', 'size:36', 'uuid', 'exists:stories,id'],
            'stream_attached' => ['nullable', 'string', 'size:36', 'uuid', 'exists:livestreams,id'],
        ], $request->all(), ['company_id' => $company_id])) instanceof JsonResponse) {
            return $params;
        }

        $products = [];
        $products_total = 0;

        try {
            $params['offset'] = isset($params['offset']) ? intval($params['offset']) : 0;
            $params['limit'] = isset($params['limit']) ? intval($params['limit']) : 80;
            $params['order_by'] = isset($params['order_by']) ? $params['order_by'] : 'created_at';
            $params['order'] = isset($params['order']) ? $params['order'] : 'asc';
            $params['groups'] = isset($params['groups']) ? filter_var($params['groups'], FILTER_VALIDATE_BOOLEAN) : false;
            $params['story_attached'] = isset($params['story_attached']) ? trim($params['story_attached']) : null;
            $params['stream_attached'] = isset($params['stream_attached']) ? trim($params['stream_attached']) : null;
            $has_token = isset($params['token']);

            $cache_tag = 'products_by_company_' . $company_id;
            $cache_tag .= sha1(implode('_', $params));

            $products = match ($has_token) {
                true => Cache::remember($cache_tag, now()->addSeconds(API::CACHE_TTL_PRODUCTS), function () use ($company_id, $params) {
                    return mLiveStreamProducts::where('company_id', '=', $company_id)
                        ->where('deleted_at', '=', null)->offset($params['offset'])
                        ->limit($params['limit'])
                        ->orderBy($params['order_by'], $params['order'])
                        ->get()
                        ->map(function ($product) use ($params) {
                            $product->images = $product->getImagesDetailsOptimized();
                            $product->link = $product->getLink();

                            if ($params['groups']) {
                                $product->groups = $product->getGroups()->map(function ($group) {
                                    $group->makeHidden(['product_id']);
                                    return $group;
                                });
                            }

                            $promoted = false;
                            if ($params['story_attached'] !== null) {
                                $product->attached = $product->isAttachedWithStory($params['story_attached'], $promoted);
                            } else if ($params['stream_attached'] !== null) {
                                $product->attached = $product->isAttachedWithStream($params['stream_attached'], $promoted);
                            }

                            $product->promoted = $promoted;
                            $product->created = $product->created_at;
                            return $product;
                        });
                }),
                default => Cache::remember($cache_tag, now()->addSeconds(API::CACHE_TTL_PRODUCTS), function () use ($company_id, $params) {
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

            $products_total = Cache::remember('products_by_company_' . $company_id . '_total', now()->addSeconds(API::CACHE_TTL), function () use ($company_id) {
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
            'offset' => $params['offset'],
            'limit' => $params['limit'],
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
            'token' => ['nullable', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
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
            $params['offset'] = isset($params['offset']) ? $params['offset'] : 0;
            $params['limit'] = isset($params['limit']) ? $params['limit'] : 80;
            $params['order_by'] = isset($params['order_by']) ? $params['order_by'] : 'created_at';
            $params['order'] = isset($params['order']) ? $params['order'] : 'asc';
            $has_token = isset($params['token']);

            $cache_tag = 'products_by_stream_' . $stream_id;
            $cache_tag .= sha1(implode('_', $params));

            $products = match ($has_token) {
                true => Cache::remember($cache_tag, now()->addSeconds(API::CACHE_TTL_PRODUCTS), function () use ($stream_id, $params) {
                    $products = new mLiveStreamProducts();
                    return $products->getProductsByStreamID($stream_id)
                        ->orderBy($params['order_by'], $params['order'])
                        ->offset($params['offset'])
                        ->limit($params['limit'])
                        ->get()
                        ->map(function ($product) {
                            $product->images = $product->getImagesDetailsOptimized();
                            $product->link = API::getLinkUrl($product->link_id);
                            $product->makeVisible(['created_at']);
                            return $product;
                        });
                }),
                default => Cache::remember($cache_tag, now()->addSeconds(API::CACHE_TTL_PRODUCTS), function () use ($stream_id, $params) {
                    $products = new mLiveStreamProducts();
                    return $products->getProductsByStreamID($stream_id)
                        ->offset($params['offset'])
                        ->limit($params['limit'])
                        ->orderBy($params['order_by'], $params['order'])
                        ->get()
                        ->map(function ($product) {
                            $product->images = $product->getImages();
                            $product->link = API::getLinkUrl($product->link_id);
                            $product->makeHidden(['status', 'group_id']);
                            return $product;
                        });
                })
            };

            $products_total = Cache::remember('products_by_stream_' . $stream_id . '_total_', now()->addSeconds(API::CACHE_TTL), function () use ($stream_id) {
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
            'token' => ['nullable', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'order_by' => ['nullable', 'string', 'in:id,created_at,deleted_at,price,views,clicks'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
            'group_attached' => ['nullable', new strBoolean],
        ], $request->all(), ['story_id' => $story_id])) instanceof JsonResponse) {
            return $params;
        }

        $products = [];
        $products_total = 0;

        try {
            $params['offset'] = isset($params['offset']) ? $params['offset'] : 0;
            $params['limit'] = isset($params['limit']) ? $params['limit'] : 80;
            $params['order_by'] = isset($params['order_by']) ? $params['order_by'] : 'created_at';
            $params['order'] = isset($params['order']) ? $params['order'] : 'asc';
            $params['group_attached'] = isset($params['group_attached']) ? filter_var($params['group_attached'], FILTER_VALIDATE_BOOLEAN) : null;
            $has_token = isset($params['token']);

            $cache_tag = 'products_by_story_' . $story_id;
            $cache_tag .= sha1(implode('_', $params));

            $products = match ($has_token) {
                true => Cache::remember($cache_tag, now()->addSeconds(API::CACHE_TTL_PRODUCTS), function () use ($story_id, $params) {
                    $products = new mLiveStreamProducts();
                    return $products->getProductsByStoryID($story_id)
                        ->offset($params['offset'])
                        ->limit($params['limit'])
                        ->orderBy($params['order_by'], $params['order'])
                        ->get()
                        ->map(function ($product) {
                            $product->images = $product->getImagesDetailsOptimized();
                            $product->link = API::getLinkUrl($product->link_id);
                            $product->makeVisible(['created_at']);
                            return $product;
                        });
                }),
                default => Cache::remember($cache_tag, now()->addSeconds(API::CACHE_TTL_PRODUCTS), function () use ($story_id, $params) {
                    $products = new mLiveStreamProducts();
                    return $products->getProductsByStoryID($story_id)
                        ->offset($params['offset'])
                        ->limit($params['limit'])
                        ->orderBy($params['order_by'], $params['order'])
                        ->get()->map(function ($product) {
                            $product->images = $product->getImages();
                            $product->link = API::getLinkUrl($product->link_id);
                            $product->makeHidden(['status', 'group_id']);
                            return $product;
                        });
                })
            };

            $products_total = Cache::remember('products_by_story_' . $story_id . '_total_', now()->addSeconds(API::CACHE_TTL), function () use ($story_id) {
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
            'token' => ['nullable', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
        ], $request->all(), ['product_id' => $product_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($product = API::getProduct($params['product_id'], $r)) instanceof JsonResponse) {
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
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
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

        if (($product = API::getProduct($params['product_id'], $r)) instanceof JsonResponse) {
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
            $message = (object) [
                'type' => 'error',
                'message' => __('Product could not be updated.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
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
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
        ], $request->all(), ['media_id' => $media_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($media = API::getMedia($params['media_id'], $r)) instanceof JsonResponse) {
            return $media;
        }

        if (($product = API::getProduct($params['product_id'], $r)) instanceof JsonResponse) {
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
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
        ], $request->all(), ['image_id' => $image_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($image = API::getImageProduct($r, $image_id)) instanceof JsonResponse) {
            return $image;
        }

        try {
            $image->delete();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Image could not be deleted.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
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
            'token' => ['required', 'string', 'size:60', 'regex:/^[a-zA-Z0-9]+$/', 'exists:tokens,token'],
        ], $request->all(), ['product_id' => $product_id])) instanceof JsonResponse) {
            return $params;
        }

        if (($product = API::getProduct($params['product_id'], $r)) instanceof JsonResponse) {
            return $product;
        }

        $now = now()->format('Y-m-d H:i:s');

        try {
            $product->deleted_at = $now;
            $product->save();
        } catch (\Exception $e) {
            $message = (object) [
                'type' => 'error',
                'message' => __('Product could not be deleted.'),
            ];
            if (config('app.debug')) {
                $message->debug = $e->getMessage();
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
}
