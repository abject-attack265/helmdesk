<?php

namespace App\Actions\Channel\Web\Public;

use Illuminate\Http\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 提供网站渠道小部件安装脚本（/embed/widget.js）。
 *
 * 脚本为静态内容，存放在 resources/embed/widget-loader.js，由宿主页 <script data-channel-code> 引入，
 * 负责在宿主页注入 shadow-DOM 气泡按钮与 iframe 面板。
 */
class ShowWidgetLoaderScriptAction
{
    use AsAction;

    /**
     * 返回小部件安装脚本，带短缓存与 nosniff 头。
     */
    public function asController(): Response
    {
        $script = (string) file_get_contents(resource_path('embed/widget-loader.js'));

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
