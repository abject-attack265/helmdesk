<?php

namespace App\Services\AiRuntime;

use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Models\AiProvider;
use App\Services\Reception\ReceptionProviderFactory;
use Illuminate\Support\Facades\Log;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\UserMessage;
use Throwable;

/**
 * 用 NeuronAI 直接对供应商凭据做一次最小化连通性探测。
 *
 * 挑一个启用中的 LLM 模型，用待测凭据发一条极短消息，按是否抛异常 / 是否有返回判定连接是否可用。
 */
class AiProviderConnectionChecker
{
    /**
     * 注入模型供应商构造器。
     */
    public function __construct(
        private readonly ReceptionProviderFactory $providers,
    ) {}

    /**
     * 探测供应商连接，返回协议是否受支持、是否连通及可读消息。
     *
     * @param  array<string, mixed>  $credentials  已合并覆盖项的待测凭据
     * @return array{supported: bool, success: bool, message: string}
     */
    public function check(AiProvider $provider, array $credentials): array
    {
        $model = $provider->models()
            ->where('type', 'llm')
            ->where('is_active', true)
            ->orderBy('model_id')
            ->first();

        if ($model === null) {
            return ['supported' => true, 'success' => false, 'message' => __('ai.runtime.check.no_active_llm')];
        }

        try {
            $aiProvider = $this->providers->make(new RuntimeModelCandidateData(
                protocol: $provider->protocol,
                credentials: RuntimeModelCandidateData::normalizeCredentials($credentials),
                model_id: (string) $model->model_id,
                supports_image_input: $model->supports_image_input,
                supports_video_input: $model->supports_video_input,
            ));

            $reply = Agent::make()
                ->setAiProvider($aiProvider)
                ->chat(new UserMessage('ping'))
                ->getMessage();

            $content = trim((string) $reply->getContent());

            return [
                'supported' => true,
                'success' => true,
                'message' => $content !== '' ? __('ai.check_success') : __('ai.check_empty_response'),
            ];
        } catch (Throwable $exception) {
            Log::warning('AI 供应商连通性探测失败。', [
                'provider_id' => $provider->id,
                'message' => $this->sanitize($exception->getMessage()),
            ]);

            return [
                'supported' => true,
                'success' => false,
                'message' => __('ai.runtime.check.runtime_error', ['error' => $this->sanitize($exception->getMessage())]),
            ];
        }
    }

    /**
     * 脱敏并裁短上游错误，避免凭据进入返回消息。
     */
    private function sanitize(string $message): string
    {
        $patterns = [
            '/sk-[A-Za-z0-9_\-]{16,}/i' => '[redacted-key]',
            '/Bearer\s+[A-Za-z0-9._\-]+/i' => 'Bearer [redacted]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $message = (string) preg_replace($pattern, $replacement, $message);
        }

        return mb_substr($message, 0, 200);
    }
}
