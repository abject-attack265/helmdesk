<?php

namespace App\Actions\Reception;

use App\Enums\IdentityType;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Services\Channel\WebChannelUserTokenVerifier;
use App\Services\Reception\ReceptionSession;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 按网站访客的签名身份或会话身份查找已有联系人。
 */
class FindExistingWebVisitorContactAction
{
    use AsAction;

    /**
     * 注入网站访客签名身份校验服务。
     */
    public function __construct(
        private readonly WebChannelUserTokenVerifier $userTokenVerifier,
    ) {}

    /**
     * 返回已有联系人；已关闭会话的评价入口可接受签名过期但内容有效的 token。
     */
    public function handle(
        Channel $channel,
        ?string $sessionToken,
        ?string $userToken,
        bool $acceptExpiredUserToken = false,
    ): ?Contact {
        $signedIdentity = $acceptExpiredUserToken
            ? $this->userTokenVerifier->verifyIgnoringLifecycle($channel, $userToken)
            : $this->userTokenVerifier->verify($channel, $userToken);
        if ($signedIdentity !== null) {
            return $this->findByIdentity(IdentityType::ExternalId, $signedIdentity['external_id']);
        }

        $token = ReceptionSession::normalize($sessionToken);
        if ($token === null) {
            return null;
        }

        return $this->findByIdentity(IdentityType::Session, $token);
    }

    /**
     * 按身份类型和值查找联系人。
     */
    private function findByIdentity(IdentityType $type, string $value): ?Contact
    {
        $contactId = ContactIdentity::query()
            ->where('type', $type)
            ->where('namespace', '')
            ->where('value', $value)
            ->value('contact_id');

        return $contactId !== null
            ? Contact::query()->findOrFail($contactId)
            : null;
    }
}
