<?php

namespace App\Actions\Conversation;

use App\Models\User;
use App\Services\Conversation\ConversationEventPayloadDecoder;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 批量解析会话时间线事件里涉及的应用成员名称。
 */
class BuildConversationTimelineUserMapAction
{
    use AsAction;

    /**
     * 解析当前分页事件涉及的成员名称，包括已软删除成员。
     *
     * @param  Collection<int, object>  $rows
     * @return array<string, string>
     */
    public function handle(Collection $rows): array
    {
        $userIds = $rows
            ->filter(fn (object $row): bool => (string) $row->type === 'event')
            ->flatMap(fn (object $row): array => $this->extractUserIds($row))
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        return User::query()
            ->withTrashed()
            ->whereIn('id', $userIds->all())
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, string $id): array => [(string) $id => $name])
            ->all();
    }

    /**
     * 从事件行的 actor 和 payload 中提取可能参与展示的用户 ID。
     *
     * @return list<string>
     */
    private function extractUserIds(object $row): array
    {
        $payload = ConversationEventPayloadDecoder::decode($row->payload);
        $ids = [];

        if ($row->actor_user_id !== null) {
            $ids[] = (string) $row->actor_user_id;
        }

        foreach (['user_id', 'previous_user_id'] as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key])) {
                $ids[] = (string) $payload[$key];
            }
        }

        return $ids;
    }
}
