<?php

namespace App\Actions\Integration\Mock;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * mock 业务系统的工具执行端点：POST /mock-business-system/helmdesk/tools/{name}/invoke。
 *
 * 按工具名返回写死的假业务数据（订单 / 客户），演示运行时 AI 调用闭环。
 * 仅 local / testing 可达；缺少正确共享密钥返回 401；未知工具名 404。
 */
class InvokeMockBusinessSystemToolAction
{
    use AsAction;

    /**
     * 解析工具名与参数，分发到 MockBusinessSystem 共享假数据；未知工具名返回 404。
     */
    public function asController(Request $request, string $name): JsonResponse
    {
        MockBusinessSystem::assertEnvironment();

        if (! MockBusinessSystem::isAuthorized($request)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $known = array_column(MockBusinessSystem::toolDefinitions(), 'name');
        if (! in_array($name, $known, true)) {
            throw new NotFoundHttpException("Unknown mock tool [{$name}].");
        }

        $arguments = is_array($request->input('arguments')) ? $request->input('arguments') : [];

        return response()->json(MockBusinessSystem::invokeTool($name, $arguments));
    }
}
