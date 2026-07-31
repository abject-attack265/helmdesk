<?php

namespace App\Actions\Integration\Mock;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * mock 业务系统的联系人面板端点：GET /mock-business-system/helmdesk/contact-panel。
 *
 * 返回写死的统一展示描述符（一个「客户概况」key_value section + 一个「最近订单」list section），
 * 任意 email / external_id 都返回，便于演示 ProvidesContactPanel 的接待侧边栏效果。
 * 仅 local / testing 可达；缺少正确共享密钥返回 401。
 */
class ShowMockBusinessSystemContactPanelAction
{
    use AsAction;

    /**
     * 输出写死的联系人业务数据面板描述符。
     */
    public function asController(Request $request): JsonResponse
    {
        MockBusinessSystem::assertEnvironment();

        if (! MockBusinessSystem::isAuthorized($request)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        return response()->json([
            'sections' => MockBusinessSystem::contactPanelSections(),
        ]);
    }
}
