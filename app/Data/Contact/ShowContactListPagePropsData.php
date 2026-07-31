<?php

namespace App\Data\Contact;

use App\Data\CustomAttribute\FilterAttributeDefinitionData;
use App\Data\EnumOptionData;
use App\Data\SimplePaginationData;
use App\Data\Tag\TagOptionData;
use App\Enums\ContactListType;
use App\Enums\TagMatchMode;
use Spatie\LaravelData\Data;

/**
 * 联系人页面 props。
 * 由对应 Show*Action 返回给 resources/js/pages/contacts/Index.vue、Trash.vue 和 ContactDetailDrawer.vue，用于渲染首屏数据、筛选项和页面状态。
 */
class ShowContactListPagePropsData extends Data
{
    /**
     * 承载联系人列表首屏数据、筛选选项和当前筛选状态。
     */
    public function __construct(
        /** @var ListContactItemData[] */
        public array $contact_list,
        public SimplePaginationData $contact_list_pagination,
        /** @var EnumOptionData[] */
        public array $contact_list_type_options,
        /** @var EnumOptionData[] */
        public array $tag_match_mode_options,
        public ContactListType $current_type,
        public ?string $search,
        public bool $important_only,
        /** @var FilterAttributeDefinitionData[] */
        public array $attribute_filter_definitions = [],
        /** @var array<string, mixed> */
        public array $attribute_filters = [],
        /** @var TagOptionData[] */
        public array $available_tags = [],
        public ContactTagFilterData $tag_filter = new ContactTagFilterData(
            include: [],
            include_mode: TagMatchMode::Any,
            exclude: [],
            exclude_mode: TagMatchMode::Any,
            untagged_only: false,
        ),
    ) {}
}
