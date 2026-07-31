<?php

namespace App\Data\Teammate;

use Spatie\LaravelData\Data;

class ShowCreateTeammatePagePropsData extends Data
{
    /**
     * @param  list<PermissionGroupData>  $permission_groups
     */
    public function __construct(
        public array $permission_groups,
        public bool $can_assign_permissions,
    ) {}
}
