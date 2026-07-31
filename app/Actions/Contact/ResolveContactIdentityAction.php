<?php

namespace App\Actions\Contact;

use App\Enums\ContactSource;
use App\Enums\ContactType;
use App\Enums\IdentityType;
use App\Models\Contact;
use App\Models\ContactActivityLog;
use App\Models\ContactIdentity;
use App\Services\Contact\ContactActivityLogger;
use App\Services\Contact\ContactIdentityNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 按身份标识查找或创建联系人，用于渠道访客归属。
 */
class ResolveContactIdentityAction
{
    use AsAction;

    /**
     * @param  array{type: IdentityType, value: string, namespace?: string}  $identityData
     */
    public function handle(
        array $identityData,
        ContactSource $source = ContactSource::Web,
        ?string $name = null,
    ): Contact {
        $type = $identityData['type'];
        $value = ContactIdentityNormalizer::validateAndNormalize($type, $identityData['value']);
        $namespace = $identityData['namespace'] ?? '';

        if ($type->requiresNamespace() && $namespace === '') {
            throw ValidationException::withMessages([
                'namespace' => __('contact.namespace_required_for_external_id'),
            ]);
        }

        $shouldPromote = ContactIdentityNormalizer::promotesContactType($type);

        try {
            return $this->resolveWithinTransaction($type, $value, $namespace, $source, $name, $shouldPromote);
        } catch (UniqueConstraintViolationException) {
            // 同一新访客的并发请求（如 Telegram 同时送达的多条 webhook）都通过了「身份不存在」检查、
            // 竞争插入同一身份：冲突方事务已整体回滚，重跑一次即可命中赢家已提交的身份，走「已存在」分支。
            // 只重试一次：再冲突说明不是这类瞬时竞态。
            return $this->resolveWithinTransaction($type, $value, $namespace, $source, $name, $shouldPromote);
        }
    }

    /**
     * 在单个事务内查找或创建「身份 + 联系人」。
     */
    private function resolveWithinTransaction(
        IdentityType $type,
        string $value,
        string $namespace,
        ContactSource $source,
        ?string $name,
        bool $shouldPromote,
    ): Contact {
        return DB::transaction(function () use ($type, $value, $namespace, $source, $name, $shouldPromote) {
            $existing = ContactIdentity::query()

                ->where('type', $type)
                ->where('namespace', $namespace)
                ->where('value', $value)
                ->whereNull('deleted_at')
                ->first();

            if ($existing) {
                $contact = Contact::query()->findOrFail($existing->contact_id);

                if ($shouldPromote && $contact->type === ContactType::Visitor) {
                    $previousType = $contact->type;
                    $contact->type = ContactType::Contact;
                    $contact->saveQuietly();
                    ContactActivityLogger::record(
                        $contact,
                        ContactActivityLog::ACTION_UPDATED,
                        payload: [
                            'origin' => 'resolve_identity',
                            'field_changes' => [
                                'type' => [
                                    'old' => $previousType->value,
                                    'new' => $contact->type->value,
                                ],
                            ],
                        ],
                    );
                }

                return $contact;
            }

            $contact = Contact::query()->create([
                'type' => $shouldPromote ? ContactType::Contact : ContactType::Visitor,
                'source' => $source,
                'name' => $name,
                'avatar_url' => Contact::DEFAULT_AVATAR_URL,
            ]);

            ContactIdentity::query()->create([
                'contact_id' => $contact->id,
                'type' => $type,
                'namespace' => $namespace,
                'value' => $value,
                'display_value' => ContactIdentityNormalizer::buildDisplayValue($type, $value),
            ]);

            $contact->syncPrimaryFields();
            ContactActivityLogger::record(
                $contact,
                ContactActivityLog::ACTION_CREATED,
                payload: [
                    'origin' => 'resolve_identity',
                    'name' => $contact->name,
                    'source' => $contact->source->value,
                    'type' => $contact->type->value,
                    'identity_type' => $type->value,
                    'identity_value' => $value,
                    'identity_values' => [$value],
                ],
            );

            return $contact;
        });
    }
}
