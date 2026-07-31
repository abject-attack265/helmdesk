<?php

namespace App\Data\Contact;

use App\Data\EnumOptionData;
use App\Data\SimplePaginationData;
use Spatie\LaravelData\Data;

/**
 * 联系人回收站页面 props。
 * 由对应 Show*Action 返回给 resources/js/pages/contacts/Index.vue、Trash.vue 和 ContactDetailDrawer.vue，用于渲染首屏数据、筛选项和页面状态。
 */
class ShowContactTrashPagePropsData extends Data
{
    /**
     * 承载联系人回收站首屏数据、分页状态和联系人页签选项。
     */
    public function __construct(
        /** @var TrashContactItemData[] */
        public array $contact_trash_list,
        public SimplePaginationData $contact_trash_list_pagination,
        /** @var EnumOptionData[] */
        public array $contact_list_type_options,
    ) {}
}
