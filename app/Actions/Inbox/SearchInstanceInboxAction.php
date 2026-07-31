<?php

namespace App\Actions\Inbox;

use App\Data\CurrentUserContextData;
use App\Data\Inbox\InboxContactSearchResultData;
use App\Data\Inbox\InboxInstanceMessageSearchResultData;
use App\Data\Inbox\InboxSearchResultsData;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationThread;
use App\Models\User;
use App\Services\Search\ConversationMessageSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 在当前应用内搜索联系人与消息内容，支撑收件箱左侧的分组搜索面板。
 */
class SearchInstanceInboxAction
{
    use AsAction;

    private const int MAX_MESSAGE_RESULTS = 50;

    private const int MAX_CONTACT_RESULTS = 20;

    /**
     * 联系人索引不包含线程信息，过量召回后按线程过滤。
     */
    private const int CONTACT_RECALL_SIZE = 100;

    /**
     * 注入消息内容搜索门面。
     */
    public function __construct(
        private readonly ConversationMessageSearch $messageSearch,
    ) {}

    /**
     * 搜索应用内匹配的联系人与消息。
     * 传入 contactId 表示「此聊天」范围：消息仅在该联系人的会话内命中，且不返回联系人分组。
     */
    public function handle(User $viewer, string $search, ?string $contactId = null): InboxSearchResultsData
    {
        $scopedConversationIds = null;
        if ($contactId !== null) {
            $scopedConversationIds = Conversation::query()
                ->where('contact_id', $contactId)
                ->whereNotNull('channel_id')
                ->pluck('id')
                ->all();

            if ($scopedConversationIds === []) {
                return new InboxSearchResultsData(contacts: [], messages: []);
            }
        }

        return new InboxSearchResultsData(
            contacts: $contactId === null ? $this->searchContacts($search) : [],
            messages: $this->searchMessages($viewer, $search, $scopedConversationIds),
        );
    }

    /**
     * 按相关度返回具有收件箱线程的联系人。
     *
     * @return list<InboxContactSearchResultData>
     */
    private function searchContacts(string $search): array
    {
        $contactIds = Contact::search($search)
            ->take(self::CONTACT_RECALL_SIZE)
            ->keys()
            ->all();

        if ($contactIds === []) {
            return [];
        }

        $relevanceOrder = array_flip($contactIds);
        $rankedThreads = ConversationThread::query()
            ->whereIn('contact_id', $contactIds)
            ->select('id', 'contact_id')
            ->selectRaw(
                'ROW_NUMBER() OVER ('.
                'PARTITION BY contact_id '.
                'ORDER BY last_activity_at DESC, id DESC'.
                ') AS contact_rank'
            );
        $threads = ConversationThread::query()
            ->whereIn('id', DB::query()
                ->fromSub($rankedThreads, 'ranked_threads')
                ->select('id')
                ->where('contact_rank', 1))
            ->with('currentConversation')
            ->get()
            ->keyBy('contact_id');

        return Contact::query()
            ->whereIn('id', $contactIds)
            ->get()
            ->sortBy(fn (Contact $contact) => $relevanceOrder[$contact->id])
            ->filter(fn (Contact $contact) => $threads->has((string) $contact->id))
            ->take(self::MAX_CONTACT_RESULTS)
            ->map(function (Contact $contact) use ($threads): InboxContactSearchResultData {
                /** @var ConversationThread $thread */
                $thread = $threads->get((string) $contact->id);

                return InboxContactSearchResultData::fromModel($contact, $thread);
            })
            ->values()
            ->all();
    }

    /**
     * 在应用全部会话（或限定会话集合）中搜索消息内容，按时间倒序返回匹配结果。
     *
     * @param  list<string>|null  $scopedConversationIds  限定会话范围；null 表示全应用
     * @return list<InboxInstanceMessageSearchResultData>
     */
    private function searchMessages(User $viewer, string $search, ?array $scopedConversationIds): array
    {
        $hits = $this->messageSearch->searchVisibleMessages(
            viewer: $viewer,
            search: $search,
            conversationIds: $scopedConversationIds,
            limit: self::MAX_MESSAGE_RESULTS,
        );
        $threads = $this->threadsForMessages($hits);

        return array_map(
            fn (array $hit) => $this->buildMessageResult(
                $hit['message'],
                $hit['matched_content'],
                $threads,
            ),
            $hits,
        );
    }

