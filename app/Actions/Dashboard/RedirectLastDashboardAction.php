<?php

namespace App\Actions\Dashboard;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 进入后台仪表板。
 */
class RedirectLastDashboardAction
{
    use AsAction;

    /**
     * 跳到固定后台仪表板。
     */
    public function handle(Request $request): RedirectResponse
    {
        return redirect()->route('app.dashboard');
    }

    /**
     * 处理默认仪表板入口请求。
     */
    public function asController(Request $request): RedirectResponse
    {
        return $this->handle($request);
    }
}
