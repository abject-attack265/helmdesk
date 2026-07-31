<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $email
 * @property string|null $nickname 接受后成员的对外昵称
 * @property array<int, string> $permissions
 * @property string $token 邀请令牌的 sha256 哈希；明文只进邮件链接
 * @property string $invited_by 发起邀请的用户
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property mixed $use_factory
 * @property int|null $inviters_count
 * @property-read User $inviter
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Invitation pending()
 * @method static \Database\Factories\InvitationFactory<self> factory($count = null, $state = [])
 */
class Invitation extends Model
{
    use HasFactory, HasUlids;

    public const EXPIRES_AFTER_DAYS = 7;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public static function hashToken(#[\SensitiveParameter] string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public static function findByPlainToken(#[\SensitiveParameter] string $plainToken): ?self
    {
        return static::query()->where('token', static::hashToken($plainToken))->first();
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAcceptable(): bool
    {
        return $this->accepted_at === null && ! $this->isExpired();
    }

    public function status(): InvitationStatus
    {
        if ($this->accepted_at !== null) {
            return InvitationStatus::Accepted;
        }

        return $this->isExpired()
            ? InvitationStatus::Expired
            : InvitationStatus::Pending;
    }
}
