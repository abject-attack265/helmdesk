<?php

namespace App\Actions\Reception;

use App\Actions\Contact\ResolveContactIdentityAction;
use App\Data\Channel\WechatOfficialAccount\ChannelWechatOfficialAccountSettingsData;
use App\Enums\ChannelType;
use App\Enums\ContactSource;
use App\Enums\ConversationEntryMode;
use App\Enums\IdentityType;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** 解析微信公众号访客的联系人和开放会话。 */
class ResolveWechatOfficialAccountReceptionContextAction
{
    use AsAction;

    /** 创建微信公众号接待上下文解析流程。 */
    public function __construct(
        private readonly ResolveContactIdentityAction $resolveContactIdentityAction,
        private readonly FindOrCreateReceptionConversationAction $findOrCreateReceptionConversationAction,
    ) {}

    /**
     * 解析访客身份并获取当前接待会话。
     *
     * @return array{channel: Channel, contact: Contact, conversation: Conversation, created: bool}
     */
    public function handle(string $channelCode, string $openid, ?string $displayName = null): array
    {
        $channel = $this->findActiveChannel($channelCode);
        /** @var ChannelWechatOfficialAccountSettingsData $settings */
        $settings = $channel->settings;

        $contact = $this->resolveContactIdentityAction->handle(
            [
                'type' => IdentityType::ChannelAccount,
                'value' => $openid,
                'namespace' => self::identityNamespace($channelCode, $settings->app_id),
            ],
            ContactSource::WechatOfficialAccount,
            name: $displayName,
        );

        $this->touchContact($contact, $displayName);

        [$conversation, $created] = $this->findOrCreateReceptionConversationAction->handle(
            $channel,
            $contact,
            ConversationEntryMode::WechatOfficialAccount,
            $settings->default_visitor_locale->value,
        );
        $contact->refresh();

        return [
            'channel' => $channel,
            'contact' => $contact,
            'conversation' => $conversation,
            'created' => $created,
        ];
    }

    /** OpenID 仅在所属 AppID 内唯一。 */
    public static function identityNamespace(string $channelCode, string $appId): string
    {
        return 'wechat_oa:'.$channelCode.':'.trim($appId);
    }

    /** 查找可接收微信公众号回调的启用渠道。 */
    private function findActiveChannel(string $channelCode): Channel
    {
        $channel = Channel::query()
            ->where('code', $channelCode)
            ->where('type', ChannelType::WechatOfficialAccount)
            ->with('receptionPlan')
            ->first();

        if ($channel === null) {
            throw new NotFoundHttpException;
        }

        return $channel;
    }

    /** 更新联系人最近活跃时间及缺失的显示名称。 */
    private function touchContact(Contact $contact, ?string $displayName): void
    {
        $updates = ['last_seen_at' => now()];

        if (filled($displayName) && ! filled($contact->name)) {
            $updates['name'] = Str::limit(trim((string) $displayName), 255, '');
        }

        $contact->forceFill($updates)->saveQuietly();
    }
}
