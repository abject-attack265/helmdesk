<?php

namespace App\Actions\CannedReply;

use App\Data\CannedReply\CannedReplyRenderContextData;
use App\Data\CannedReply\RenderedCannedReplyData;
use App\Data\CurrentUserContextData;
use App\Exceptions\BusinessException;
use App\Models\CannedReply;
use App\Models\Conversation;
use App\Models\User;
use App\Services\CannedReply\CannedReplyVariableResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 客服选中一条快捷回复后的"原子渲染 + 计数 + 标记最近使用"。
 *
 * 一次往返完成三件事：
 * 1. 用当前会话上下文渲染模版 ({{contact.name}} 等静态变量)
 * 2. 原子地把 usage_count 自增 1、last_used_at 置为当前时间
 * 3. 返回渲染结果给前端 composer 直接插入光标位置
 */
class UseAndRenderCannedReplyAction
{
    use AsAction;

    public function __construct(
        private readonly CannedReplyVariableResolver $resolver,
    ) {}

    /**
     * 渲染并记录使用一次。
     */
    public function handle(
        User $user,
        string $cannedReplyId,
        ?string $conversationId,
    ): RenderedCannedReplyData {
        $reply = CannedReply::query()

            ->find($cannedReplyId);

        if ($reply === null) {
            throw new NotFoundHttpException;
        }

        if (! Gate::forUser($user)->allows('view', $reply)) {
            throw new BusinessException(__('canned_reply.errors.forbidden'));
        }

        $conversation = $this->resolveConversation($conversationId);
        $context = CannedReplyRenderContextData::build(
            teammate: $user,
            contact: $conversation?->contact,
            conversation: $conversation,
        );

        $rendered = $this->resolver->render($reply->content, $context);

        // 用原子 SQL 自增防止并发重复读写。
        $now = Carbon::now();
        DB::table('canned_replies')
            ->where('id', $reply->id)
            ->update([
                'usage_count' => DB::raw('usage_count + 1'),
                'last_used_at' => $now,
                'updated_at' => $now,
            ]);

        $reply->refresh();

        return new RenderedCannedReplyData(
            id: (string) $reply->id,
            rendered_content: $rendered['content'],
            original_content: $reply->content,
            warnings: $rendered['warnings'],
            usage_count: (int) $reply->usage_count,
            last_used_at: $reply->last_used_at?->toIso8601String(),
        );
    }

    /**
     * XHR 入口：返回 JSON 给前端 composer。
     */
    public function asController(Request $request, string $cannedReply): JsonResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $user = User::query()->findOrFail($ctx->user_id);

        $conversationId = $request->input('conversation_id');

        $rendered = $this->handle(
            user: $user,
            cannedReplyId: $cannedReply,
            conversationId: is_string($conversationId) && $conversationId !== '' ? $conversationId : null,
        );

        return response()->json($rendered->toArray());
    }

    /**
     * 解析 conversation_id 并加载渲染所需的上下文关联。
     */
    private function resolveConversation(?string $conversationId): ?Conversation
    {
        if ($conversationId === null) {
            return null;
        }

        return Conversation::query()

            ->with('contact')
            ->find($conversationId);
    }
}
