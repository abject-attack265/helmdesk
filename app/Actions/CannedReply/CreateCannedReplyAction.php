<?php

namespace App\Actions\CannedReply;

use App\Data\CannedReply\FormCreateCannedReplyData;
use App\Data\CurrentUserContextData;
use App\Models\CannedReply;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 创建个人或应用共享的快捷回复模板。
 */
class CreateCannedReplyAction
{
    use AsAction;

    /**
     * 校验快捷键唯一性后保存快捷回复。
     */
    public function handle(User $user, FormCreateCannedReplyData $data): CannedReply
    {
        return DB::transaction(function () use ($user, $data): CannedReply {
            $isPersonal = $data->is_personal;
            $shortcut = $this->normalizeShortcut($data->shortcut);
            $userId = $isPersonal ? (string) $user->id : null;

            $this->guardShortcutUnique($userId, $shortcut);

            return CannedReply::query()->create([
                'user_id' => $userId,
                'name' => trim($data->name),
                'shortcut' => $shortcut,
                'content' => $data->content,
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
            ]);
        });
    }

    /**
     * 保存表单并返回快捷回复列表。
     */
    public function asController(Request $request): RedirectResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $user = User::query()->findOrFail($ctx->user_id);

        $this->handle($user, FormCreateCannedReplyData::from($request));

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
     * 要求快捷键在应用共享或个人归属范围内唯一。
     */
    private function guardShortcutUnique(?string $userId, ?string $shortcut): void
    {
        if ($shortcut === null) {
            return;
        }

        $query = CannedReply::query()->where('shortcut', $shortcut);

        if ($userId === null) {
            $query->whereNull('user_id');
        } else {
            $query->where('user_id', $userId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'shortcut' => __('canned_reply.errors.shortcut_exists'),
            ]);
        }
    }
}
