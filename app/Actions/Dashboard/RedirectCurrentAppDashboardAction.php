<?php

namespace App\Actions\Dashboard;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 把当前应用上下文重定向到对应仪表板。
 */
class RedirectCurrentAppDashboardAction
{
    use AsAction;

    /**
     * 根据当前应用上下文生成仪表板重定向响应。
     */
    public function handle(Request $request): RedirectResponse
    {
        return redirect()->route('app.dashboard');
    }

    /**
     * 处理当前应用首页入口请求。
     */
    public function asController(Request $request): RedirectResponse
    {
        return $this->handle($request);
    }
}
