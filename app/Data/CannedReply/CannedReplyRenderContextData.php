<?php

namespace App\Data\CannedReply;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Settings\GeneralSettings;
use Spatie\LaravelData\Data;

/**
 * 快捷回复渲染上下文。
 * 由 UseAndRenderCannedReplyAction 组装，再喂给 CannedReplyVariableResolver 解析 {{contact.name}} 等静态 token。
 * 字段允许为空，渲染器对缺值 token 保留原文。
 */
class CannedReplyRenderContextData extends Data
{
    public function __construct(
        public ?string $app_name,
        public ?string $teammate_name,
        public ?string $contact_name,
        public ?string $contact_email,
        public ?string $contact_primary_phone,
        public ?string $conversation_id,
        public ?string $conversation_subject,
    ) {}

    public static function build(
        ?User $teammate = null,
        ?Contact $contact = null,
        ?Conversation $conversation = null,
    ): self {
        return new self(
            app_name: app(GeneralSettings::class)->name,
            teammate_name: $teammate?->name,
            contact_name: $contact?->name,
            contact_email: $contact?->primary_email,
            contact_primary_phone: $contact?->primary_phone,
            conversation_id: $conversation?->id !== null ? (string) $conversation->id : null,
            conversation_subject: $conversation?->subject,
        );
    }
}