    /**
     * 按消息会话身份加载线程并以身份键索引。
     *
     * @param  list<array{message: ConversationMessage, matched_content: string}>  $hits
     * @return Collection<string, ConversationThread>
     */
    private function threadsForMessages(array $hits): Collection
    {
        $channelsByContact = [];
        foreach ($hits as $hit) {
            $conversation = $hit['message']->conversation;
            if (
                ! $conversation instanceof Conversation
                || $conversation->contact_id === null
                || $conversation->channel_id === null
            ) {
                Log::warning('收件箱搜索命中的消息缺少线程身份', [
                    'message_id' => (string) $hit['message']->id,
                    'conversation_id' => $conversation instanceof Conversation
                        ? (string) $conversation->id
                        : null,
                    'contact_id' => $conversation?->contact_id,
                    'channel_id' => $conversation?->channel_id,
                ]);

                throw new LogicException("消息 {$hit['message']->id} 所属会话缺少线程身份。");
            }

            $channelsByContact[(string) $conversation->contact_id][(string) $conversation->channel_id] = true;
        }

        if ($channelsByContact === []) {
            return collect();
        }

        return ConversationThread::query()
            ->where(function (Builder $query) use ($channelsByContact): void {
                foreach ($channelsByContact as $contactId => $channelIds) {
                    $query->orWhere(fn (Builder $identity) => $identity
                        ->where('contact_id', $contactId)
                        ->whereIn('channel_id', array_keys($channelIds)));
                }
            })
            ->get()
            ->keyBy(fn (ConversationThread $thread) => $this->threadIdentityKey(
                (string) $thread->contact_id,
                (string) $thread->channel_id,
            ));
    }

    /**
     * 将命中的消息组装为带线程和联系人信息的搜索结果。
     *
     * @param  Collection<string, ConversationThread>  $threads
     */
    private function buildMessageResult(
        ConversationMessage $message,
        string $matchedContent,
        Collection $threads,
    ): InboxInstanceMessageSearchResultData {
        $senderName = filled($message->sender_name)
            ? (string) $message->sender_name
            : $message->senderUser?->name;
        $conversation = $message->conversation;

        if (! $conversation instanceof Conversation || $conversation->contact === null) {
            Log::warning('收件箱搜索命中的消息缺少联系人', [
                'message_id' => (string) $message->id,
                'conversation_id' => $conversation instanceof Conversation
                    ? (string) $conversation->id
                    : null,
                'contact_id' => $conversation?->contact_id,
            ]);

            throw new LogicException("消息 {$message->id} 所属会话缺少联系人。");
        }

        $contact = $conversation->contact;
        $thread = $threads->get($this->threadIdentityKey(
            (string) $conversation->contact_id,
            (string) $conversation->channel_id,
        ));

        if (! $thread instanceof ConversationThread) {
            Log::warning('收件箱搜索命中的会话缺少线程', [
                'message_id' => (string) $message->id,
                'conversation_id' => (string) $conversation->id,
                'contact_id' => (string) $conversation->contact_id,
                'channel_id' => (string) $conversation->channel_id,
            ]);

            throw new LogicException("消息 {$message->id} 所属会话缺少收件箱线程。");
        }

        return new InboxInstanceMessageSearchResultData(
            id: (string) $message->id,
            thread_id: (string) $thread->id,
            contact_id: (string) $contact->id,
            contact_name: $contact->name,
            contact_avatar_url: $contact->avatar_url,
            role: $message->role->value,
            role_label: $message->role->label(),
            kind: $message->kind->value,
            sender_name: $senderName,
            content: $message->content,
            matched_content: $matchedContent,
            occurred_at: $message->created_at->toIso8601String(),
        );
    }

    /**
     * 生成联系人渠道线程身份键。
     */
    private function threadIdentityKey(string $contactId, string $channelId): string
    {
        return $contactId.':'.$channelId;
    }

    /**
     * 处理收件箱搜索请求。
     */
    public function asController(Request $request): InboxSearchResultsData
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $viewer = User::query()->findOrFail($ctx->user_id);

        $validated = $request->validate([
            'search' => ['required', 'string', 'min:1', 'max:200'],
            'contact_id' => ['nullable', 'string', 'ulid'],
        ]);

        return $this->handle($viewer, $validated['search'], $validated['contact_id'] ?? null);
    }
}
