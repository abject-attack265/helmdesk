<?php

namespace App\Actions\Conversation;

use App\Data\CurrentUserContextData;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 人工从会话移除标签。
 * 不物理删除而是写入 removed_at 抑制墓碑，AI 重算时不会再把这个标签复打回去（保护人工干预）。
 */
class DetachConversationTagAction
{
    use AsAction;

    /**
     * 给会话上的有效标签写入 removed_at 抑制墓碑，等价于人工移除。
     */
    public function handle(string $conversationId, string $tagId, ?User $actor = null): void
    {
        $conversation = Conversation::query()

            ->findOrFail($conversationId);

        DB::table('conversation_tag_assignments')
            ->where('conversation_id', $conversation->id)
            ->where('tag_id', $tagId)
            ->whereNull('removed_at')
            ->update([
                'removed_at' => now(),
                'removed_by_user_id' => $actor?->id,
                'updated_at' => now(),
            ]);
    }

    /**
     * 接收会话标签移除的 XHR 请求并返回 JSON。
     */
    public function asController(Request $request, string $conversation, string $tagId): JsonResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $this->handle($conversation, $tagId, $request->user());

        return response()->json(['success' => true]);
    }
}
