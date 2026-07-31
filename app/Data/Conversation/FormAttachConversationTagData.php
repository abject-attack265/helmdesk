<?php

namespace App\Data\Conversation;

use Spatie\LaravelData\Data;

/**
 * 给会话人工附加标签的表单数据。
 * 提交来源：接待页时间线中某次会话的摘要块（ConversationSummaryBlock）上的标签选择器。
 */
class FormAttachConversationTagData extends Data
{
    public function __construct(
        public string $tag_id,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'tag_id' => ['required', 'string'],
        ];
    }
}
