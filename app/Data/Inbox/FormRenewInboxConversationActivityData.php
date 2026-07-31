<?php

namespace App\Data\Inbox;

use Spatie\LaravelData\Data;

/**
 * 收件箱会话活动续期表单，标识当前浏览器页面是否仍选中可回复会话。
 */
class FormRenewInboxConversationActivityData extends Data
{
    /**
     * 创建一次页面活动更新请求。
     */
    public function __construct(
        public string $activity_id,
        public int $sequence,
        public bool $active,
    ) {}

    /**
     * 返回会话活动续期表单的验证规则。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'activity_id' => ['required', 'uuid'],
            'sequence' => ['required', 'integer', 'min:1'],
            'active' => ['required', 'boolean'],
        ];
    }
}
