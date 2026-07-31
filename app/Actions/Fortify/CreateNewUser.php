<?php

namespace App\Actions\Fortify;

use App\Models\Membership;
use App\Models\User;
use App\Services\LocalePreference;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * 处理 Fortify 注册流程，并将用户加入唯一系统。
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * 校验注册输入并创建用户。
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $settings = app(GeneralSettings::class);
        abort_unless(blank($settings->owner_id) || $settings->registration_enabled, 403);

        if (is_string($input['name'] ?? null)) {
            $input['name'] = trim($input['name']);
        }

        $input = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'locale' => ['nullable', 'string'],
            'timezone' => ['nullable', 'string', 'timezone'],
        ])->validate();

        $locale = LocalePreference::normalizeFrontend(
            $input['locale'] ?? LocalePreference::preferredBrowserLocale(request())
        );

        return DB::transaction(function () use ($input, $locale, $settings) {
            $isFirstUser = blank($settings->owner_id);

            $user = User::query()->create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'locale' => $locale,
                'timezone' => $input['timezone'] ?? null,
            ]);

            if ($isFirstUser) {
                // 首个注册用户作为系统所有者，注册时直接标记邮箱已验证。
                $user->forceFill(['email_verified_at' => now()])->save();
                $settings->fill([
                    'owner_id' => $user->id,
                ])->save();
            }

            Membership::query()->create([
                'user_id' => $user->id,
            ]);

            Log::info('user.joined_app', [
                'user_id' => (string) $user->id,
                'source' => $isFirstUser ? 'registration_owner' : 'registration',
            ]);

            return $user;
        });
    }
}
