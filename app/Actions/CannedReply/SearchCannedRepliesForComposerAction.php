<?php

namespace App\Actions\CannedReply;

use App\Data\CannedReply\CannedReplyComposerItemData;
use App\Data\CurrentUserContextData;
use App\Models\CannedReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 搜索收件箱回复输入框中的快捷回复候选项。
 */
class SearchCannedRepliesForComposerAction
{
    use AsAction;

    private const int DEFAULT_LIMIT = 8;

    private const int MAX_LIMIT = 20;

    private const int MAX_QUERY_LENGTH = 64;

    /**
     * 搜索当前用户可见的快捷回复模版（个人 + 应用共享）。
     *
     * @return array<int, CannedReplyComposerItemData>
     */
    public function handle(
        User $user,
        string $query,
        int $limit = self::DEFAULT_LIMIT,
    ): array {
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $normalizedQuery = mb_substr(trim($query), 0, self::MAX_QUERY_LENGTH);

        $builder = CannedReply::query()

            ->where(function (Builder $scope) use ($user): void {
                $scope->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            });

        if ($normalizedQuery !== '') {
            $like = '%'.$normalizedQuery.'%';
            $shortcutPrefix = $normalizedQuery.'%';

            $builder->where(function (Builder $scope) use ($like, $shortcutPrefix): void {
                $scope
                    ->where('shortcut', 'like', $shortcutPrefix)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('content', 'like', $like);
            });
        }

        $replies = $builder
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('last_used_at IS NULL')
            ->orderByDesc('last_used_at')
            ->orderByDesc('usage_count')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $replies->map(
            fn (CannedReply $reply) => CannedReplyComposerItemData::fromModel($reply),
        )->all();
    }

    /**
     * XHR 入口：返回 JSON 数组给前端 composer。
     */
    public function asController(Request $request): JsonResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $user = User::query()->findOrFail($ctx->user_id);

        $query = $request->query('q');
        $rawLimit = $request->query('limit');

        $items = $this->handle(
            user: $user,
            query: is_string($query) ? $query : '',
            limit: is_numeric($rawLimit) ? (int) $rawLimit : self::DEFAULT_LIMIT,
        );

        return response()->json([
            'items' => array_map(static fn (CannedReplyComposerItemData $item) => $item->toArray(), $items),
        ]);
    }
}
