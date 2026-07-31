<?php

namespace App\Actions\Manage;

use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 展示当前系统设置页。
 */
class GetSystemSettingsAction
{
    use AsAction;

    /**
     * 渲染当前系统设置页。
     */
    public function asController(): Response
    {
        return Inertia::render('currentApp/Index');
    }
}
