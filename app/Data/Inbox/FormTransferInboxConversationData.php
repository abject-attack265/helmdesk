<?php

namespace App\Data\Inbox;

use Spatie\LaravelData\Data;

/**
 * 收件箱会话转接表单数据。
 */
class FormTransferInboxConversationData extends Data
{
    public function __construct(
        public string $target_user_id,
    ) {}

    /**
     * 返回转接目标校验规则。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'target_user_id' => ['required', 'string'],
        ];
    }
}
