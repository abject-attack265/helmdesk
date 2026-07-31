<?php

namespace App\Services\Reception;

use App\Data\Reception\Runtime\ReceptionToolEventContextData;
use Illuminate\Support\Facades\Log;
use NeuronAI\Tools\Tool;
use Throwable;

/**
 * 处理接待工具异常并生成标准失败结果。
 */
class ReceptionToolErrorHandler
{
    /**
     * 创建工具异常处理器。
     */
    public function __construct(
        private readonly ?ReceptionToolEventContextData $context,
    ) {}

    /**
     * 可展示工具返回标准失败结果，出口工具保留异常传播。
     */
    public function __invoke(Throwable $exception, Tool $tool): string
    {
        if ($this->context === null) {
            $this->rethrowWithResult($exception, $tool);
        }

        $definition = $this->context->definitionFor($tool->getName());
        if ($definition === null) {
            $this->rethrowWithResult($exception, $tool);
        }

        Log::warning('[reception] 接待工具执行失败', [
            'conversation_id' => $this->context->conversation_id,
            'turn_id' => $this->context->turn_id,
            'tool' => $tool->getName(),
            'source_type' => $definition->source_type,
            'source_names' => $definition->source_names,
            'call_id' => $tool->getCallId(),
            'exception_class' => $exception::class,
            'error' => $exception->getMessage(),
        ]);

        return json_encode(['error' => 'tool_execution_failed'], JSON_THROW_ON_ERROR);
    }

    /**
     * 写入标准失败结果并重新抛出工具异常。
     */
    private function rethrowWithResult(Throwable $exception, Tool $tool): never
    {
        $tool->setResult(['error' => 'tool_execution_failed']);

        throw $exception;
    }
}
