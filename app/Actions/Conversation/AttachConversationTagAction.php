<?php

namespace App\Actions\Conversation;

use App\Data\Conversation\FormAttachConversationTagData;
use App\Data\CurrentUserContextData;
use App\Enums\TagScope;
use App\Enums\TagSource;
use App\Models\Conversation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 人工给会话附加标签；若该标签曾被人工移除（removed_at 墓碑），则复活为人工来源。
 */
class AttachConversationTagAction
{
    use AsAction;

    /**
     * 校验标签为会话维度后，以人工来源附加到会话（复用同一行，清除抑制墓碑）。
     */
    public function handle(string $conversationId, FormAttachConversationTagData $data, ?User $actor = null): void
    {
        $conversation = Conversation::query()

            ->findOrFail($conversationId);

        $tag = Tag::query()

            ->with('tagGroup')
            ->findOrFail($data->tag_id);

        // 会话只能打会话维度的标签，挡住联系人维度标签误用。
        if ($tag->tagGroup->scope !== TagScope::Conversation) {
            throw ValidationException::withMessages([
                'tag_id' => __('tag.errors.group_scope_mismatch'),
            ]);
        }

        DB::table('conversation_tag_assignments')->upsert(
            [
                'conversation_id' => $conversation->id,
                'tag_id' => $tag->id,
                'source' => TagSource::Manual->value,
                'confidence' => null,
                'reason' => null,
                'assigned_by_user_id' => $actor?->id,
                'based_on_seq_no' => null,
                'removed_at' => null,
                'removed_by_user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['conversation_id', 'tag_id'],
            ['source', 'assigned_by_user_id', 'removed_at', 'removed_by_user_id', 'updated_at'],
        );
    }

    /**
     * 接收会话标签附加的 XHR 请求并返回 JSON。
     */
    public function asController(Request $request, string $conversation): JsonResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $data = FormAttachConversationTagData::from($request);
        $this->handle($conversation, $data, $request->user());

        return response()->json(['success' => true]);
    }
}
