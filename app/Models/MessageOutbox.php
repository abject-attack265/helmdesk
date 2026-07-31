<?php

namespace App\Models;

use App\Enums\ChannelType;
use App\Enums\MessageOutboxStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $conversation_message_id
 * @property string $channel_id
 * @property ChannelType $channel_type
 * @property MessageOutboxStatus $status
 * @property int $attempts
 * @property Carbon|null $available_at
 * @property Carbon|null $locked_at
 * @property string|null $lock_token
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 * @property string|null $provider_message_id
 * @property string|null $last_error
 * @property array|null $payload
 */
class MessageOutbox extends Model
{
    /**
     * 记录外部渠道消息的投递状态与发送租约。
     */
    use HasUlids;

    protected $guarded = [];

    /** 定义 Outbox 字段类型。 */
    protected function casts(): array
    {
        return [
            'channel_type' => ChannelType::class,
            'status' => MessageOutboxStatus::class,
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'locked_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /** 领取待发送 Outbox，并返回本次发送使用的租约令牌。 */
    public function claimForSending(): ?string
    {
        $token = (string) Str::uuid();
        $claimedAt = now();
        $claimed = self::query()
            ->whereKey($this->getKey())
            ->where('status', MessageOutboxStatus::Pending)
            ->update([
                'status' => MessageOutboxStatus::Sending,
                'attempts' => DB::raw('attempts + 1'),
                'available_at' => null,
                'locked_at' => $claimedAt,
                'lock_token' => $token,
                'updated_at' => $claimedAt,
            ]);

        $this->refresh();

        return $claimed === 1 ? $token : null;
    }

    /** 释放发送租约并安排下一次重试。 */
    public function releaseForRetry(string $token, string $reason, int $delaySeconds = 60): bool
    {
        $released = self::query()
            ->whereKey($this->getKey())
            ->where('status', MessageOutboxStatus::Sending)
            ->where('lock_token', $token)
            ->update([
                'status' => MessageOutboxStatus::Pending,
                'available_at' => now()->addSeconds($delaySeconds),
                'locked_at' => null,
                'lock_token' => null,
                'last_error' => mb_substr($reason, 0, 2000),
                'updated_at' => now(),
            ]);

        $this->refresh();

        return $released === 1;
    }

    /**
     * 完成当前租约对应的投递。
     *
     * @param  array<string, mixed>  $metadata
     */
    public function markSentIfClaimed(string $token, array $metadata = [], ?string $providerMessageId = null): bool
    {
        $payload = $this->payload ?? [];
        $sent = self::query()
            ->whereKey($this->getKey())
            ->where('status', MessageOutboxStatus::Sending)
            ->where('lock_token', $token)
            ->update([
                'status' => MessageOutboxStatus::Sent,
                'available_at' => null,
                'locked_at' => null,
                'lock_token' => null,
                'sent_at' => now(),
                'failed_at' => null,
                'last_error' => null,
                'provider_message_id' => $providerMessageId ?? $this->provider_message_id,
                'payload' => json_encode(array_merge($payload, $metadata), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

        $this->refresh();

        return $sent === 1;
    }

    /** 将当前租约对应的投递标记为失败。 */
    public function markFailedIfClaimed(string $token, string $reason): bool
    {
        $failed = self::query()
            ->whereKey($this->getKey())
            ->where('status', MessageOutboxStatus::Sending)
            ->where('lock_token', $token)
            ->update([
                'status' => MessageOutboxStatus::Failed,
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

    /**
     * 保存当前租约的分片发送进度并续租。
     *
     * @param  array<string, mixed>  $payload
     */
    public function updatePayloadIfClaimed(string $token, array $payload): bool
    {
        $updated = self::query()
            ->whereKey($this->getKey())
            ->where('status', MessageOutboxStatus::Sending)
            ->where('lock_token', $token)
            ->update([
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'locked_at' => now(),
                'updated_at' => now(),
            ]);

        $this->refresh();

        return $updated === 1;
    }

    /** 取消尚未领取的投递。 */
    public function cancelPending(string $reason): bool
    {
        $cancelled = self::query()
            ->whereKey($this->getKey())
            ->where('status', MessageOutboxStatus::Pending)
            ->update([
                'status' => MessageOutboxStatus::Failed,
                'available_at' => null,
                'failed_at' => now(),
                'last_error' => mb_substr($reason, 0, 2000),
                'updated_at' => now(),
            ]);

        $this->refresh();

        return $cancelled === 1;
    }

    /** 将尚未完成的投递标记为失败。 */
    public function failIfUnsent(string $reason): bool
    {
        $failed = self::query()
            ->whereKey($this->getKey())
            ->whereIn('status', [MessageOutboxStatus::Pending, MessageOutboxStatus::Sending])
            ->update([
                'status' => MessageOutboxStatus::Failed,
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

    /** 将最终失败的投递恢复为可立即重试。 */
    public function retryFailed(): bool
    {
        $retried = self::query()
            ->whereKey($this->getKey())
            ->where('status', MessageOutboxStatus::Failed)
            ->update([
                'status' => MessageOutboxStatus::Pending,
                'attempts' => 0,
                'available_at' => now(),
                'locked_at' => null,
                'lock_token' => null,
                'failed_at' => null,
                'last_error' => null,
                'updated_at' => now(),
            ]);

        $this->refresh();

        return $retried === 1;
    }
}
