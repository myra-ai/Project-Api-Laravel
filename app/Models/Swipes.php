<?php

namespace App\Models;

use App\Casts\Timestamp;
use App\Http\Controllers\API;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

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

    public function createSwipe(array $params, ?string &$id = null): ?Swipes
    {
        $id = Str::uuid()->toString();
        return $this->create([
            'id' => $id,
            'company_id' => $params['company_id'],
            'title' => $params['title'],
            'status' => $params['status'],
            'published' => $params['published'],
        ]);
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
                'status' => API::SWIPE_STATUS_DELETED,
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

    public function getAttachedStories(int $offset = 0, int $limit = 5, ?string $search = null, string $order_by = 'created_at', string $order = 'asc'): Collection
    {
        return $this->belongsToMany(Stories::class, 'swipe_groups', 'swipe_id', 'story_id')
            ->when($search, function ($qry, $search) {
                return $qry->where('title', 'LIKE', "%{$search}%");
            })
            ->orderBy($order_by, $order)
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    public function getAttachedStoriesDetailed(int $offset = 0, int $limit = 5, ?string $search = null, string $order_by = 'created_at', string $order = 'asc'): object
    {
        return $this->belongsToMany(Stories::class, 'swipe_groups', 'swipe_id', 'story_id')
            ->when($search, function ($qry, $search) {
                return $qry->where('title', 'LIKE', "%{$search}%");
            })
            ->orderBy($order_by, $order)
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return API::story($item);
            });
    }

    public function isAttachStory(string $story_id): bool
    {
        return SwipeGroups::where('swipe_id', '=', $this->id)
            ->where('story_id', '=', $story_id)
            ->exists();
    }

    public function getStories(array $params = [], &$data_info)
    {
        $params = [
            'offset' => $params['offset'] ?? 0,
            'limit' => $params['limit'] ?? 5,
            'filter' => $params['filter'] ?? 'all', // all,attached,unattached,status,published
            'filter_status' => $params['filter_status'] ?? null,
            'filter_publish' => $params['filter_publish'] ?? null,
            'filter_from' => $params['filter_from'] ?? null,
            'filter_to' => $params['filter_to'] ?? null,
            'order_by' => $params['order_by'] ?? 'created_at', // created_at,updated_at
            'order' => $params['order'] ?? 'asc',
            'is_attached' => $params['is_attached'] ?? false,
            'search' => $params['search'] ?? null,
        ];

        $qry = Stories::where('company_id', '=', $this->company_id)->where('deleted_at', '=', null);

        if ($params['filter'] === 'all') {
            $qry = $qry->where('id', '!=', null);
        } elseif ($params['filter'] === 'attached') {
            $qry = $qry->whereHas('swipeGroups', function ($qry) {
                $qry->where('swipe_id', '=', $this->id);
            });
        } elseif ($params['filter'] === 'unattached') {
            $qry = $qry->whereDoesntHave('swipeGroups', function ($qry) {
                $qry->where('swipe_id', '=', $this->id);
            });
        } elseif ($params['filter'] === 'status' && $params['filter_status'] !== null) {
            $qry = $qry->where('status', '=', $params['filter_status']);
        } elseif ($params['filter'] === 'publish' && $params['filter_publish'] !== null) {
            $qry = $qry->where('publish', '=', $params['filter_publish']);
        }

        if ($params['filter_from'] !== null) {
            $qry = $qry->where('created_at', '>=', $params['filter_from']);
        }

        if ($params['filter_to'] !== null) {
            $qry = $qry->where('created_at', '<=', $params['filter_to']);
        }

        if ($params['search'] !== null) {
            $qry = $qry->where('title', 'LIKE', "%{$params['search']}%");
        }

        $qry = $qry->orderBy($params['order_by'], $params['order'])
            ->offset($params['offset'])
            ->limit($params['limit']);

        $stories = $qry->get();

        $data_info = [
            'offset' => $params['offset'],
            'limit' => $params['limit'],
            'count' => count($stories),
            'total' => $qry->count(),
        ];

        return $stories->map(function ($item) use ($params) {
            $item = API::story($item, [
                'thumbnail_width' => 660,
                'thumbnail_height' => 660,
            ]);
            if ($params['is_attached']) {
                $item->attached = $this->isAttachStory($item->id);
            }
            return $item;
        });
    }
}
