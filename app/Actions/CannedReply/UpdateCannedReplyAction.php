<?php

namespace App\Actions\CannedReply;

use App\Data\CannedReply\FormUpdateCannedReplyData;
use App\Data\CurrentUserContextData;
use App\Exceptions\BusinessException;
use App\Models\CannedReply;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 更新快捷回复内容与个人、应用共享归属。
 */
class UpdateCannedReplyAction
{
    use AsAction;

    /**
     * 校验权限和快捷键唯一性后更新内容与归属。
     */
    public function handle(User $user, string $cannedReplyId, FormUpdateCannedReplyData $data): CannedReply
    {
        return DB::transaction(function () use ($user, $cannedReplyId, $data): CannedReply {
            $reply = CannedReply::query()->find($cannedReplyId);

            if ($reply === null) {
                throw new NotFoundHttpException;
            }

            if (! Gate::forUser($user)->allows('update', $reply)) {
                throw new BusinessException(__('canned_reply.errors.forbidden'));
            }

            $wasShared = $reply->user_id === null;
            $willBeShared = ! $data->is_personal;
            $targetUserId = $willBeShared ? null : ($wasShared ? $user->id : $reply->user_id);

            $shortcut = $this->normalizeShortcut($data->shortcut);
            $this->guardShortcutUnique($reply, $shortcut, $targetUserId);

            $reply->fill([
                'name' => trim($data->name),
                'shortcut' => $shortcut,
                'content' => $data->content,
                'user_id' => $targetUserId,
                'updated_by_user_id' => $user->id,
            ])->save();

            return $reply->refresh();
        });
    }

    /**
     * 保存表单并返回快捷回复列表。
     */
    public function asController(Request $request, string $cannedReply): RedirectResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $user = User::query()->findOrFail($ctx->user_id);

        $this->handle($user, $cannedReply, FormUpdateCannedReplyData::from($request));

        return redirect()->route('app.canned-replies.index');
    }

    /**
     * 将快捷键转为小写，并将空值归一为 null。
     */
    private function normalizeShortcut(?string $shortcut): ?string
    {
        if ($shortcut === null) {
            return null;
        }

        $trimmed = strtolower(trim($shortcut));

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * 要求快捷键在目标归属范围内排除当前模板后唯一。
     */
    private function guardShortcutUnique(CannedReply $current, ?string $shortcut, ?string $targetUserId): void
    {
        if ($shortcut === null) {
            return;
        }

        $query = CannedReply::query()
            ->where('shortcut', $shortcut)
            ->whereKeyNot($current->id);

        if ($targetUserId === null) {
            $query->whereNull('user_id');
        } else {
            $query->where('user_id', $targetUserId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'shortcut' => __('canned_reply.errors.shortcut_exists'),
            ]);
        }
    }
}
