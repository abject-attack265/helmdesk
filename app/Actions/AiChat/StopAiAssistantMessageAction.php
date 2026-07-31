<?php

namespace App\Actions\AiChat;

use App\Services\AiChat\AiChatStreamSignal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * 为指定 AI 助手轮次写入流式停止信号。
 */
class StopAiAssistantMessageAction
{
    use AsAction;

    /**
     * 注入 AI 助手流式停止信号服务。
     */
    public function __construct(
        private AiChatStreamSignal $signal,
    ) {}

    /**
     * 校验轮次 ID 并写入停止标志。
     *
     * @return array{stopped: bool}
     */
    public function handle(string $roundId): array
    {
        if (! Str::isUuid($roundId)) {
            throw ValidationException::withMessages([
                'round_id' => __('validation.uuid', ['attribute' => 'round_id']),
            ]);
        }

        $this->signal->requestCancel($roundId);

        return [
            'stopped' => true,
        ];
    }

    /**
     * 处理 AI 助手停止生成请求。
     */
    public function asController(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'round_id' => ['required', 'string', 'uuid'],
        ]);

        return response()->json(
            $this->handle((string) $validated['round_id']),
            Response::HTTP_OK,
        );
    }
}
