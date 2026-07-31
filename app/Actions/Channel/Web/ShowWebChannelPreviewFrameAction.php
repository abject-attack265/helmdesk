<?php

namespace App\Actions\Channel\Web;

use Illuminate\Contracts\View\View;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 提供网站渠道详情页右侧实时预览所嵌入的 iframe 文档。
 *
 * 这是一个不含任何渠道数据的「哑壳」：渠道外观草稿由后台页面通过同源 postMessage 注入，
 * 因此无需保存即可在隔离的 iframe 文档里实时渲染访客端外观。
 */
class ShowWebChannelPreviewFrameAction
{
    use AsAction;

    /**
     * 返回预览 iframe 的空壳 HTML（仅挂载 channel-preview 入口，等待父页面下发草稿）。
     */
    public function asController(): View
    {
        return view('channel-preview');
    }
}
