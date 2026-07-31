<?php

namespace App\Actions\User;

use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 展示个人语言和时区设置页面。
 */
class ShowLanguageSettingsPageAction
{
    use AsAction;

    public function handle(): string
    {
        return 'settings/Language';
    }

    public function asController(): Response
    {
        return Inertia::render($this->handle());
    }
}
