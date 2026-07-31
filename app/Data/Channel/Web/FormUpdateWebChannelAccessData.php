<?php

namespace App\Data\Channel\Web;

use Spatie\LaravelData\Data;

/**
 * 更新网站渠道接入方式表单数据。
 * 来自 resources/js/pages/channel/web/tabs/AccessTab.vue 的接入方式表单提交，
 * 承载嵌入域名白名单与聊天链接附加 query；入口/设备与传参映射各自由对应表单维护，互不覆盖。
 */
class FormUpdateWebChannelAccessData extends Data
{
    /**
     * @param  list<string>|null  $allowed_embed_hosts
     */
    public function __construct(
        public ?array $allowed_embed_hosts = null,
        public ?string $standalone_link_query = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'allowed_embed_hosts' => ['nullable', 'array', 'max:50'],
            'allowed_embed_hosts.*' => ['string', 'max:255'],
            'standalone_link_query' => ['nullable', 'string', 'max:1024'],
        ];
    }
}
