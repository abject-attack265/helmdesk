<?php

namespace App\Actions\CannedReply;

use App\Data\CurrentUserContextData;
use App\Exceptions\BusinessException;
use App\Models\CannedReply;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 软删除快捷回复模版。
 */
class DeleteCannedReplyAction
{
    use AsAction;

    /**
     * 软删模版；找不到 -> 404；归属不符 -> 业务异常 toast。
     */
    public function handle(User $user, string $cannedReplyId): void
    {
        $reply = CannedReply::query()

            ->find($cannedReplyId);

        if ($reply === null) {
            throw new NotFoundHttpException;
        }

        if (! Gate::forUser($user)->allows('delete', $reply)) {
            throw new BusinessException(__('canned_reply.errors.forbidden'));
        }

        $reply->delete();
    }

    /**
     * Inertia 入口。
     */
    public function asController(Request $request, string $cannedReply): RedirectResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $user = User::query()->findOrFail($ctx->user_id);

        $this->handle($user, $cannedReply);

        return redirect()->route('app.canned-replies.index');
    }
}
