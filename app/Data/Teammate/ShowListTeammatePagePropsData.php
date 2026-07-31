<?php

namespace App\Data\Teammate;

use App\Data\CurrentUserContextData;
use App\Data\EnumOptionData;
use App\Data\Invitation\ListInvitationItemData;
use Spatie\LaravelData\Data;

/**
 * 客服列表页面数据。
 */
class ShowListTeammatePagePropsData extends Data
{
    public function __construct(
        /** @var CurrentUserContextData[] */
        public array $user_list,

        /** @var ListInvitationItemData[] */
        public array $pending_invitations,

        /** @var EnumOptionData[] */
        public array $online_status_options,

        public string $current_search,
        public string $current_online_status,
        public bool $can_create = false,
    ) {}
}
