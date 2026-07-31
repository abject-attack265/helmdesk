<?php

namespace App\Data\CannedReply;

use Spatie\LaravelData\Data;

/**
 * 快捷回复列表页面 props。
 * 由 ShowCannedReplyListAction 返回，对应 resources/js/pages/cannedReplies/Index.vue。
 * canned_reply_list 已按"个人优先 + 最近使用 + 常用"排序，前端直接渲染。
 */
class ShowCannedReplyListPagePropsData extends Data
{
    /**
     * @param  array<int, ListCannedReplyItemData>  $canned_reply_list
     * @param  array<int, CannedReplyTokenOptionData>  $available_tokens
     */
    public function __construct(
        public array $canned_reply_list,
        public string $current_visibility,
        public array $available_tokens,
    ) {}
}
