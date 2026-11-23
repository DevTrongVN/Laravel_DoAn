<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $table = 'posts';

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'summary',
        'content',
        'thumbnail',
        'post_type',
        'moderation_status',
        'ai_severity',
        'ai_violation_type',
        'ai_reason',
        'ai_news_suggested',
        'ai_news_reason',
        'ai_status',
        'ai_analyzed_at',
        'ai_suggested_action',
        'final_decision',
        'moderated_by',
        'moderated_at',
        'shared_post_id',
        'share_comment',
        'views_count',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'shared_post_id' => 'integer',
            'views_count' => 'integer',
            'ai_news_suggested' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class, 'post_id');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class, 'post_id');
    }

    public function hashtags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_hashtags', 'post_id', 'hashtag_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->hashtags();
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class, 'post_id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(PostView::class, 'post_id');
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(PostAppeal::class, 'post_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PostReport::class, 'post_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'post_id');
    }

    public function sharedPost(): BelongsTo
    {
        return $this->belongsTo(self::class, 'shared_post_id');
    }

    public function reposts(): HasMany
    {
        return $this->hasMany(self::class, 'shared_post_id');
    }
}
