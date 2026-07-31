<?php

namespace App\Data\Teammate;

use Spatie\LaravelData\Data;

class ShowInviteTeammatePagePropsData extends Data
{
    /** @param PermissionGroupData[] $permission_groups */
    public function __construct(
        public array $permission_groups,
        public bool $can_assign_permissions,
    ) {}
}
