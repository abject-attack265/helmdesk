<?php

namespace App\Actions\Teammate;

use App\Actions\Attachment\AttachUploadedAttachmentsAction;
use App\Actions\Attachment\DeleteAttachmentAction;
use App\Data\Teammate\FormUpdateTeammateData;
use App\Enums\AttachmentPurpose;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 更新客服账号资料、登录信息和权限。
 */
class UpdateTeammateAction
{
    use AsAction;

    /**
     * 按当前用户的对象级权限更新目标成员。
     */
    public function handle(User $actor, string $userId, FormUpdateTeammateData $data): void
    {
        $targetUser = User::query()->whereHas('membership')->whereKey($userId)->firstOrFail();
        $authorization = Gate::forUser($actor)->inspect('users.updateMember', $targetUser);

        if ($authorization->denied()) {
            Log::warning('成员资料更新被权限规则拒绝。', [
                'actor_user_id' => $actor->id,
                'target_user_id' => $targetUser->id,
            ]);
        }

        $authorization->authorize();

        $actorIsOwner = Gate::forUser($actor)->allows('app.owner');
        $restrictedFields = [];

        if (! $actorIsOwner && $data->email !== $targetUser->email) {
            $restrictedFields[] = 'email';
        }

        if (! $actorIsOwner && filled($data->password)) {
            $restrictedFields[] = 'password';
        }

        if (! $actorIsOwner && $data->permissions !== []) {
            $restrictedFields[] = 'permissions';
        }

        if ($restrictedFields !== []) {
            Log::warning('成员安全配置更新被权限规则拒绝。', [
                'actor_user_id' => $actor->id,
                'target_user_id' => $targetUser->id,
                'fields' => $restrictedFields,
            ]);

            throw ValidationException::withMessages(
                array_fill_keys($restrictedFields, __('auth.unauthorized')),
            );
        }

        $securityChanges = [];

        if ($actorIsOwner && $data->email !== $targetUser->email) {
            $securityChanges[] = 'email';
        }

        if ($actorIsOwner && filled($data->password)) {
            $securityChanges[] = 'password';
        }

        if ($actorIsOwner && $data->permissions !== ($targetUser->permissions ?? [])) {
            $securityChanges[] = 'permissions';
        }

        DB::transaction(function () use ($actor, $actorIsOwner, $targetUser, $data): void {
            $nextNickname = filled($data->nickname) ? $data->nickname : null;
            $originalAvatar = $targetUser->avatarAttachment()->first();
            $profileUpdate = [
                'name' => $data->name,
                'locale' => $data->locale,
            ];

            if ($actorIsOwner) {
                $profileUpdate['email'] = $data->email;
                $profileUpdate['permissions'] = $data->permissions;
            }

            $targetUser->update($profileUpdate);

            if ($actorIsOwner && filled($data->password)) {
                $targetUser->update(['password' => $data->password]);
            }

            $targetUser->refresh();
            $this->syncUploadedAvatar($actor, $targetUser, $originalAvatar, $data->avatar_id);

            $targetUser->membership()->update(['nickname' => $nextNickname]);
        });

        if ($securityChanges !== []) {
            Log::info('成员安全配置已更新。', [
                'actor_user_id' => $actor->id,
                'target_user_id' => $targetUser->id,
                'fields' => $securityChanges,
            ]);
        }
    }

    /**
     * 同步成员头像附件绑定，并删除本次更新淘汰的附件。
     */
    private function syncUploadedAvatar(User $actor, User $user, ?Attachment $originalAvatar, ?string $nextAttachmentId): void
    {
        if (! filled($nextAttachmentId)) {
            return;
        }

        if ($originalAvatar !== null && (string) $originalAvatar->id === $nextAttachmentId) {
            return;
        }

        $attachment = AttachUploadedAttachmentsAction::run(
            $user,
            $nextAttachmentId,
            actor: $actor,
            allowedPurposes: [AttachmentPurpose::Avatar],
        );

        if ($attachment instanceof Attachment) {
            $user->update(['avatar' => $attachment->full_url]);
        }

        if ($originalAvatar !== null) {
            DeleteAttachmentAction::run($originalAvatar);
        }
    }

    /**
     * 将成员编辑表单转换为 Data 并执行更新。
     */
    public function asController(Request $request, string $id)
    {
        $data = FormUpdateTeammateData::from($request);
        $this->handle($request->user(), $id, $data);

        return redirect()->route('app.manage.teammates.index');
    }
}
