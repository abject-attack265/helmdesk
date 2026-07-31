<?php

namespace App\Actions\User;

use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 展示个人密码设置页面。
 */
class ShowPasswordSettingsPageAction
{
    use AsAction;

    public function handle(): string
    {
        return 'settings/Password';
    }

    public function asController(): Response
    {
        return Inertia::render($this->handle());
    }
}
