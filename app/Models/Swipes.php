<?php

namespace App\Models;

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
        'status' => 'string',
        'published' => 'boolean',
        'deleted_at' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public function getSwipesByCompanyId(string $swipe_id, string $order_by = 'created_at', $order = 'asc', int $offset = 0, int $limit = 30)
    {
        return $this->where('company_id', '=', $swipe_id)
            ->where('deleted_at', '=', null)
            ->orderBy($order_by, $order)
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    public function getSwipeById(string $swipe_id, string $order_by = 'created_at', $order = 'asc', int $offset = 0)
    {
        return $this->where('id', '=', $swipe_id)
            ->where('deleted_at', '=', null)
            ->orderBy($order_by, $order)
            ->offset($offset)
            ->first();
    }

    public function createSwipe(array $params): bool
    {
        return $this->create([
            'id' => Str::uuid()->toString(),
            'company_id' => $params['company_id'],
            'title' => $params['title'],
            'status' => $params['status'],
            'published' => false,
        ]);
    }

    public function updateSwipe(array $params): bool
    {
        $update = [];
        if ($params['title'] !== null) {
            $update['title'] = $params['title'];
        }
        if ($params['status'] !== null) {
            $update['status'] = $params['status'];
        }
        if ($params['published'] !== null) {
            $update['published'] = $params['published'];
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

    public function attachStory(array $params): bool
    {
        try {
            SwipeGroups::create([
                'id' => Str::uuid()->toString(),
                'swipe_id' => $params['swipe_id'],
                'story_id' => $params['story_id'],
            ]);
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
        return SwipeGroups::where('swipe_id', '=', $params['swipe_id'])
            ->where('story_id', '=', $params['story_id'])
            ->delete();
    }
}
