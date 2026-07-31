<?php

namespace App\Actions\Channel\Web;

use App\Data\Channel\Web\WebChannelQueryParamMappingData;
use App\Enums\AttributeType;
use App\Enums\AttributeValueSource;
use App\Enums\IdentityType;
use App\Enums\TagScope;
use App\Enums\TagSource;
use App\Enums\WebChannelParamTarget;
use App\Enums\WebChannelParamTrust;
use App\Enums\WebChannelParamWriteMode;
use App\Models\AttributeDefinition;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactAttributeValue;
use App\Models\ContactIdentity;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Services\Contact\ContactIdentityNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 按渠道映射和信任级别把访客查询参数写入联系人资料。
 *
 * 格式非法、不可写属性及已被占用的唯一身份不会写入。
 */
class ApplyVisitorQueryParamsAction
{
    use AsAction;

    /** 单个参数值允许的最大长度。 */
    private const int MAX_VALUE_LENGTH = 1024;

    /** 标签模板 {value} 占位允许的字符。 */
    private const string TAG_VALUE_PATTERN = '/^[a-zA-Z0-9_-]{1,40}$/';

    /** 可写属性定义缓存五分钟，减少访客接待入口的重复查询。 */
    private const int DEFINITIONS_CACHE_TTL = 300;

    /**
     * 按渠道映射将查询参数写入联系人资料。
     *
     * @param  array<string, string>  $queryParams
     */
    public function handle(Channel $channel, Contact $contact, array $queryParams, bool $isSigned): void
    {
        if ($queryParams === []) {
            return;
        }

        $mappings = $channel->webSettings()->query_param_mappings;
        if ($mappings === []) {
            return;
        }

        $definitions = Cache::remember(
            'attribute_definitions:writable',
            self::DEFINITIONS_CACHE_TTL,
            fn (): Collection => AttributeDefinition::query()

                ->whereNull('deleted_at')
                ->get()
                ->keyBy('key'),
        );

        foreach ($mappings as $mapping) {
            $value = $this->normalizeValue($queryParams[$mapping->param_name] ?? null);
            if ($value === null) {
                continue;
            }
            if ($mapping->trust === WebChannelParamTrust::SignedOnly && ! $isSigned) {
                continue;
            }

            $this->applyMapping($contact, $mapping, $value, $definitions);
        }
    }

    /**
     * 将单条映射分派给对应的联系人字段处理逻辑。
     *
     * @param  Collection<string, AttributeDefinition>  $definitions
     */
    private function applyMapping(
        Contact $contact,
        WebChannelQueryParamMappingData $mapping,
        string $value,
        Collection $definitions,
    ): void {
        match ($mapping->target) {
            WebChannelParamTarget::ContactName => $this->applyContactName($contact, $mapping, $value),
            WebChannelParamTarget::ContactEmail => $this->applyContactEmail($contact, $mapping, $value),
            WebChannelParamTarget::ContactPhone => $this->applyContactPhone($contact, $mapping, $value),
            WebChannelParamTarget::ContactExternalId => $this->applyContactExternalId($contact, $mapping, $value),
            WebChannelParamTarget::ContactImportance => $this->applyContactImportance($contact, $mapping, $value),
            WebChannelParamTarget::Attribute => $this->applyAttribute($contact, $mapping, $value, $definitions),
            WebChannelParamTarget::Tag => $this->applyTag($contact, $mapping, $value),
        };
    }

    /**
     * 按写入模式更新联系人姓名。
     */
    private function applyContactName(Contact $contact, WebChannelQueryParamMappingData $mapping, string $value): void
    {
        if ($mapping->write_mode === WebChannelParamWriteMode::OnlyIfEmpty && filled($contact->name)) {
            return;
        }

        $contact->forceFill(['name' => $value])->saveQuietly();
    }

    /**
     * 校验并写入联系人邮箱身份。
     */
    private function applyContactEmail(Contact $contact, WebChannelQueryParamMappingData $mapping, string $value): void
    {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $normalized = ContactIdentityNormalizer::normalizeValue(IdentityType::Email, $value);
        if ($normalized === '') {
            return;
        }

        $this->writeContactIdentity(
            $contact,
            $mapping,
            type: IdentityType::Email,
            namespace: '',
            value: $normalized,
        );
    }

