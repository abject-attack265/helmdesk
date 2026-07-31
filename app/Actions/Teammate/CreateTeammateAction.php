<?php

namespace App\Actions\Teammate;

use App\Actions\Attachment\AttachUploadedAttachmentsAction;
use App\Data\Teammate\FormCreateTeammateData;
use App\Enums\AttachmentPurpose;
use App\Enums\UserOnlineStatus;
use App\Enums\UserPermission;
use App\Models\Attachment;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateTeammateAction
{
    use AsAction;

    public function handle(User $actor, FormCreateTeammateData $data): User
    {
        Gate::forUser($actor)->authorize('user.permission', UserPermission::UsersCreate);
        $permissions = Gate::forUser($actor)->allows('app.owner') ? $data->permissions : [];

        return DB::transaction(function () use ($actor, $data, $permissions): User {
            $user = User::query()->create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
                'avatar' => null,
                'permissions' => $permissions,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            Membership::query()->create([
                'user_id' => $user->id,
                'nickname' => filled($data->nickname) ? $data->nickname : null,
                'online_status' => UserOnlineStatus::Online,
            ]);

            $this->bindUploadedAvatar($actor, $user, $data->avatar_id);

            return $user;
        });
    }

    public function asController(Request $request): RedirectResponse
    {
        $this->handle($request->user(), FormCreateTeammateData::from($request));

        return redirect()->route('app.manage.teammates.index');
    }

    private function bindUploadedAvatar(User $actor, User $user, ?string $attachmentId): void
    {
        if (! filled($attachmentId)) {
            return;
        }

        $attachment = AttachUploadedAttachmentsAction::run(
            $user,
            $attachmentId,
            actor: $actor,
            allowedPurposes: [AttachmentPurpose::Avatar],
        );

        if ($attachment instanceof Attachment) {
            $user->update(['avatar' => $attachment->full_url]);
        }
    }
}
