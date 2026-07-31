<?php

namespace App\Actions\Conversation;

use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationThread;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

/**
 * 按联系人渠道身份重算收件箱线程的当前会话投影。
 */
class SyncConversationThreadAction
{
    use AsObject;

    /**
     * 按会话最新状态重算其联系人渠道线程。
     */
    public function handle(Conversation $conversation): ConversationThread
    {
        return $this->synchronize($conversation, createIfMissing: false);
    }

    /**
     * 为刚创建的会话建立或更新联系人渠道线程。
     */
    public function establish(Conversation $conversation): ConversationThread
    {
        return $this->synchronize($conversation, createIfMissing: true);
    }

    /**
     * 在写事务中读取会话和线程并写入最新投影。
     */
    private function synchronize(Conversation $conversation, bool $createIfMissing): ConversationThread
    {
        return DB::transaction(function () use ($conversation, $createIfMissing): ConversationThread {
            $currentConversation = Conversation::query()
                ->whereKey($conversation->id)
                ->firstOrFail();
            $identity = $this->identity($currentConversation);
            $thread = $this->findThread($identity);

            if ($currentConversation->status === ConversationStatus::Open) {
                return $this->projectConversation(
                    $identity,
                    $thread,
                    $currentConversation,
                    $createIfMissing,
                );
            }

            return $this->rebuildIdentity($identity, $thread, $createIfMissing)
                ?? $this->threadInvariantFailure(
                    '会话线程缺少代表会话',
                    $identity,
                    ['conversation_id' => (string) $conversation->id],
                );
        });
    }

    /**
     * 会话删除后重算原联系人渠道线程。
     */
    public function forget(string $contactId, string $channelId): void
    {
        DB::transaction(function () use ($contactId, $channelId): void {
            $identity = [
                'contact_id' => $contactId,
                'channel_id' => $channelId,
            ];

            $this->rebuildIdentity(
                $identity,
                $this->findThread($identity),
                createIfMissing: false,
            );
        });
    }

    /**
     * 按完整身份重算代表会话，并在线程没有会话时删除线程。
     *
     * @param  array{contact_id: string, channel_id: string}  $identity
     */
    private function rebuildIdentity(
        array $identity,
        ?ConversationThread $thread,
        bool $createIfMissing,
    ): ?ConversationThread {
        $representative = $this->representativeConversation($identity);

        if ($representative === null) {
            $thread?->delete();

            return null;
        }

        return $this->projectConversation(
            $identity,
            $thread,
            $representative,
            $createIfMissing,
        );
    }

    /**
     * 将代表会话写入线程投影，并处理线程的并发首次创建。
     *
     * @param  array{contact_id: string, channel_id: string}  $identity
     */
    private function projectConversation(
        array $identity,
        ?ConversationThread $thread,
        Conversation $representative,
        bool $createIfMissing,
    ): ConversationThread {
        $conversationId = (string) $representative->id;

        if ($thread === null) {
            if (! $createIfMissing) {
                $this->threadInvariantFailure(
                    '完整会话缺少收件箱线程',
                    $identity,
                    ['conversation_id' => $conversationId],
                );
            }

            $now = now();
            ConversationThread::query()->insertOrIgnore([[
                'id' => (string) Str::ulid(),
                ...$identity,
                ...ConversationThread::projectionFromConversation($representative),
                'is_important' => $this->contactIsImportant($identity),
                'created_at' => $now,
                'updated_at' => $now,
            ]]);

            $thread = $this->findThread($identity);
            if ($thread === null) {
                $this->threadInvariantFailure(
                    '会话线程创建后无法按身份解析',
                    $identity,
                    ['conversation_id' => $conversationId],
                );
            }

            $representative = $this->representativeConversation($identity)
                ?? $this->threadInvariantFailure(
                    '会话线程创建后缺少代表会话',
                    $identity,
                    ['conversation_id' => $conversationId],
                );
        }

        $thread->fill(ConversationThread::projectionFromConversation($representative));
        if ($thread->isDirty()) {
            $thread->save();
        }

        return $thread;
    }

    /**
     * 记录线程投影不变量错误并终止同步。
     *
     * @param  array{contact_id: string, channel_id: string}  $identity
     * @param  array<string, mixed>  $context
     */
    private function threadInvariantFailure(
        string $message,
        array $identity,
        array $context = [],
    ): never {
        Log::warning($message, [...$identity, ...$context]);

        throw new RuntimeException($message.'。');
    }

    /**
     * 返回联系人渠道身份下优先级最高的代表会话。
     *
     * @param  array{contact_id: string, channel_id: string}  $identity
     */
    private function representativeConversation(array $identity): ?Conversation
    {
        return ConversationThread::representativeConversation(
            $this->conversationQueryForIdentity($identity),
        );
    }

    /**
     * 提取会话的完整联系人渠道身份。
     *
     * @return array{contact_id: string, channel_id: string}
     */
    private function identity(Conversation $conversation): array
    {
        if ($conversation->contact_id === null || $conversation->channel_id === null) {
            Log::warning('会话缺少线程身份', [
                'conversation_id' => (string) $conversation->id,
                'contact_id' => $conversation->contact_id,
                'channel_id' => $conversation->channel_id,
            ]);

            throw new InvalidArgumentException("会话 {$conversation->id} 缺少联系人或渠道，无法建立线程。");
        }

        return [
            'contact_id' => (string) $conversation->contact_id,
            'channel_id' => (string) $conversation->channel_id,
        ];
    }

    /**
     * 读取线程联系人的重点标记。
     *
     * @param  array{contact_id: string, channel_id: string}  $identity
     */
    private function contactIsImportant(array $identity): bool
    {
        return Contact::withTrashed()
            ->findOrFail($identity['contact_id'])
            ->is_important;
    }

    /**
     * 查找联系人渠道身份对应的线程。
     *
     * @param  array{contact_id: string, channel_id: string}  $identity
     */
    private function findThread(array $identity): ?ConversationThread
    {
        return ConversationThread::queryForIdentity(
            $identity['contact_id'],
            $identity['channel_id'],
        )
            ->first();
    }

    /**
     * 构造联系人渠道身份下的会话查询。
     *
     * @param  array{contact_id: string, channel_id: string}  $identity
     * @return Builder<Conversation>
     */
    private function conversationQueryForIdentity(array $identity): Builder
    {
        return Conversation::query()
            ->where('contact_id', $identity['contact_id'])
            ->where('channel_id', $identity['channel_id']);
    }
}
