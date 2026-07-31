<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 知识库分类，决定创建后承载的内容形态（普通文档库 / 问答库 / 公众号库）。
 * 用于知识库创建入口下拉、列表行的类别 Badge 以及列表数据的类别显示。
 */
enum KnowledgeBaseCategory: string implements LabeledEnum
{
    case Standard = 'standard';
    case Qa = 'qa';
    case WechatPublic = 'wechat_public';

    public function label(): string
    {
        return match ($this) {
            self::Standard => __('knowledge_base.categories.standard'),
            self::Qa => __('knowledge_base.categories.qa'),
            self::WechatPublic => __('knowledge_base.categories.wechat_public'),
        };
    }

    /**
     * 返回分类在创建入口下拉中的辅助说明，帮助用户判断该选哪种知识库。
     */
    public function description(): string
    {
        return match ($this) {
            self::Standard => __('knowledge_base.categories.helper.standard'),
            self::Qa => __('knowledge_base.categories.helper.qa'),
            self::WechatPublic => __('knowledge_base.categories.helper.wechat_public'),
        };
    }

    /**
     * 返回当前可在创建入口下拉中选择的分类；尚未上线的分类不在其中。
     *
     * @return list<self>
     */
    public static function creatableCases(): array
    {
        return [self::Standard, self::Qa];
    }
}
