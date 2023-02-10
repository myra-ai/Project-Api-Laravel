<?php

use App\Events\Chat\SendMessage;
use App\Http\Controllers as C;
use App\Http\Controllers\Live as L;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::post('/stream/{company_id}', [L\Streams::class, 'doCreate']);
Route::put('/stream/{stream_id}', [L\Streams::class, 'doUpdate']);
Route::get('/stream/{stream_id}', [L\Streams::class, 'getByStreamID']);
Route::get('/stream/widget/{stream_id}', [L\Streams::class, 'getWidgetByStreamID']);
Route::get('/streams/{company_id}', [L\Streams::class, 'getByCompanyID']);
Route::delete('/stream/{stream_id}', [L\Streams::class, 'doDelete']);

Route::get('/stream/rtmp/{stream_id}', [L\Streams::class, 'getRTMP']);

Route::get('/stream/metrics/{stream_id}', [L\Streams::class, 'getMetrics']);
Route::get('/stream/{live_id}/metric/widget/loads', [L\Streams::class, 'streamMetricWidgetLoads']);
Route::post('/stream/{live_id}/metric/widget/loads', [L\Streams::class, 'streamAddMetricWidgetLoads']);
Route::get('/stream/{live_id}/metric/widget/clicks', [L\Streams::class, 'streamMetricWidgetClicks']);
Route::post('/stream/{live_id}/metric/widget/clicks', [L\Streams::class, 'streamAddMetricWidgetClicks']);

Route::post('/story/{company_id}', [L\Stories::class, 'doCreate']);
Route::get('/stories/{company_id}', [L\Stories::class, 'getByStoryCompanyID']);
Route::get('/story/swipe/{storie_id}', [L\Stories::class, 'storieList']);
Route::get('/story/{story_id}', [L\Stories::class, 'getByStoryID']);
Route::put('/story/{story_id}', [L\Stories::class, 'doUpdate']);
Route::delete('/story/{story_id}', [L\Stories::class, 'doDelete']);

// Route::get('/stream/{live_id}/asset', [L\Streams::class, 'streamAsset']);
// Route::get('/stream/assets', [L\Streams::class, 'streamAssets']);

// Route::get('/stream/{live_id}/playback', [L\Streams::class, 'streamPlayback']);
// Route::get('/stream/playbacks', [L\Streams::class, 'streamPlaybacks']);

Route::get('/stream/status/{stream_id}', [L\Streams::class, 'getStatus']);
Route::get('/stream/views/current/{stream_id}', [L\Streams::class, 'getCurrentViews']);

Route::post('/like', [L\Likes::class, 'addLike']);
Route::delete('/unlike', [L\Likes::class, 'removeLike']);
Route::get('/likes', [L\Likes::class, 'getLikes']);

Route::post('/comment', [L\Comments::class, 'addComment']);
Route::get('/comments', [L\Comments::class, 'getComments']);
Route::get('/comments/count', [L\Comments::class, 'getCommentsCount']);

Route::post('/product/stream', [L\Products::class, 'addStreamProduct']);
Route::post('/product/{company_id}', [L\Products::class, 'doCreateProduct']);
Route::post('/product/image/{media_id}', [L\Products::class, 'addImage']);
Route::post('/product/stream', [L\Products::class, 'addStreamProduct']);
Route::delete('/product/stream', [L\Products::class, 'removeStreamProduct']);
Route::put('/product/promote/status/{group_ip}', [L\Products::class, 'updateProductPromoteStatus']);
Route::get('/products/company/{company_id}', [L\Products::class, 'getByCompanyID']);
Route::get('/products/stream/{company_id}', [L\Products::class, 'getByStreamID']);
Route::get('/products/story/{company_id}', [L\Products::class, 'getByStoryID']);

Route::get('/product/group/{group_id}', [L\Products::class, 'getGroupByID']);
Route::delete('/product/group/{group_id}', [L\Products::class, 'doDeleteGroup']);

Route::get('/product/{product_id}', [L\Products::class, 'getByProductID']);
Route::put('/product/{product_id}', [L\Products::class, 'productUpdate']);
Route::delete('/product/{product_id}', [L\Products::class, 'productDelete']);
Route::delete('/product/image/{image_id}', [L\Products::class, 'removeImage']);
Route::post('/product/{product_id}/metric/view', [L\Products::class, 'productMetricViews']);
Route::post('/product/{product_id}/metric/click', [L\Products::class, 'productMetricClicks']);
Route::get('/product/{product_id}/metrics', [L\Products::class, 'productMetrics']);

Route::post('/media/{company_id}', [L\Medias::class, 'doUploadMediaByFile']);
Route::post('/media/url/{company_id}', [L\Medias::class, 'doUploadMediaByUrl']);
Route::delete('/media/{media_id}', [L\Medias::class, 'doDeleteMedia']);
Route::get('/media/{media_id}', [L\Medias::class, 'getMediaByID']);
Route::get('/media/raw/id/{media_id}', [L\Medias::class, 'getMediaRawByID']);
Route::get('/media/raw/thumbnail/{media_id}', [L\Medias::class, 'getThumbnailRawByMediaID']);
Route::get('/media/raw/{path}', [L\Medias::class, 'getMediaRawByPath'])->where('path', '.*');

Route::get('/widget/{company_id}/stream', [L\Widget::class, 'getWidgetStream']);
Route::get('/widget/{company_id}/story', [L\Widget::class, 'getWidgetStory']);

Route::post('/account/create', [C\Account::class, 'doCreate']);
Route::post('/account/login', [C\Account::class, 'doLogin']);
Route::post('/account/validate/token/{token}', [C\Account::class, 'doValidateToken']);
Route::post('/account/logout/{token}', [C\Account::class, 'doLogout']);
Route::post('/account/password/reset', [C\Account::class, 'doResetPassword']);
Route::post('/account/password/reset/{token}', [C\Account::class, 'doResetPasswordVerify']);
Route::post('/account/password/change', [C\Account::class, 'doChangePassword']);
Route::get('/account/users', [C\Account::class, 'getUsers']);
Route::put('/account/user/{user_id}', [C\Account::class, 'doUpdateUser']);
Route::put('/account/user/{user_id}/password', [C\Account::class, 'doUpdateUserPassword']);
Route::delete('/account/user/{user_id}', [C\Account::class, 'doDeleteUser']);

Route::get('/company/{company_id}', [L\Company::class, 'getCompanyByID']);
Route::put('/company/{company_id}', [L\Company::class, 'doUpdateCompany']);
Route::get('/company/{company_id}/settings', [L\Company::class, 'getCompanySettings']);
Route::put('/company/{company_id}/settings', [L\Company::class, 'doUpdateCompanySettings']);
Route::delete('/company/{company_id}', [L\Company::class, 'doDeleteCompany']);

Route::get('/l/{link_id}', [C\Links::class, 'getLink']);

Route::get('/metrics/accessed/most/ip', [L\Metrics::class, 'getMostAccessedIp']);
Route::get('/metrics/accessed/most/city', [L\Metrics::class, 'getMostAccessedCountry']);
Route::get('/metrics/accessed/most/country', [L\Metrics::class, 'getMostAccessedCountry']);
Route::get('/metrics/accessed/most/referer', [L\Metrics::class, 'getMostAccessedReferer']);
Route::get('/metrics/acceses/total/days', [L\Metrics::class, 'getTotalAccessByDays']);
Route::get('/metrics/acceses/average/days', [L\Metrics::class, 'getAverageAccessByDays']);

Route::get('/healthcheck', function () {
    return response()->json([
        'code' => 200,
        'requested_at' => now(),
    ], Response::HTTP_OK);
});