<?php

namespace App\Services\Reception;

use App\Actions\Reception\RecordReceptionToolCalledAction;
use App\Data\Reception\Runtime\ReceptionToolEventContextData;
use Illuminate\Support\Facades\Log;
use NeuronAI\Observability\Events\ToolCalled;
use NeuronAI\Observability\ObserverInterface;
use Throwable;

/**
 * 观察接待 Agent 的工具执行结果，并记录可展示的会话事件。
 */
class ReceptionToolEventObserver implements ObserverInterface
{
    /**
     * 创建单次接待模型尝试的工具事件观察器。
     */
    public function __construct(
        private readonly ReceptionToolEventContextData $context,
    ) {}

    /**
     * 记录已登记的工具事件；出口工具和记录失败均不影响 AI 接待。
     */
    public function onEvent(string $event, object $source, mixed $data = null, ?string $branchId = null): void
    {
        if ($event !== 'tool-called' || ! $data instanceof ToolCalled) {
            return;
        }

        $definition = $this->context->definitionFor($data->tool->getName());
        if ($definition === null) {
            return;
        }

        try {
            RecordReceptionToolCalledAction::run($this->context, $definition, $data->tool);
        } catch (Throwable $exception) {
            Log::warning('[reception] 工具调用事件处理失败', [
                'conversation_id' => $this->context->conversation_id,
                'turn_id' => $this->context->turn_id,
                'tool' => $data->tool->getName(),
                'source_type' => $definition->source_type,
                'source_names' => $definition->source_names,
                'call_id' => $data->tool->getCallId(),
                'exception_class' => $exception::class,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
