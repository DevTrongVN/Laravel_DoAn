<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use InvalidArgumentException;

class User extends Authenticatable
{
    use Notifiable;

    public const AVATAR_PRESETS = [
        'user-admin.svg',
        'user-demo.svg',
        'user-hoangnam.svg',
        'user-thuha.svg',
        'user-quocbao.svg',
        'user-maianh.svg',
    ];

    protected $table = 'users';

    protected $fillable = [
        'username',
        'email',
        'password',
        'full_name',
        'avatar',
        'bio',
        'role',
        'is_verified',
        'reputation_score',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'reputation_score' => 'integer',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'user_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class, 'user_id');
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class, 'user_id');
    }

    public function bookmarkedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'bookmarks', 'user_id', 'post_id')
            ->withPivot('created_at');
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'following_id', 'follower_id')
            ->withPivot(['visit_count', 'created_at']);
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'follower_id', 'following_id')
            ->withPivot(['visit_count', 'created_at']);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'user_id');
    }

    public function verifiedBadgeLogs(): HasMany
    {
        return $this->hasMany(VerifiedBadgeLog::class, 'user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public static function randomAvatar(): string
    {
        return '/assets/images/avatars/' . self::AVATAR_PRESETS[random_int(0, count(self::AVATAR_PRESETS) - 1)];
    }

    public static function normalizeAvatar(string $avatar): string
    {
        $avatar = '/' . ltrim(trim($avatar), '/');
        $allowed = array_map(static fn(string $file): string => '/assets/images/avatars/' . $file, self::AVATAR_PRESETS);
        if (in_array($avatar, $allowed, true)) {
            return $avatar;
        }
        if (preg_match('~^/uploads/avatars/[a-zA-Z0-9_\-\.]+\.(jpe?g|png|webp|gif)$~i', $avatar)) {
            return $avatar;
        }
        throw new InvalidArgumentException('Avatar không hợp lệ. Hãy chọn một avatar hoạt hình có sẵn hoặc tải ảnh lên.');
    }

    public function toPublicArray(): array
    {
        return [
            'id' => (int) $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'full_name' => $this->full_name,
            'avatar' => $this->avatar,
            'bio' => $this->bio,
            'role' => $this->role,
            'is_verified' => (int) $this->is_verified,
            'reputation_score' => (int) $this->reputation_score,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
