<?php

namespace App\Actions\User;

use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 展示个人外观设置页面。
 */
class ShowAppearanceSettingsPageAction
{
    use AsAction;

    public function handle(): string
    {
        return 'settings/Appearance';
    }

    public function asController(): Response
    {
        return Inertia::render($this->handle());
    }
}
