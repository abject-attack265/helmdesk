<?php

namespace App\Data\Inbox;

use App\Enums\ReceptionLanguage;
use App\Services\Conversation\TeammateMessageLimits;
use App\Services\LocalePreference;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 收件箱回复表单数据，用于校验并发送客服消息。
 */
class FormReplyInboxConversationData extends Data
{
    /**
     * 承载客服原文、访客可见内容、附件、幂等键和引用回复目标。
     */
    public function __construct(
        public ?string $content = null,
        /** @var list<string> */
        public array $attachment_ids = [],
        public ?string $client_msg_id = null,
        public ?string $quoted_message_id = null,
        public ?string $visitor_content = null,
        public ?string $visitor_locale = null,
        public ?string $source_locale = null,
    ) {}

    /**
     * 返回收件箱回复表单的验证规则。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:'.TeammateMessageLimits::MAX_CONTENT_LENGTH],
            'attachment_ids' => ['nullable', 'array', 'max:'.TeammateMessageLimits::MAX_ATTACHMENT_COUNT],
            'attachment_ids.*' => ['string', 'distinct'],
            'client_msg_id' => ['nullable', 'string', 'max:64'],
            'quoted_message_id' => ['nullable', 'ulid'],
            'visitor_content' => ['required_with:visitor_locale,source_locale', 'nullable', 'string', 'max:'.TeammateMessageLimits::MAX_CONTENT_LENGTH],
            'visitor_locale' => ['required_with:visitor_content,source_locale', 'nullable', 'string', Rule::in(array_column(ReceptionLanguage::cases(), 'value'))],
            'source_locale' => ['required_with:visitor_content,visitor_locale', 'nullable', 'string', 'max:20', 'regex:'.LocalePreference::LANGUAGE_TAG_PATTERN],
        ];
    }
}
