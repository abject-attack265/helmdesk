<?php

namespace App\Actions\Teammate;

use App\Enums\UserPermission;
use App\Models\Membership;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class RemoveTeammateAction
{
    use AsAction;

    public function handle(User $actor, string $id): void
    {
        Gate::forUser($actor)->authorize('user.permission', UserPermission::UsersDelete);

        $settings = app(GeneralSettings::class);
        $targetUser = User::query()->whereKey($id)->whereHas('membership')->firstOrFail();

        if ((string) $actor->id === (string) $targetUser->id) {
            throw ValidationException::withMessages([
                'user_id' => __('user.cannot_delete_self'),
            ]);
        }

        if (filled($settings->owner_id) && (string) $settings->owner_id === (string) $targetUser->id) {
            throw ValidationException::withMessages([
                'user_id' => __('app.cannot_remove_owner'),
            ]);
        }

        Membership::query()->whereKey($targetUser->id)->delete();
    }

    public function asController(Request $request, string $id)
    {
        $this->handle($request->user(), $id);

        return back();
    }
}
