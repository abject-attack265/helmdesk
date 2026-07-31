<?php

namespace App\Enums;

/**
 * 联系人面板「积木块」的渲染类型，纯前端渲染提示。
 *
 * provider 把自家业务数据映射成固定 UI 积木，前端通用渲染器只认 kind、不感知具体平台：
 * KeyValue 渲染为 label-value 行（用 rows），List 渲染为条目列表（用 items）。
 * 仅作渲染分发，不需要展示文案，故为纯 backed enum、不实现 LabeledEnum。
 */
enum ContactPanelBlockKind: string
{
    case KeyValue = 'key_value';
    case List = 'list';
}
