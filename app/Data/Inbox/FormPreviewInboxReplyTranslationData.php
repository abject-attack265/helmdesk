<?php

namespace App\Data\Inbox;

use App\Services\Conversation\TeammateMessageLimits;
use Spatie\LaravelData\Data;

/**
 * 收件箱回复访客内容预览表单数据。
 */
class FormPreviewInboxReplyTranslationData extends Data
{
    /**
     * 承载客服输入的待发送文本。
     */
    public function __construct(
        public string $content,
    ) {}

    /**
     * 返回访客内容预览校验规则。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:'.TeammateMessageLimits::MAX_CONTENT_LENGTH],
        ];
    }
}
