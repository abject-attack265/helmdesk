<?php

namespace App\Services\Reception;

use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Data\Channel\Web\ChannelWebVisitorInterfaceSettingsData;
use App\Data\Conversation\QuotedMessageData;
use App\Data\Reception\ReceptionActivityStateData;
use App\Data\Reception\ReceptionAttachmentData;
use App\Data\Reception\ReceptionMessageData;
use App\Data\Reception\ReceptionRatingData;
use App\Data\Reception\ReceptionStateData;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Enums\WebChannelVisitorIdentityMode;
use App\Models\Attachment;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationRating;
use App\Models\ReceptionPlanVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 组装访客接待窗口的当前状态。
 *
 * 统一服务身份读取渠道访客界面配置，实际接待身份读取会话锁定的接待方案版本。
 */
class ReceptionStateBuilder
{
    private const int MAX_MESSAGES = 500;

    /**
     * 组装访客端接待状态数据。
     */
    public static function build(Channel $channel, Conversation $conversation, string $sessionToken): ReceptionStateData
    {
        $visitorInterface = self::webVisitorInterface($channel);
        $visitorIdentityMode = $visitorInterface?->visitor_identity_mode
            ?? WebChannelVisitorIdentityMode::ActualReceptionist;
        [$assistantName, $assistantAvatarUrl] = self::channelMessageIdentity($channel, $conversation);
        $conversationIds = self::historyConversationIds($channel, $conversation);

        $messages = ConversationMessage::query()

            ->whereIn('conversation_id', $conversationIds)
            ->with(['senderUser', 'attachments.storageProfile', 'quotedMessage.attachments.storageProfile'])
            ->whereIn('kind', [MessageKind::Text, MessageKind::Image, MessageKind::File])
            ->whereIn('role', [MessageRole::Visitor, MessageRole::Ai, MessageRole::Teammate])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_MESSAGES)
            ->get()
            ->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
        $appNicknames = self::appNicknames($conversation, $messages);

        $entries = $messages
            ->map(function (ConversationMessage $message) use ($visitorIdentityMode, $assistantName, $assistantAvatarUrl, $appNicknames): ReceptionMessageData {
                $quotedMessage = self::quotedMessageData($message);
                [$senderName, $senderAvatarUrl] = self::senderMessageIdentity(
                    $visitorIdentityMode,
                    $message,
                    $assistantName,
                    $assistantAvatarUrl,
                    $appNicknames,
                );

                return match ($message->role) {
                    MessageRole::Ai => ReceptionMessageData::fromModel(
                        $message,
                        senderName: $senderName,
                        senderAvatarUrl: $senderAvatarUrl,
                        quotedMessage: $quotedMessage,
                    ),
                    MessageRole::Teammate => ReceptionMessageData::fromModel(
                        $message,
                        senderName: $senderName,
                        senderAvatarUrl: $senderAvatarUrl,
                        quotedMessage: $quotedMessage,
                    ),
                    default => ReceptionMessageData::fromModel($message, quotedMessage: $quotedMessage),
                };
            })
            ->values()
            ->all();

        $rating = ConversationRating::query()
            ->where('conversation_id', $conversation->id)
            ->first();
        $canInviteRating = $conversation->status === ConversationStatus::Closed
            && $conversation->inbox_status !== ConversationInboxStatus::TeammatePending;

