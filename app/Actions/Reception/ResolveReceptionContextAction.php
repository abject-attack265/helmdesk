<?php

namespace App\Actions\Reception;

use App\Actions\Channel\Web\ApplyVisitorQueryParamsAction;
use App\Actions\Contact\ResolveContactIdentityAction;
use App\Enums\ChannelType;
use App\Enums\ContactSource;
use App\Enums\ConversationEntryMode;
use App\Enums\IdentityType;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Models\Conversation;
use App\Services\Channel\WebChannelUserTokenVerifier;
use App\Services\Contact\ContactIdentityNormalizer;
use App\Services\Reception\ReceptionSession;
use DateTimeZone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 解析网站访客身份、联系人、会话和渠道上下文。
 */
class ResolveReceptionContextAction
{
    use AsAction;

    /**
     * 注入网站访客身份、查询参数、会话和渠道上下文服务。
     */
    public function __construct(
        private readonly ResolveContactIdentityAction $resolveContactIdentityAction,
        private readonly WebChannelUserTokenVerifier $userTokenVerifier,
        private readonly ApplyVisitorQueryParamsAction $applyVisitorQueryParamsAction,
        private readonly FindOrCreateReceptionConversationAction $findOrCreateReceptionConversationAction,
        private readonly CaptureWebConversationContextAction $captureWebConversationContextAction,
    ) {}

    /**
     * 解析网站访客接待上下文并写入本次真实消息携带的访客信息。
     *
     * 签名 token 使用 sub 定位 ExternalId 联系人；其他请求使用渠道会话 token。
     * query 参数更新联系人字段，visitorClient 更新会话渠道快照和浏览轨迹。
     *
     * @param  array{locale?: string|null, timezone?: string|null, country?: string|null, city?: string|null}|null  $visitorEnvironment
     * @param  array<string, string>|null  $queryParams
     * @param  array<string, mixed>|null  $visitorClient
     * @return array{channel: Channel, contact: Contact, conversation: Conversation, session_token: string, created: bool, signed_identity: array{external_id: string, name: ?string, email: ?string, claims: array<string, mixed>}|null}
     */
    public function handle(
        string $channelCode,
        ?string $sessionToken,
        ?ConversationEntryMode $entryMode = null,
        ?array $visitorEnvironment = null,
        ?string $userToken = null,
        ?array $queryParams = null,
        ?array $visitorClient = null,
    ): array {
        $entryMode ??= ConversationEntryMode::Standalone;

        $channel = $this->findWebChannel($channelCode);

        $token = ReceptionSession::normalize($sessionToken) ?? ReceptionSession::generate();

        $signedIdentity = $this->userTokenVerifier->verify($channel, $userToken);
        $contact = $signedIdentity !== null
            ? $this->resolveSignedContact($channel, $signedIdentity)
            : $this->resolveContactIdentityAction->handle(
                ['type' => IdentityType::Session, 'value' => $token],
                ContactSource::Web,
            );

        $normalizedQueryParams = $this->normalizeQueryParams($queryParams);

        $this->touchContactVisit($contact, $visitorEnvironment);
        $this->applyVisitorQueryParamsAction->handle(
            $channel,
            $contact,
            $normalizedQueryParams,
            $signedIdentity !== null,
        );

        [$conversation, $created] = $this->findOrCreateReceptionConversationAction->handle(
            $channel,
            $contact,
            $entryMode,
            $channel->settings->default_visitor_locale->value,
        );

        if ($channel->type === ChannelType::Web && $visitorClient !== null) {
            $this->captureWebConversationContextAction->handle(
                $conversation,
                $visitorClient,
                $normalizedQueryParams,
                $created,
            );
        }

        $conversation->refresh();
        $contact = $conversation->contact()->firstOrFail();

        return [
            'channel' => $channel,
            'contact' => $contact,
            'conversation' => $conversation,
            'session_token' => $token,
            'created' => $created,
            'signed_identity' => $signedIdentity,
        ];
    }

    /**
     * 保留入口透传的非空字符串 query 参数。
     *
     * @param  array<string, mixed>|null  $queryParams
     * @return array<string, string>
     */
    private function normalizeQueryParams(?array $queryParams): array
    {
        if ($queryParams === null) {
            return [];
        }

        $normalized = [];
        foreach ($queryParams as $key => $value) {
            if (! is_string($key) || $key === '' || ! is_string($value)) {
                continue;
            }
            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }
            $normalized[$key] = $trimmed;
        }

