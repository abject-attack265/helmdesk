<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\HiddenPasswordResetLinkRequestResponse;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\RegisterResponse;
use App\Http\Responses\TwoFactorLoginResponse;
use App\Http\Responses\VerifyEmailResponse;
use App\Settings\GeneralSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

/**
 * 配置 Fortify 认证流程、页面和限流策略。
 */
class FortifyServiceProvider extends ServiceProvider
{
    /**
     * 注册 Fortify 响应契约实现。
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(FailedPasswordResetLinkRequestResponseContract::class, HiddenPasswordResetLinkRequestResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, TwoFactorLoginResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
        $this->app->singleton(VerifyEmailResponseContract::class, VerifyEmailResponse::class);
    }

    /**
     * 启动 Fortify 的动作、页面和限流配置。
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * 配置 Fortify 使用的业务动作。
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * 配置 Fortify 认证相关页面。
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => $this->registrationIsAllowed(),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(function () {
            if (! $this->registrationIsAllowed()) {
                throw new HttpResponseException(redirect()->route('login'));
            }

            return Inertia::render('auth/Register');
        });

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * 配置认证和 AI 对话相关限流。
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('ai-chat-send', function (Request $request) {
            return Limit::perMinute(30)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('ai-chat-stop', function (Request $request) {
            return Limit::perMinute(60)->by((string) ($request->user()?->id ?? $request->ip()));
        });
    }

    /**
     * 判断当前站点是否允许开放注册。
     */
    private function registrationIsAllowed(): bool
    {
        $settings = app(GeneralSettings::class);

        return Features::enabled(Features::registration())
            && (blank($settings->owner_id) || $settings->registration_enabled);
    }
}
