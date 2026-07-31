<?php

namespace App\Actions\User;

use App\Data\User\FormUpdateLanguageSettingsData;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 更新当前用户的语言和时区偏好。
 */
class UpdateLanguageSettingsAction
{
    use AsAction;

    public function handle(User $user, FormUpdateLanguageSettingsData $data): void
    {
        $user->forceFill([
            'locale' => $data->locale,
            'timezone' => $data->timezone,
        ])->save();
    }

    public function asController(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->handle($user, FormUpdateLanguageSettingsData::from($request));

        return back();
    }
}
