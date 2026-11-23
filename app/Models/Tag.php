<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $table = 'hashtags';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'created_at',
    ];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_hashtags', 'hashtag_id', 'post_id');
    }
}
