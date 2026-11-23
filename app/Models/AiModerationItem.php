<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModerationItem extends Model
{
    protected $table = 'ai_moderation_items';

    public $timestamps = false;

    protected $fillable = [
        'item_type',
        'item_id',
        'post_id',
        'user_id',
        'violation_type',
        'severity',
        'ai_reason',
        'suggested_action',
        'status',
        'admin_note',
        'final_decision',
        'final_decision_at',
        'reviewed_by',
        'reviewed_at',
        'created_at',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
