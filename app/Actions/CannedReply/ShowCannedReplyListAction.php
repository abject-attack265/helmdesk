<?php

namespace App\Actions\CannedReply;

use App\Data\CannedReply\CannedReplyTokenOptionData;
use App\Data\CannedReply\ListCannedReplyItemData;
use App\Data\CannedReply\ShowCannedReplyListPagePropsData;
use App\Data\CurrentUserContextData;
use App\Models\CannedReply;
use App\Models\User;
use App\Services\CannedReply\CannedReplyVariableResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 展示快捷回复模版列表。
 * 当前用户可见的模版 = 自己的个人模版 + 应用共享模版；
 * visibility 参数支持过滤"全部 / 共享 / 个人"。
 */
class ShowCannedReplyListAction
{
    use AsAction;

    public const string VISIBILITY_ALL = 'all';

    public const string VISIBILITY_APP = 'app';

    public const string VISIBILITY_PERSONAL = 'personal';

    /**
     * 注入快捷回复变量解析器。
     */
    public function __construct(
        private readonly CannedReplyVariableResolver $resolver,
    ) {}

    /**
     * 组装列表页面 props。
     */
    public function handle(User $user, string $visibility = self::VISIBILITY_ALL): ShowCannedReplyListPagePropsData
    {
        $visibility = $this->normalizeVisibility($visibility);

        $query = CannedReply::query()

            ->with('owner')
            ->where(function (Builder $scope) use ($user): void {
                $scope->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            });

        $this->applyVisibilityScope($query, $user, $visibility);

        $replies = $query
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('last_used_at IS NULL')
            ->orderByDesc('last_used_at')
            ->orderByDesc('usage_count')
            ->orderBy('name')
            ->get();

        $items = $replies->map(fn (CannedReply $reply) => ListCannedReplyItemData::fromModel(
            $reply,
            canEdit: Gate::forUser($user)->allows('update', $reply),
            canDelete: Gate::forUser($user)->allows('delete', $reply),
        ))->all();

        return new ShowCannedReplyListPagePropsData(
            canned_reply_list: $items,
            current_visibility: $visibility,
            available_tokens: array_map(
                static fn (array $token) => CannedReplyTokenOptionData::fromArray($token),
                $this->resolver->availableTokens(),
            ),
        );
    }

    /**
     * Inertia 入口：解析请求参数并渲染列表页。
     */
    public function asController(Request $request): Response
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $user = User::query()->findOrFail($ctx->user_id);

        $visibility = $request->query('visibility');
        $props = $this->handle(
            $user,
            is_string($visibility) ? $visibility : self::VISIBILITY_ALL,
        );

        return Inertia::render('cannedReplies/Index', $props->toArray());
    }

    /**
     * 把任意输入归一化到允许的 visibility 值。
     */
    private function normalizeVisibility(string $value): string
    {
        return match ($value) {
            self::VISIBILITY_APP, self::VISIBILITY_PERSONAL => $value,
            default => self::VISIBILITY_ALL,
        };
    }

    /**
     * 在外层"个人 + 共享"基础上叠加更细粒度的归属筛选。
     */
    private function applyVisibilityScope(Builder $query, User $user, string $visibility): void
    {
        match ($visibility) {
            self::VISIBILITY_APP => $query->whereNull('user_id'),
            self::VISIBILITY_PERSONAL => $query->where('user_id', $user->id),
            default => null,
        };
    }
}
