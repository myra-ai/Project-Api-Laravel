<?php

namespace App\Models;

use App\Casts\Timestamp;
use App\Http\Controllers\API;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Swipes extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'swipes';
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
        'status',
        'published',
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
        'title' => 'string',
        'status' => 'integer',
        'published' => 'boolean',
        'deleted_at' => Timestamp::class,
        'created_at' => Timestamp::class,
        'updated_at' => Timestamp::class,
    ];

    public function getSwipesByCompanyId(string $swipe_id, string $order_by = 'created_at', string $order = 'asc', int $offset = 0, int $limit = 80): Collection
    {
        return $this->where('company_id', '=', $swipe_id)
            ->where('deleted_at', '=', null)
            ->orderBy($order_by, $order)
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    public function getSwipeById(string $swipe_id, string $order_by = 'created_at', string $order = 'asc', int $offset = 0): ?Swipes
    {
        return $this->where('id', '=', $swipe_id)
            ->where('deleted_at', '=', null)
            ->orderBy($order_by, $order)
            ->offset($offset)
            ->first();
    }

    public function createSwipe(array $params): Swipes
    {
        return $this->create([
            'id' => Str::uuid()->toString(),
            'company_id' => $params['company_id'],
            'title' => $params['title'],
            'status' => $params['status'],
            'published' => false,
        ])->fresh();
    }

    public function updateSwipe(array $params, ?string &$message = null): ?bool
    {
        $update = [];
        if ($params['title'] !== null && $params['title'] !== $this->title) {
            $update['title'] = $params['title'];
        }
        if ($params['status'] !== null && $params['status'] !== $this->status) {
            $update['status'] = $params['status'];
            if ($params['status'] === API::SWIPE_STATUS_READY) {
                $update['published'] = false;
            }
        }
        if ($params['published'] !== null && $params['published'] !== $this->published) {
            if ($params['published']) {
                $update['status'] = API::SWIPE_STATUS_ACTIVE;
            } else {
                $update['status'] = API::SWIPE_STATUS_READY;
            }
            if ($params['published'] && $this->status !== API::SWIPE_STATUS_READY) {
                $message = __('Swipe is not ready to be published');
                return false;
            }
            $update['published'] = $params['published'];
        }

        if (count($update) === 0) {
            return null;
        }

        return $this->where('id', '=', $params['swipe_id'])
            ->where('deleted_at', '=', null)
            ->update($update);
    }

    public function deleteSwipe(string $swipe_id): bool
    {
        return $this->where('id', '=', $swipe_id)
            ->where('deleted_at', '=', null)
            ->update([
                'deleted_at' => now()->format('Y-m-d H:i:s.u'),
            ]);
    }

    public function countAttachedStories(): int
    {
        return SwipeGroups::where('swipe_id', '=', $this->id)->count();
    }

    public function attachStory(array $params): bool
    {
        if (SwipeGroups::where('swipe_id', '=', $params['swipe_id'])
            ->where('story_id', '=', $params['story_id'])
            ->exists()
        ) {
            return false;
        }
        try {
            SwipeGroups::create([
                'id' => Str::uuid()->toString(),
                'swipe_id' => $params['swipe_id'],
                'story_id' => $params['story_id'],
            ]);

            if ($this->status === API::SWIPE_STATUS_DRAFT) {
                $this->status = API::SWIPE_STATUS_READY;
                $this->save();
            }
        } catch (\Exception $e) {
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }

        return true;
    }

    public function detachStory(array $params): bool
    {
        if (SwipeGroups::where('swipe_id', '=', $params['swipe_id'])
            ->where('story_id', '=', $params['story_id'])
            ->delete()
        ) {
            if ($this->status === API::SWIPE_STATUS_READY) {
                $this->status = API::SWIPE_STATUS_DRAFT;
                $this->save();
            }
            return true;
        }

        return false;
    }

    public function getAttachedStories(string $order_by = 'created_at', string $order = 'asc', int $offset = 0, int $limit = 30): Collection
    {
        return SwipeGroups::where('swipe_id', '=', $this->id)
            ->orderBy($order_by, $order)
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    public function getAttachedStoriesDetailed(string $order_by = 'created_at', string $order = 'asc', int $offset = 0, int $limit = 30): object
    {
        return SwipeGroups::where('swipe_id', '=', $this->id)
            ->orderBy($order_by, $order)
            ->offset($offset)
            ->limit($limit)
            ->get()->map(function ($item) {
                return API::story($item->getStory());
            });
    }
}
