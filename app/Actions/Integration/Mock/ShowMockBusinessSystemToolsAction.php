<?php

namespace App\Actions\Integration\Mock;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * mock 业务系统的工具清单端点：GET /mock-business-system/helmdesk/tools。
 *
 * 返回写死的两个工具定义（query_order / query_customer），用于演示 BusinessSystemToolProvider 的同步。
 * 仅 local / testing 可达；缺少正确共享密钥返回 401。
 */
class ShowMockBusinessSystemToolsAction
{
    use AsAction;

    /**
     * 输出工具清单（含 input_schema），供集成同步拉取。
     */
    public function asController(Request $request): JsonResponse
    {
        MockBusinessSystem::assertEnvironment();

        if (! MockBusinessSystem::isAuthorized($request)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        return response()->json([
            'tools' => MockBusinessSystem::toolDefinitions(),
        ]);
    }
}
