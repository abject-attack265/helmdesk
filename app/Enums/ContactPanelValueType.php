<?php

namespace App\Enums;

/**
 * 联系人面板 key-value 行的值渲染提示，纯前端渲染提示。
 *
 * 告诉通用渲染器该 value 字符串如何呈现：Text 纯文本、Date 走 formatDateTime、
 * Money 金额样式、Link 渲染为可点链接、Badge 渲染为中性徽标。
 * 仅作渲染分发，不需要展示文案，故为纯 backed enum、不实现 LabeledEnum。
 */
enum ContactPanelValueType: string
{
    case Text = 'text';
    case Date = 'date';
    case Money = 'money';
    case Link = 'link';
    case Badge = 'badge';
}