        return $normalized;
    }

    /**
     * 根据签名 token 解析联系人，并按需补齐 name / email identity。
     *
     * @param  array{external_id: string, name: ?string, email: ?string, claims: array<string, mixed>}  $signedIdentity
     */
    private function resolveSignedContact(Channel $channel, array $signedIdentity): Contact
    {
        $contact = $this->resolveContactIdentityAction->handle(
            [
                'type' => IdentityType::ExternalId,
                'value' => $signedIdentity['external_id'],
            ],
            ContactSource::Web,
            name: $signedIdentity['name'],
        );

        // 当前联系人未设置展示名时，补齐 token 携带的展示名。
        if ($signedIdentity['name'] !== null && ! filled($contact->name)) {
            $contact->forceFill(['name' => $signedIdentity['name']])->saveQuietly();
        }

        if ($signedIdentity['email'] !== null) {
            $this->attachEmailIdentityIfMissing($channel, $contact, $signedIdentity['email']);
        }

        return $contact;
    }

    /**
     * 把 token 携带的邮箱挂到联系人上：仅在邮箱在本应用未被占用、且联系人尚无该邮箱时追加。
     * 冲突时不写入，保留给客服显式合并。
     */
    private function attachEmailIdentityIfMissing(Channel $channel, Contact $contact, string $email): void
    {
        $value = ContactIdentityNormalizer::normalizeValue(IdentityType::Email, $email);
        if ($value === '') {
            return;
        }

        $alreadyAttached = ContactIdentity::query()

            ->where('contact_id', $contact->id)
            ->where('type', IdentityType::Email)
            ->where('value', $value)
            ->exists();

        if ($alreadyAttached) {
            return;
        }

        $taken = ContactIdentity::query()

            ->where('type', IdentityType::Email)
            ->where('namespace', '')
            ->where('value', $value)
            ->exists();

        if ($taken) {
            return;
        }

        try {
            ContactIdentity::query()->create([
                'contact_id' => $contact->id,
                'type' => IdentityType::Email,
                'namespace' => '',
                'value' => $value,
                'display_value' => ContactIdentityNormalizer::buildDisplayValue(IdentityType::Email, $value),
            ]);
            $contact->syncPrimaryFields();
        } catch (UniqueConstraintViolationException) {
            Log::debug('访客邮箱身份写入遇到并发唯一约束。', [
                'contact_id' => (string) $contact->id,
                'email' => $value,
            ]);
        }
    }

    /**
     * 查找网站接待渠道；暂停渠道的既有会话仍可继续收发消息。
     */
    private function findWebChannel(string $channelCode): Channel
    {
        $channel = Channel::query()
            ->withTrashed()
            ->where('code', $channelCode)
            ->where('type', ChannelType::Web)
            ->with('receptionPlan')
            ->first();

        if ($channel === null) {
            throw new NotFoundHttpException;
        }

        return $channel;
    }

    /**
     * 更新联系人最近访问时间和可识别的访客环境信息。
     *
     * @param  array{locale?: string|null, timezone?: string|null, country?: string|null, city?: string|null}|null  $visitorEnvironment
     */
    private function touchContactVisit(Contact $contact, ?array $visitorEnvironment): void
    {
        $updates = ['last_seen_at' => now()];

        $timezone = $this->normalizeTimezone($visitorEnvironment['timezone'] ?? null);
        if ($timezone !== null) {
            $updates['timezone'] = $timezone;
        }

        foreach (['country', 'city'] as $field) {
            $value = $this->normalizeText($visitorEnvironment[$field] ?? null, 120);
            if ($value !== null) {
                $updates[$field] = $value;
            }
        }

        $contact->forceFill($updates)->saveQuietly();
    }

    /**
     * 清理访客环境中的短文本字段。
     */
    private function normalizeText(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '' || mb_strlen($normalized) > $maxLength) {
            return null;
        }

        return $normalized;
    }

    /**
     * 校验并标准化访客时区。
     */
    private function normalizeTimezone(mixed $value): ?string
    {
        $timezone = $this->normalizeText($value, 80);
        if ($timezone === null) {
            return null;
        }

        // 同时接受废弃的 IANA 别名（如 Asia/Saigon → 越南），否则这些地区访客上报的浏览器时区
        // 会被判非法、整段访客上下文解析抛 422，导致打不开聊天。PHP 能正确处理 BC 别名，按原样保留。
        if (in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            return $timezone;
        }

        throw ValidationException::withMessages([
            'timezone' => __('validation.timezone', ['attribute' => 'timezone']),
        ]);
    }
}
