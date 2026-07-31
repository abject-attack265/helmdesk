<?php

namespace App\Actions\Security;

use App\Services\Auth\SessionGuardManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * 退出应用后台 web guard 的登录状态，并清理其 Sanctum 会话残留。
 */
class LogoutWebAction
{
    use AsAction;

    public function __construct(private readonly SessionGuardManager $guards) {}

    /**
     * 登出后整页跳转到登录页。登录页是非 Inertia 入口，
     * 必须用 Inertia::location 触发整页导航。
     */
    public function asController(Request $request): Response
    {
        $this->guards->logout($request, 'web');

        return Inertia::location(route('home', absolute: false));
    }
}
