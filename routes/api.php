<?php

use App\Http\Controllers as C;
use App\Http\Controllers\Live as L;
use App\Http\Middleware\CORS;
use Illuminate\Http\Response;
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


Route::post('/stream/{company_id}', [L\Streams::class, 'doCreate']);
Route::put('/stream/{stream_id}', [L\Streams::class, 'doUpdate']);
Route::get('/stream/{stream_id}', [L\Streams::class, 'getByStreamID']);
Route::get('/stream/widget/{stream_id}', [L\Streams::class, 'getWidgetByStreamID']);
Route::get('/streams/{company_id}', [L\Streams::class, 'getByCompanyID']);
Route::delete('/stream/{stream_id}', [L\Streams::class, 'doDelete']);

Route::get('/stream/rtmp/{stream_id}', [L\Streams::class, 'getRTMP']);

Route::post('/story/{company_id}', [L\Stories::class, 'doCreate']);
Route::get('/stories/{company_id}', [L\Stories::class, 'getListCompanyId']);
Route::get('/stories/{company_id}/count', [L\Stories::class, 'getTotalCountByCompanyId']);
Route::get('/story/{story_id}', [L\Stories::class, 'getById']);
Route::put('/story/{story_id}', [L\Stories::class, 'doUpdate']);
Route::delete('/story/{story_id}', [L\Stories::class, 'doDelete']);

Route::post('/swipe/{company_id}', [L\Swipes::class, 'doCreate']);
Route::get('/swipes/{company_id}', [L\Swipes::class, 'getListByCompanyId']);
Route::get('/swipe/{swipe_id}', [L\Swipes::class, 'getById']);
Route::get('/swipe/{swipe_id}/stories', [L\Swipes::class, 'getStoriesBySwipeId']);
Route::put('/swipe/{swipe_id}', [L\Swipes::class, 'doUpdate']);
Route::delete('/swipe/{swipe_id}', [L\Swipes::class, 'doDelete']);
Route::post('/swipe/{swipe_id}/story', [L\Swipes::class, 'doAttachStory']);
Route::delete('/swipe/{swipe_id}/story', [L\Swipes::class, 'doDetachStory']);

Route::get('/stream/status/{stream_id}', [L\Streams::class, 'getStatus']);
Route::get('/stream/views/current/{stream_id}', [L\Streams::class, 'getCurrentViews']);

Route::post('/like', [L\Likes::class, 'addLike']);
Route::delete('/unlike', [L\Likes::class, 'removeLike']);
Route::get('/likes', [L\Likes::class, 'getLikes']);

Route::post('/comment', [L\Comments::class, 'addComment']);
Route::get('/comments', [L\Comments::class, 'getComments']);
Route::get('/comments/count', [L\Comments::class, 'getCommentsCount']);

Route::post('/product/{product_id}/stream', [L\Products::class, 'addStreamOrStoryProduct']);
Route::post('/product/{product_id}/story', [L\Products::class, 'addStreamOrStoryProduct']);
Route::post('/product/{company_id}', [L\Products::class, 'doCreateProduct']);
Route::post('/product/image/{media_id}', [L\Products::class, 'addImage']);
Route::delete('/product/{product_id}/stream', [L\Products::class, 'removeStreamOrStoryProduct']);
Route::delete('/product/{product_id}/story', [L\Products::class, 'removeStreamOrStoryProduct']);
Route::put('/product/promote/status/{group_ip}', [L\Products::class, 'updateProductPromoteStatus']);
Route::get('/products/company/{company_id}', [L\Products::class, 'getByCompanyID']);
Route::get('/products/stream/{company_id}', [L\Products::class, 'getByStreamID']);
Route::get('/products/story/{company_id}', [L\Products::class, 'getByStoryID']);
Route::get('/product/group/{group_id}', [L\Products::class, 'getGroupByID']);
Route::delete('/product/group/{group_id}', [L\Products::class, 'doDeleteGroup']);
Route::get('/product/{product_id}', [L\Products::class, 'getByProductID']);
Route::put('/product/{product_id}', [L\Products::class, 'productUpdate']);
Route::delete('/product/image/{image_id}', [L\Products::class, 'removeImage']);
Route::delete('/product/{product_id}', [L\Products::class, 'productDelete']);

Route::post('/media/file', [L\Medias::class, 'doUploadMediaByFile']);
Route::post('/media/url', [L\Medias::class, 'doUploadMediaByUrl']);
Route::delete('/media/{media_id}', [L\Medias::class, 'doDeleteMedia']);
Route::get('/media/{media_id}', [L\Medias::class, 'getMediaByID']);
Route::post('/media/{media_id}/optimize', [L\Medias::class, 'doOptimizeMedia']);
Route::get('/media/raw/id/{media_id}', [L\Medias::class, 'getMediaRawByID']);
Route::get('/media/raw/thumbnail/{media_id}', [L\Medias::class, 'getThumbnailRawByMediaID']);
Route::get('/media/raw/{path}', [L\Medias::class, 'getMediaRawByPath'])->where('path', '.*');

Route::get('/widget/{company_id}', [L\Widget::class, 'getWidget']);

Route::post('/account/create', [C\Account::class, 'doCreate']);
Route::post('/account/login', [C\Account::class, 'doLogin']);
Route::post('/account/validate/token/{token}', [C\Account::class, 'doValidateToken']);
Route::post('/account/logout/{token}', [C\Account::class, 'doLogout']);
Route::post('/account/password/reset', [C\Account::class, 'doResetPassword']);
Route::post('/account/password/reset/{token}', [C\Account::class, 'doResetPasswordVerify']);
Route::post('/account/password/change', [C\Account::class, 'doChangePassword']);
Route::post('/account/user', [C\Account::class, 'doCreateUser']);
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
Route::get('/metrics/top/streams', [L\Metrics::class, 'getTopStreams']);
Route::get('/metrics/top/stories', [L\Metrics::class, 'getTopStories']);
Route::get('/metrics/story/{story_id}', [L\Metrics::class, 'getStoryMetric']);

Route::get('/language/translations/{language}', [C\Languages::class, 'getTranslations'])->name('language.translations');
// Route::get('/language/translations/{language}/download', [C\Languages::class, 'downloadTranslations'])->withoutMiddleware([CORS::class])->name('language.download');
Route::get('/language/availables', [C\Languages::class, 'getAvailableLanguages'])->name('language.availables');

Route::get('/healthcheck', function () {
    return response()->json([
        'health' => 'OK',
    ], Response::HTTP_OK);
})->name('healthcheck')->withoutMiddleware(['throttle:api']);
