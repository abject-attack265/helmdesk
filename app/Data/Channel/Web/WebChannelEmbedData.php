<?php

namespace App\Data\Channel\Web;

use App\Models\Channel;
use Spatie\LaravelData\Data;

/**
 * 网站渠道嵌入代码数据。
 * 显示在渠道详情的组件配置页，前端把 script 地址和初始化参数展示给管理员复制。
 */
class WebChannelEmbedData extends Data
{
    public function __construct(
        public string $standalone_url,
        public string $widget_snippet,
    ) {}

    /**
     * 从渠道生成独立页地址和小部件安装代码，主机地址取 config('app.url')。
     */
    public static function fromChannel(Channel $channel): self
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        return new self(
            standalone_url: $appUrl.'/ch/'.$channel->code,
            widget_snippet: "<script async src=\"{$appUrl}/embed/widget.js\" data-channel-code=\"{$channel->code}\"></script>",
        );
    }
}
