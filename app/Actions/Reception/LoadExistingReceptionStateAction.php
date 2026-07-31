<?php

namespace App\Actions\Reception;

use App\Data\Reception\ReceptionStateData;
use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Models\Channel;
use App\Services\Reception\ReceptionSession;
use App\Services\Reception\ReceptionStateBuilder;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 读取网站访客已有接待状态；没有会话时返回不落库的空状态。
 */
class LoadExistingReceptionStateAction
{
    use AsAction;

    /**
     * 注入已有网站访客会话查询。
     */
    public function __construct(
        private readonly FindExistingWebVisitorConversationAction $findExistingVisitorConversation,
    ) {}

    /**
     * 返回访客当前会话或空状态，不创建联系人、会话和收件箱线程。
     */
    public function handle(string $channelCode, ?string $sessionToken, ?string $userToken): ReceptionStateData
    {
        $channel = $this->findChannel($channelCode);
        $resolvedToken = ReceptionSession::normalize($sessionToken) ?? ReceptionSession::generate();
        $conversation = $this->findExistingVisitorConversation->handle(
            $channel,
            $resolvedToken,
            $userToken,
        );

        if ($channel->trashed() && $conversation?->status !== ConversationStatus::Open) {
            throw new GoneHttpException('channel is paused');
        }

        return $conversation !== null
            ? ReceptionStateBuilder::build($channel, $conversation, $resolvedToken)
            : ReceptionStateBuilder::buildEmpty($channel, $resolvedToken);
    }

    /**
     * 查找网站渠道，暂停渠道仍允许恢复开放会话。
     */
    private function findChannel(string $channelCode): Channel
    {
        $channel = Channel::query()
            ->withTrashed()
            ->where('code', $channelCode)
            ->where('type', ChannelType::Web)
            ->with('receptionPlan')
            ->first();

        if ($channel === null) {
            throw new NotFoundHttpException;
        }

        return $channel;
    }
}
