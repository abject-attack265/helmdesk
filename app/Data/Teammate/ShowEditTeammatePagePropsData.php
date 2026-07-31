<?php

namespace App\Data\Teammate;

use Spatie\LaravelData\Data;

/**
 * 编辑客服页面数据。
 */
class ShowEditTeammatePagePropsData extends Data
{
    /** 创建成员编辑页面所需的资料、权限选项和操作能力。 */
    public function __construct(
        public TeammateData $user_form,
        /** @var list<PermissionGroupData> */
        public array $permission_groups = [],
        public bool $can_update_profile = true,
        public bool $can_update_credentials = false,
        public bool $can_assign_permissions = false,
    ) {}
}
