<?php

namespace App\Actions\Channel\Web\Public;

use App\Data\Channel\Web\PublicStandaloneChannelData;
use App\Enums\ChannelType;
use App\Models\Channel;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 根据公开 code 解析访客独立页启动所需的渠道数据。
 *
 * AI 不可用时，访客仍可进入独立页发起会话并排队待人工接待。
 *
 * 渠道被软删除时返回 paused=true 的最小化数据，用于渲染品牌化的"暂时不可用"占位。
 */
class ResolvePublicWebChannelBootstrapAction
{
    use AsAction;

    /**
     * 通过公开渠道 code 生成独立页启动数据。
     */
    public function handle(string $code): PublicStandaloneChannelData
    {
        $channel = Channel::query()
            ->withTrashed()
            ->where('code', $code)
            ->where('type', ChannelType::Web)
            ->first();

        if ($channel === null) {
            throw new NotFoundHttpException;
        }

        return PublicStandaloneChannelData::fromModel(
            $channel,
            paused: $channel->trashed(),
        );
    }
}
