<?php

namespace App\Contracts\Integration;

use App\Data\Integration\Panel\ContactPanelData;
use App\Models\Contact;
use App\Models\Integration;

/**
 * 「联系人业务数据面板」能力契约（Integration 域的可选 capability）。
 *
 * 业务层据此为人工接待页右侧边栏拉取某集成对某联系人的业务数据面板：provider 把自家数据
 * （订单 / 客户 / 工单等）映射成统一的展示描述符 ContactPanelData，前端通用渲染、不感知具体平台。
 * 并非所有 provider 都实现本接口（如 MCP 无此概念）；只有 instanceof ProvidesContactPanel 的
 * provider 才参与面板装配。返回 null 表示该集成对此联系人无数据，调用方据此整体略过。
 */
interface ProvidesContactPanel
{
    /**
     * 为给定集成 + 联系人构建业务数据面板；无数据 / 不可用时返回 null（不抛异常打断接待页）。
     */
    public function buildContactPanel(Integration $integration, Contact $contact): ?ContactPanelData;
}