    /**
     * 将渠道参数写入联系人重点客户标记。
     */
    private function applyContactImportance(Contact $contact, WebChannelQueryParamMappingData $mapping, string $value): void
    {
        $isImportant = $this->normalizeBooleanValue($value);
        if ($isImportant === null) {
            return;
        }

        DB::transaction(function () use ($contact, $mapping, $isImportant): void {
            $currentContact = Contact::query()
                ->whereKey($contact->id)
                ->firstOrFail();

            if ($mapping->write_mode === WebChannelParamWriteMode::OnlyIfEmpty && $currentContact->is_important) {
                return;
            }

            if ($currentContact->is_important === $isImportant) {
                return;
            }

            // 重点标记不属于联系人搜索文档，保存时只触发业务观察器。
            Contact::withoutSyncingToSearch(fn () => $currentContact->forceFill($isImportant
                ? [
                    'is_important' => true,
                    'important_at' => now(),
                    'important_by_user_id' => null,
                    'important_source' => 'channel',
                ]
                : [
                    'is_important' => false,
                    'important_at' => null,
                    'important_by_user_id' => null,
                    'important_source' => null,
                ])->save());
        });

        $contact->refresh();
    }

    /**
     * 校验并写入联系人手机号身份。
     */
    private function applyContactPhone(Contact $contact, WebChannelQueryParamMappingData $mapping, string $value): void
    {
        if (! ContactIdentityNormalizer::isPhoneInputFormatValid($value)) {
            return;
        }
        $normalized = ContactIdentityNormalizer::normalizeValue(IdentityType::Phone, $value);
        if ($normalized === '' || ! ContactIdentityNormalizer::isNormalizedPhoneValid($normalized)) {
            return;
        }

        $this->writeContactIdentity(
            $contact,
            $mapping,
            type: IdentityType::Phone,
            namespace: '',
            value: $normalized,
        );
    }

    /**
     * 校验并写入联系人外部身份。
     */
    private function applyContactExternalId(Contact $contact, WebChannelQueryParamMappingData $mapping, string $value): void
    {
        if (strlen($value) > 191) {
            return;
        }

        $this->writeContactIdentity(
            $contact,
            $mapping,
            type: IdentityType::ExternalId,
            namespace: '',
            value: $value,
        );
    }

    /**
     * 按写入模式保存联系人身份，并保护应用内身份唯一性。
     */
    private function writeContactIdentity(
        Contact $contact,
        WebChannelQueryParamMappingData $mapping,
        IdentityType $type,
        string $namespace,
        string $value,
    ): void {
        $existingOnContact = ContactIdentity::query()
            ->where('contact_id', $contact->id)
            ->where('type', $type)
            ->where('namespace', $namespace)
            ->get();

        if ($existingOnContact->contains(fn (ContactIdentity $identity) => $identity->value === $value)) {
            return;
        }

        if ($mapping->write_mode === WebChannelParamWriteMode::OnlyIfEmpty && $existingOnContact->isNotEmpty()) {
            return;
        }

        $takenElsewhere = ContactIdentity::query()
            ->where('type', $type)
            ->where('namespace', $namespace)
            ->where('value', $value)
            ->where('contact_id', '!=', $contact->id)
            ->exists();
        if ($takenElsewhere) {
            return;
        }

        try {
            DB::transaction(function () use ($contact, $existingOnContact, $type, $namespace, $value, $mapping): void {
                if ($mapping->write_mode === WebChannelParamWriteMode::Overwrite) {
                    foreach ($existingOnContact as $identity) {
                        $identity->delete();
                    }
                }

                ContactIdentity::query()->create([
                    'contact_id' => $contact->id,
                    'type' => $type,
                    'namespace' => $namespace,
                    'value' => $value,
                    'display_value' => ContactIdentityNormalizer::buildDisplayValue($type, $value),
                ]);

                $contact->syncPrimaryFields();
            });
        } catch (UniqueConstraintViolationException) {
            Log::debug('访客传参联系人身份写入遇到并发唯一约束。', [
                'contact_id' => (string) $contact->id,
                'type' => $type->value,
                'namespace' => $namespace,
            ]);
        }
    }

    /**
     * 校验可写属性定义并保存联系人属性值。
     *
     * @param  Collection<string, AttributeDefinition>  $definitions
     */
    private function applyAttribute(Contact $contact, WebChannelQueryParamMappingData $mapping, string $value, Collection $definitions): void
    {
        $key = $mapping->target_key;
        if ($key === null || $key === '') {
            return;
        }

        /** @var AttributeDefinition|null $definition */
        $definition = $definitions->get($key);
        if ($definition === null || ! $definition->is_api_writable) {
            return;
        }
        if (! in_array($definition->type, [
            AttributeType::Text,
            AttributeType::Textarea,
            AttributeType::Number,
            AttributeType::Date,
            AttributeType::SingleSelect,
        ], true)) {
            return;
        }

        $normalized = $this->normalizeAttributeValue($definition, $value);
        if ($normalized === null) {
            return;
        }

        $existing = ContactAttributeValue::query()
            ->where('contact_id', $contact->id)
            ->where('definition_id', $definition->id)
            ->first();

        if ($mapping->write_mode === WebChannelParamWriteMode::OnlyIfEmpty && $existing !== null) {
            return;
        }

        $payload = ['value' => $normalized];

        if ($existing) {
            $existing->update([
                'value_json' => $payload,
                'source' => AttributeValueSource::Channel,
            ]);
        } else {
            try {
                ContactAttributeValue::query()->create([
                    'contact_id' => $contact->id,
                    'definition_id' => $definition->id,
                    'value_json' => $payload,
                    'source' => AttributeValueSource::Channel,
                ]);
            } catch (UniqueConstraintViolationException) {
                Log::debug('访客传参联系人属性写入遇到并发唯一约束。', [
                    'contact_id' => (string) $contact->id,
                    'definition_id' => (string) $definition->id,
                ]);
            }
        }
    }

