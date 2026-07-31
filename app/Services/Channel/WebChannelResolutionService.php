<?php

namespace App\Services\Channel;

use App\Enums\ChannelType;
use App\Models\Channel;

/**
 * 网站渠道解析服务：封装渠道查找等跨 Action 复用的渠道层操作。
 *
 * 各 Action 通过 DI 注入共享此服务，避免直接耦合彼此的内部方法。
 */
class WebChannelResolutionService
{
    /**
     * 查找当前应用内的网站渠道，不存在时抛出 404。
     */
    public function find(string $channelId): Channel
    {
        return Channel::query()

            ->where('type', ChannelType::Web)
            ->findOrFail($channelId);
    }
}
