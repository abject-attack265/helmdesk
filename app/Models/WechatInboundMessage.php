<?php

namespace App\Models;

use App\Enums\WechatInboundMessageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 微信公众号入站消息处理台账。
 *
 * @property string $id
 * @property string $channel_id
 * @property string $provider_message_id
 * @property string $message_type
 * @property array<string, mixed> $payload
 * @property WechatInboundMessageStatus $status
 * @property int $attempts
 * @property Carbon|null $available_at
 * @property Carbon|null $locked_at
 * @property string|null $lock_token
 * @property Carbon|null $processed_at
 * @property Carbon|null $failed_at
 * @property string|null $last_error
 * @property-read Channel $channel
 */
class WechatInboundMessage extends Model
{
    use HasUlids;

    protected $guarded = [];

    /** 关联接收该入站消息的微信公众号渠道。 */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** 定义入站台账字段类型。 */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => WechatInboundMessageStatus::class,
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'locked_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /** 原子领取一条到期或由当前任务预占的入站消息。 */
    public function claimForProcessing(?string $reservationToken = null): ?string
    {
        $token = (string) Str::uuid();
        $now = now();
        $claimed = self::query()
            ->whereKey($this->getKey())
            ->where('status', WechatInboundMessageStatus::Pending)
            ->where(function (Builder $query) use ($now, $reservationToken): void {
                $query->where(fn (Builder $due) => $due->whereNull('available_at')->orWhere('available_at', '<=', $now));
                if ($reservationToken !== null) {
                    $query->orWhere('lock_token', $reservationToken);
                }
            })
            ->update([
                'status' => WechatInboundMessageStatus::Processing,
                'attempts' => DB::raw('attempts + 1'),
                'available_at' => null,
                'locked_at' => $now,
                'lock_token' => $token,
                'updated_at' => $now,
            ]);

        $this->refresh();

        return $claimed === 1 ? $token : null;
    }

    /** 释放当前处理租约并安排重试。 */
    public function releaseForRetry(string $token, string $reason, int $delaySeconds): bool
    {
        $released = self::query()
            ->whereKey($this->getKey())
            ->where('status', WechatInboundMessageStatus::Processing)
            ->where('lock_token', $token)
            ->update([
                'status' => WechatInboundMessageStatus::Pending,
                'available_at' => now()->addSeconds($delaySeconds),
                'locked_at' => null,
                'lock_token' => null,
                'last_error' => mb_substr($reason, 0, 2000),
                'updated_at' => now(),
            ]);

        $this->refresh();

        return $released === 1;
    }

    /** 完成当前租约对应的入站处理。 */
    public function markProcessed(string $token): bool
    {
        $processed = self::query()
            ->whereKey($this->getKey())
            ->where('status', WechatInboundMessageStatus::Processing)
            ->where('lock_token', $token)
            ->update([
                'status' => WechatInboundMessageStatus::Processed,
                'locked_at' => null,
                'lock_token' => null,
                'processed_at' => now(),
                'failed_at' => null,
                'last_error' => null,
                'updated_at' => now(),
            ]);

        $this->refresh();

        return $processed === 1;
    }

    /** 将尚未完成的入站处理标记为失败。 */
    public function failIfUnprocessed(string $reason): bool
    {
        $failed = self::query()
            ->whereKey($this->getKey())
            ->whereIn('status', [WechatInboundMessageStatus::Pending, WechatInboundMessageStatus::Processing])
            ->update([
                'status' => WechatInboundMessageStatus::Failed,
                'available_at' => null,
                'locked_at' => null,
                'lock_token' => null,
                'failed_at' => now(),
                'last_error' => mb_substr($reason, 0, 2000),
                'updated_at' => now(),
            ]);

        $this->refresh();

        return $failed === 1;
    }
}
