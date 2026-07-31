<?php

namespace App\Actions\Channel\Telegram;

use App\Exceptions\TelegramApiException;
use App\Services\Telegram\TelegramBotApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 用 getMe 检测 Bot Token 的有效性并返回机器人信息（详情页「检测」按钮）。
 *
 * 只做只读检测、不落库，供用户在保存前确认 Token 对应的机器人身份。
 */
class CheckTelegramBotTokenAction
{
    use AsAction;

    /**
     * 注入 Telegram Bot API 客户端。
     */
    public function __construct(
        private readonly TelegramBotApi $api,
    ) {}

    /**
     * 调 getMe 检测 Token；无效时返回失败标记与提示，不抛业务异常（检测失败是预期内结果）。
     *
     * @return array{success: bool, bot_username: ?string, message: ?string}
     */
    public function handle(string $botToken): array
    {
        try {
            $me = $this->api->getMe($botToken);
        } catch (TelegramApiException $e) {
            return [
                'success' => false,
                'bot_username' => null,
                'message' => __('channel.telegram.errors.invalid_bot_token'),
            ];
        }

        return [
            'success' => true,
            'bot_username' => is_string($me['username'] ?? null) ? $me['username'] : null,
            'message' => null,
        ];
    }

    /**
     * 接收检测请求：入口校验 Token 格式后执行检测并以 JSON 返回。
     */
    public function asController(Request $request): JsonResponse
    {

        $validated = Validator::make($request->all(), [
            'bot_token' => ['required', 'string', 'max:200', 'regex:/^\d+:[A-Za-z0-9_-]{20,}$/'],
        ])->validate();

        return response()->json($this->handle($validated['bot_token']));
    }
}
