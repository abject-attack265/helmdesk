<?php

namespace App\Data\Channel\Web;

use App\Enums\WebChannelParamTarget;
use App\Enums\WebChannelParamTrust;
use App\Enums\WebChannelParamWriteMode;
use Spatie\LaravelData\Data;

/**
 * 单条网站渠道自定义参数映射配置。
 *
 * 一条映射描述"访客 URL/widget query 上的某个参数 → 联系人字段/属性/标签"的转换关系。
 * - param_name : URL 参数名，例如 utm_source、external_id
 * - target     : WebChannelParamTarget 定义的写入目标
 * - target_key : 当 target=Attribute 时为自定义属性 key；当 target=Tag 时为标签名称模板，
 *                模板里允许 {value} 占位，会被替换为参数实际值
 * - trust      : 信任级别（SignedOnly 强制要求 user_token 校验通过；Always 任意访客均可）
 * - write_mode : 写入模式（OnlyIfEmpty / Overwrite）
 */
class WebChannelQueryParamMappingData extends Data
{
    public function __construct(
        public string $param_name,
        public WebChannelParamTarget $target,
        public ?string $target_key = null,
        public WebChannelParamTrust $trust = WebChannelParamTrust::SignedOnly,
        public WebChannelParamWriteMode $write_mode = WebChannelParamWriteMode::OnlyIfEmpty,
    ) {}
}
