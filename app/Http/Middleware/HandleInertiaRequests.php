<?php

namespace App\Http\Middleware;

use App\Services\LocalePreference;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * 为 Inertia 页面注入全局共享数据。
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * 首次访问页面时加载的根模板。
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * 返回当前前端资源版本。
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * 定义默认共享给前端的全局 props。
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'app_locale' => LocalePreference::frontendFromLaravel(app()->getLocale()),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => $request->cookie('sidebar_state', 'true') !== 'false',
            // 前端用于拼接应用分享/预览链接
            'app_url' => rtrim((string) config('app.url'), '/'),
        ];
    }
}