        return new ReceptionStateData(
            session_token: $sessionToken,
            conversation_id: (string) $conversation->id,
            status: $conversation->status->value,
            assistant_name: $assistantName,
            assistant_avatar_url: $assistantAvatarUrl,
            messages: $entries,
            agent_activity: app(ReceptionActivityRegistry::class)->current((string) $conversation->id),
            can_rate: $canInviteRating && $rating === null,
            rating: $rating !== null ? ReceptionRatingData::fromModel($rating) : null,
        );
    }

    /**
     * 组装尚未发起会话的访客空状态。
     */
    public static function buildEmpty(Channel $channel, string $sessionToken): ReceptionStateData
    {
        [$assistantName, $assistantAvatarUrl] = self::channelMessageIdentity($channel);

        return new ReceptionStateData(
            session_token: $sessionToken,
            conversation_id: null,
            status: null,
            assistant_name: $assistantName,
            assistant_avatar_url: $assistantAvatarUrl,
            messages: [],
            agent_activity: ReceptionActivityStateData::inactive(0),
        );
    }

    /**
     * 生成访客端引用块需要的被引用消息快照。
     */
    private static function quotedMessageData(ConversationMessage $message): ?QuotedMessageData
    {
        $quoted = $message->quotedMessage;
        if (! $quoted instanceof ConversationMessage) {
            return null;
        }

        return new QuotedMessageData(
            id: (string) $quoted->id,
            role: $quoted->role->value,
            kind: $quoted->kind->value,
            sender_name: (string) $quoted->sender_name,
            preview: self::quotedMessagePreview($quoted),
            content: ! $quoted->isRecalled() && is_string($quoted->content) ? $quoted->content : null,
            attachments: $quoted->isRecalled()
                ? []
                : $quoted->attachments
                    ->map(fn (Attachment $attachment): array => ReceptionAttachmentData::fromModel($attachment)->toArray())
                    ->values()
                    ->all(),
            recalled_at: $quoted->recalled_at?->toIso8601String(),
        );
    }

    /**
     * 生成引用块中的单行预览。
     */
    private static function quotedMessagePreview(ConversationMessage $message): string
    {
        if ($message->isRecalled()) {
            return __('conversation.message_recalled_placeholder');
        }

        if (is_string($message->content) && trim($message->content) !== '') {
            return str($message->content)->squish()->limit(120, '')->toString();
        }

        return match ($message->kind) {
            MessageKind::Image => __('conversation.message_kinds.image'),
            MessageKind::File => __('conversation.message_kinds.file'),
            default => __('conversation.empty_content'),
        };
    }

    /**
     * 解析渠道默认展示给访客的接待身份。
     *
     * 统一服务模式使用渠道展示身份；实际接待模式依次使用会话版本、渠道生效版本和系统默认名称。
     *
     * @return array{0: string, 1: ?string}
     */
    public static function channelMessageIdentity(
        Channel $channel,
        ?Conversation $conversation = null,
    ): array {
        $visitorInterface = self::webVisitorInterface($channel);
        if ($visitorInterface !== null
            && $visitorInterface->visitor_identity_mode === WebChannelVisitorIdentityMode::UnifiedService) {
            return [
                filled($visitorInterface->service_display_name) ? (string) $visitorInterface->service_display_name : $channel->name,
                Attachment::findUrl($visitorInterface->service_avatar_id),
            ];
        }

        $displayName = self::resolvePlanPersonaDisplayName($conversation, $channel)
            ?? (string) __('channel.defaults.assistant_name');

        return [$displayName, null];
    }

    /**
     * 返回网站渠道的访客界面设置。
     */
    private static function webVisitorInterface(Channel $channel): ?ChannelWebVisitorInterfaceSettingsData
    {
        $settings = $channel->settings;

        return $settings instanceof ChannelWebSettingsData ? $settings->visitor_interface : null;
    }

    /**
     * 解析单条消息在访客侧展示的发送者身份。
     *
     * @param  array<string, string>  $appNicknames
     * @return array{0: ?string, 1: ?string}
     */
    private static function senderMessageIdentity(
        WebChannelVisitorIdentityMode $mode,
        ConversationMessage $message,
        string $assistantName,
        ?string $assistantAvatarUrl,
        array $appNicknames,
    ): array {
        if ($mode === WebChannelVisitorIdentityMode::UnifiedService) {
            return [$assistantName, $assistantAvatarUrl];
        }

        if ($message->role === MessageRole::Ai) {
            return [$assistantName, $assistantAvatarUrl];
        }

        if ($message->role === MessageRole::Teammate) {
            $senderUserId = filled($message->sender_user_id) ? (string) $message->sender_user_id : null;
            $nickname = $senderUserId ? ($appNicknames[$senderUserId] ?? null) : null;

            return [
                filled($nickname) ? $nickname : $message->senderUser?->name,
                $message->senderUser?->avatar,
            ];
        }

        return [null, null];
    }

    /**
     * 从会话版本或渠道生效版本读取接待身份名称。
     */
    private static function resolvePlanPersonaDisplayName(?Conversation $conversation, Channel $channel): ?string
    {
        $version = $conversation?->reception_plan_version_id !== null
            ? ReceptionPlanVersion::query()->findOrFail($conversation->reception_plan_version_id)
            : app(ChannelActivePlanVersionResolver::class)->currentVersionForChannel($channel);
        $displayName = $version?->personaConfig()->display_name;

        return filled($displayName) ? $displayName : null;
    }

    /**
     * 查询消息发送者在当前应用内的昵称。
     *
     * @param  Collection<int, ConversationMessage>  $messages
     * @return array<string, string>
     */
    private static function appNicknames(Conversation $conversation, Collection $messages): array
    {
        $userIds = $messages
            ->flatMap(fn (ConversationMessage $message): array => [
                $message->sender_user_id,
                $message->quotedMessage?->sender_user_id,
            ])
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        return DB::table('memberships')
            ->whereIn('user_id', $userIds)
            ->whereNotNull('nickname')
            ->pluck('nickname', 'user_id')
            ->mapWithKeys(fn ($nickname, $userId): array => [(string) $userId => (string) $nickname])
            ->all();
    }

    /**
     * 取同一访客在当前渠道下的已有会话 ID。
     *
     * @return list<string>
     */
    private static function historyConversationIds(Channel $channel, Conversation $conversation): array
    {
        if ($conversation->contact_id === null || $conversation->channel_id === null) {
            return [(string) $conversation->id];
        }

        return Conversation::query()

            ->where('contact_id', $conversation->contact_id)
            ->where('channel_id', $channel->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }
}
