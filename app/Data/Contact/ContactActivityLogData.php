<?php

namespace App\Data\Contact;

use App\Models\ContactActivityLog;
use Spatie\LaravelData\Data;

/**
 * 联系人详情抽屉中的活动日志数据。
 */
class ContactActivityLogData extends Data
{
    /**
     * 接收活动类型、展示快照和操作人信息。
     *
     * @param  array<int, string>  $identity_values
     */
    public function __construct(
        public string $id,
        public string $action,
        public ?string $related_contact_name,
        public ?string $actor_name,
        public string $created_at,
        public array $identity_values,
        /** @var array<string, mixed>|null */
        public ?array $payload,
    ) {}

    /**
     * 从活动日志及其操作人关系构造展示数据。
     */
    public static function fromModel(ContactActivityLog $activityLog): self
    {
        $payload = $activityLog->payload ?? [];
        $identityValues = collect(data_get($payload, 'identity_values', []))
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->values()
            ->all();

        return new self(
            id: $activityLog->id,
            action: $activityLog->action,
            related_contact_name: data_get($payload, 'related_contact_name'),
            actor_name: $activityLog->actor?->name,
            created_at: $activityLog->created_at->toIso8601String(),
            identity_values: $identityValues,
            payload: $payload !== [] ? $payload : null,
        );
    }
}
