<?php

namespace App\Actions\User;

use App\Data\User\ShowProfileSettingsPagePropsData;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 展示个人资料设置页面。
 */
class ShowProfileSettingsPageAction
{
    use AsAction;

    public function handle(Request $request): ShowProfileSettingsPagePropsData
    {
        return new ShowProfileSettingsPagePropsData(
            mustVerifyEmail: $request->user() instanceof MustVerifyEmail,
            status: $request->session()->get('status'),
        );
    }

    public function asController(Request $request): Response
    {
        return Inertia::render('settings/Profile', $this->handle($request)->toArray());
    }
}