    /**
     * 根据标签模板创建并关联联系人标签。
     */
    private function applyTag(Contact $contact, WebChannelQueryParamMappingData $mapping, string $value): void
    {
        $template = $mapping->target_key;
        if ($template === null || trim($template) === '') {
            return;
        }

        $resolved = $this->resolveTagName($template, $value);
        if ($resolved === null) {
            return;
        }

        $normalized = mb_strtolower($resolved);

        try {
            DB::transaction(function () use ($contact, $resolved, $normalized): void {
                $group = $this->resolveChannelTagGroup();

                $tag = Tag::query()
                    ->where('tag_group_id', $group->id)
                    ->where('normalized_name', $normalized)
                    ->whereNull('deleted_at')
                    ->first();

                if ($tag === null) {
                    $tag = Tag::query()->create([
                        'tag_group_id' => $group->id,
                        'name' => $resolved,
                        'source' => TagSource::Channel,
                    ]);
                }

                $alreadyAssigned = DB::table('contact_tag_assignments')
                    ->where('tag_id', $tag->id)
                    ->where('contact_id', $contact->id)
                    ->exists();

                if ($alreadyAssigned) {
                    return;
                }

                DB::table('contact_tag_assignments')->insert([
                    'tag_id' => $tag->id,
                    'contact_id' => $contact->id,
                    'assigned_by_user_id' => null,
                    'source' => TagSource::Channel,
                    'created_at' => now(),
                ]);

                $contact->searchable();
            });
        } catch (UniqueConstraintViolationException) {
            Log::debug('访客传参联系人标签写入遇到并发唯一约束。', [
                'contact_id' => (string) $contact->id,
                'tag_name' => $resolved,
            ]);
        }
    }

    /**
     * 返回联系人维度的渠道参数标签组。
     */
    private function resolveChannelTagGroup(): TagGroup
    {
        $name = __('tag.default_groups.channel');

        return TagGroup::query()->firstOrCreate(
            [
                'normalized_name' => mb_strtolower($name),
            ],
            [
                'name' => $name,
                'scope' => TagScope::Contact,
            ],
        );
    }

    /**
     * 将模板和值解析为标签名称。
     */
    private function resolveTagName(string $template, string $value): ?string
    {
        if (! str_contains($template, '{value}')) {
            $name = trim($template);

            return $name === '' ? null : mb_substr($name, 0, 120);
        }

        if (! preg_match(self::TAG_VALUE_PATTERN, $value)) {
            return null;
        }

        $name = trim(str_replace('{value}', $value, $template));

        return $name === '' ? null : mb_substr($name, 0, 120);
    }

    /**
     * 将渠道参数中的布尔型文本转换为重点客户开关。
     */
    private function normalizeBooleanValue(string $value): ?bool
    {
        return match (mb_strtolower(trim($value))) {
            '1', 'true', 'yes', 'y', 'on', 'important', 'vip' => true,
            '0', 'false', 'no', 'n', 'off' => false,
            default => null,
        };
    }

    /**
     * 按属性类型规范化查询参数值。
     */
    private function normalizeAttributeValue(AttributeDefinition $definition, string $value): mixed
    {
        return match ($definition->type) {
            AttributeType::Text, AttributeType::Textarea => mb_substr($value, 0, 1024),
            AttributeType::Number => is_numeric($value) ? $value + 0 : null,
            AttributeType::Date => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null,
            AttributeType::SingleSelect => $this->isValidOptionCode($definition, $value) ? $value : null,
            default => null,
        };
    }

    /**
     * 判断单选属性是否包含指定选项编码。
     */
    private function isValidOptionCode(AttributeDefinition $definition, string $code): bool
    {
        $options = $definition->config['options'] ?? [];

        foreach ($options as $option) {
            if (isset($option['code']) && $option['code'] === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * 将原始查询参数规范化为可写字符串。
     */
    private function normalizeValue(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }
        $value = trim($raw);
        if ($value === '' || strlen($value) > self::MAX_VALUE_LENGTH) {
            return null;
        }

        return $value;
    }
}
