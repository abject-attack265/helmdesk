<?php

namespace App\Actions\Inbox;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 进入后台收件箱。
 */
class RedirectLastInboxAction
{
    use AsAction;

    /**
     * 跳到固定后台收件箱。
     */
    public function handle(Request $request): RedirectResponse
    {
        return redirect()->route('app.inbox.show');
    }

    /**
     * 处理默认收件箱入口请求。
     */
    public function asController(Request $request): RedirectResponse
    {
        return $this->handle($request);
    }
}
