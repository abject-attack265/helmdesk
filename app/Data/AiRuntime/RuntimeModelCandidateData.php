<?php

namespace App\Data\AiRuntime;

use App\Enums\AiProviderProtocol;
use App\Models\AiModel;
use Spatie\LaravelData\Data;

/**
 * 模型调用使用的协议、凭据、模型标识与媒体输入能力。
 *
 * 接待运行时按顺序传递候选，队列任务可直接序列化该数据。
 */
class RuntimeModelCandidateData extends Data
{
    /**
     * 承载构造模型供应商和消息内容所需的运行时字段。
     *
     * @param  array<string, string>  $credentials  凭据 map（`key` = API Key，`base_uri` = 可选 base URL 覆盖）
     * @param  string  $brand  品牌目录标识（如 minimax / deepseek），决定是否需要品牌专属 provider 适配
     */
    public function __construct(
        public AiProviderProtocol $protocol,
        public array $credentials,
        public string $model_id,
        public bool $supports_image_input,
        public bool $supports_video_input,
        public string $provider_name = '',
        public string $model_name = '',
        public string $ai_model_id = '',
        public string $brand = '',
    ) {}

    /**
     * 从 AiModel（含 provider 关联）投射运行时候选，凭据归一化为非空字符串 map。
     */
    public static function fromModel(AiModel $model): self
    {
        $provider = $model->provider;

        return new self(
            protocol: $provider->protocol,
            credentials: self::normalizeCredentials($provider->credentials),
            model_id: (string) $model->model_id,
            provider_name: (string) $provider->name,
            model_name: (string) $model->name,
            ai_model_id: (string) $model->id,
            brand: (string) $provider->brand,
            supports_image_input: $model->supports_image_input,
            supports_video_input: $model->supports_video_input,
        );
    }

    /**
     * 凭据归一化：丢弃非标量值，trim 后跳过空字符串。
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    public static function normalizeCredentials(array $credentials): array
    {
        $normalized = [];
        foreach ($credentials as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }
            $trimmed = trim((string) $value);
            if ($trimmed === '') {
                continue;
            }
            $normalized[$key] = $trimmed;
        }

        return $normalized;
    }
}
