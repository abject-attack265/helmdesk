<?php

namespace App\Actions\Channel\Telegram;

use App\Actions\Contact\MergeContactsAction;
use App\Enums\ContactType;
use App\Enums\IdentityType;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactActivityLog;
use App\Models\ContactIdentity;
use App\Services\Contact\ContactActivityLogger;
use App\Services\Contact\ContactIdentityNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * 将 Telegram 网关传入的外部用户 ID 和邮箱挂到联系人。
 *
 * 外部用户 ID 已归属于其他联系人时合并联系人。同步失败只记录告警，不阻断入站消息处理。
 */
class SyncGatewayContactIdentityAction
{
    use AsAction;

    /** 同步网关注入的外部用户 ID，并按需挂载邮箱身份。 */
    public function handle(Channel $channel, Contact $contact, string $externalId, ?string $email): void
    {
        try {
            $contact = $this->attachExternalIdentity($channel, $contact, $externalId);
            if ($email !== null) {
                $this->attachEmailIdentity($contact, $email);
            }
        } catch (Throwable $e) {
            Log::warning('网关业务身份同步失败，不影响消息落库。', [
                'channel_id' => (string) $channel->id,
                'contact_id' => (string) $contact->id,
                'external_id' => $externalId,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /** 挂载外部 ID；该 ID 已属于其他联系人时合并并返回最终联系人。 */
    private function attachExternalIdentity(Channel $channel, Contact $contact, string $externalId): Contact
    {
        $value = ContactIdentityNormalizer::validateAndNormalize(IdentityType::ExternalId, $externalId);

        $existing = ContactIdentity::query()
            ->where('type', IdentityType::ExternalId)
            ->where('value', $value)
            ->whereNull('deleted_at')
            ->first();

        if ($existing !== null) {
            if ($existing->contact_id === $contact->id) {
                return $contact;
            }

            // 外部用户 ID 已归属于其他联系人，当前联系人并入该联系人。
            return MergeContactsAction::run($existing->contact_id, $contact->id);
        }

        // 联系人已有不同的外部 ID：业务身份冲突，保留原值并告警。
        $conflicting = $contact->identities()
            ->where('type', IdentityType::ExternalId)
            ->whereNull('deleted_at')
            ->first();
        if ($conflicting !== null && $conflicting->value !== $value) {
            Log::warning('联系人已有不同的外部 ID，网关身份未覆盖。', [
                'contact_id' => (string) $contact->id,
                'existing_value' => $conflicting->value,
                'incoming_value' => $value,
            ]);

            return $contact;
        }

        try {
            ContactIdentity::query()->create([
                'contact_id' => $contact->id,
                'type' => IdentityType::ExternalId,
                'namespace' => '',
                'value' => $value,
                'display_value' => ContactIdentityNormalizer::buildDisplayValue(IdentityType::ExternalId, $value),
            ]);
        } catch (UniqueConstraintViolationException) {
            // 另一条并发请求已挂载相同身份。
            return $contact;
        }

        if (ContactIdentityNormalizer::promotesContactType(IdentityType::ExternalId) && $contact->type === ContactType::Visitor) {
            $contact->type = ContactType::Contact;
            $contact->saveQuietly();
        }
        $contact->syncPrimaryFields();

        ContactActivityLogger::record(
            $contact,
            ContactActivityLog::ACTION_UPDATED,
            payload: [
                'origin' => 'telegram_gateway',
                'identity_type' => IdentityType::ExternalId->value,
                'identity_value' => $value,
            ],
        );

        return $contact;
    }

    /** 在联系人尚无邮箱时挂载有效邮箱；邮箱已被占用或格式无效时跳过。 */
    private function attachEmailIdentity(Contact $contact, string $email): void
    {
        try {
            $value = ContactIdentityNormalizer::validateAndNormalize(IdentityType::Email, $email);
        } catch (ValidationException) {
            return;
        }

        $owned = ContactIdentity::query()
            ->where('type', IdentityType::Email)
            ->where('value', $value)
            ->whereNull('deleted_at')
            ->exists();
        if ($owned) {
            return;
        }

        $hasEmail = $contact->identities()
            ->where('type', IdentityType::Email)
            ->whereNull('deleted_at')
            ->exists();
        if ($hasEmail) {
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
            // 另一条并发请求已挂载该邮箱。
        }
    }
}
