<?php

namespace App\Data\Teammate;

use App\Data\EnumOptionData;
use App\Enums\UserPermission;
use Spatie\LaravelData\Data;

class PermissionGroupData extends Data
{
    /**
     * @param  list<EnumOptionData>  $permissions
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $permissions,
    ) {}

    /**
     * @param  list<UserPermission>  $permissions
     */
    public static function fromPermissions(string $key, array $permissions): self
    {
        return new self(
            key: $key,
            label: $permissions[0]->groupLabel(),
            permissions: EnumOptionData::fromCases($permissions),
        );
    }

    /**
     * @return list<self>
     */
    public static function allGroups(): array
    {
        return array_map(
            static fn (string $key, array $permissions): self => self::fromPermissions($key, $permissions),
            array_keys(UserPermission::groupedCases()),
            array_values(UserPermission::groupedCases()),
        );
    }
}
