<?php

namespace App\Data\Inbox;

use App\Models\Channel;
use Spatie\LaravelData\Data;

/**
 * 启用网站渠道数据。
 * 由后端组装后传给 resources/js/pages/Inbox.vue 及 pages/inbox/*，用于页面展示、抽屉详情或局部交互状态。
 */
class EnabledWebChannelData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type_label,
    ) {}

    public static function fromModel(Channel $channel): self
    {
        return new self(
            id: (string) $channel->id,
            name: $channel->name,
            type_label: $channel->type->label(),
        );
    }
}
