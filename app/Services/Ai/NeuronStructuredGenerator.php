<?php

namespace App\Services\Ai;

use App\Models\AiModel;
use App\Services\Ai\Usage\AiUsageContext;
use App\Services\Reception\ReceptionProviderFactory;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\UserMessage;

/**
 * 按指定 Schema 执行带用量与调用日志观测的 LLM 结构化生成。
 */
class NeuronStructuredGenerator
{
    public function __construct(
        private readonly ReceptionProviderFactory $providers,
    ) {}

    /**
     * 用指定模型按 instructions 对纯文本 userMessage 做结构化抽取，回填到 $schemaClass 实例。
     *
     * @template T of object
     *
     * @param  class-string<T>  $schemaClass  带 SchemaProperty 注解的目标类
     * @return T
     */
    public function generate(AiModel $model, string $instructions, string $userMessage, string $schemaClass, AiUsageContext $context): object
    {
        return $this->generateFromMessage($model, $instructions, new UserMessage($userMessage), $schemaClass, $context);
    }

    /**
     * 同 generate()，但接收已构建的多模态 UserMessage（文本 + 图片/视频内容块），
     * 用于需要把访客图片/视频喂给模型的结构化场景（如回复润色）。
     *
     * @template T of object
     *
     * @param  class-string<T>  $schemaClass  带 SchemaProperty 注解的目标类
     * @return T
     */
    public function generateFromMessage(AiModel $model, string $instructions, UserMessage $userMessage, string $schemaClass, AiUsageContext $context): object
    {
        $agent = Agent::make()->setAiProvider($this->providers->makeForModel($model));
        $context->attachObservers($agent);
        if (trim($instructions) !== '') {
            $agent->setInstructions($instructions);
        }

        /** @var T $result */
        $result = $agent->structured($userMessage, $schemaClass);

        return $result;
    }
}
