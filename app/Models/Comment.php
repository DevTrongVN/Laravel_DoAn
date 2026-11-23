<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    protected $table = 'comments';

    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'content',
        'moderation_status',
        'edited_at',
        'created_at',
        'ai_severity',
        'ai_violation_type',
        'ai_reason',
        'ai_status',
    ];

    protected function casts(): array
    {
        return [
            'post_id' => 'integer',
            'user_id' => 'integer',
            'parent_id' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CommentReport::class, 'comment_id');
    }

    /**
     * Thu thập toàn bộ ID của bình luận cha và tất cả hậu duệ (con, cháu, chắt...).
     *
     * @param int $commentId
     * @return int[]
     */
    public static function collectDescendantIds(int $commentId): array
    {
        $allIds = [$commentId];
        $currentLevelIds = [$commentId];

        while (!empty($currentLevelIds)) {
            $childIds = \Illuminate\Support\Facades\DB::table('comments')
                ->whereIn('parent_id', $currentLevelIds)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->all();

            if (empty($childIds)) {
                break;
            }

            // Tránh vòng lặp vô hạn nếu có quan hệ vòng lặp trong dữ liệu cũ
            $newIds = array_diff($childIds, $allIds);
            if (empty($newIds)) {
                break;
            }

            $allIds = array_merge($allIds, $newIds);
            $currentLevelIds = $newIds;
        }

        // Bổ sung các bình luận có root_id trỏ tới commentId nếu có
        $rootDescendants = \Illuminate\Support\Facades\DB::table('comments')
            ->where('root_id', $commentId)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        if (!empty($rootDescendants)) {
            $allIds = array_merge($allIds, $rootDescendants);
        }

        return array_values(array_unique(array_map('intval', $allIds)));
    }
}
