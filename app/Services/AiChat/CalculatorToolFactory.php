<?php

namespace App\Services\AiChat;

use DivisionByZeroError;
use InvalidArgumentException;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use OverflowException;

/**
 * 为 AI 助手提供确定性的数学表达式工具。
 */
class CalculatorToolFactory
{
    /**
     * 注入受限数学表达式求值器。
     */
    public function __construct(
        private readonly CalculatorExpressionEvaluator $evaluator,
    ) {}

    /**
     * 构造支持常用数学运算的 calculator 工具。
     */
    public function build(): Tool
    {
        return Tool::make(
            'calculator',
            '确定性计算数学表达式。支持四则运算、余数、幂、括号、常用数学函数和 pi/e；需要准确数值结果时优先使用。',
        )
            ->addProperty(new ToolProperty(
                'expression',
                PropertyType::STRING,
                '完整数学表达式。可用 abs、sqrt、round、floor、ceil、min、max、avg、三角函数、log、log10、exp、pow、fmod、hypot 及 pi/e。',
                true,
            ))
            ->setCallable(fn (?string $expression): array => $this->calculate($expression));
    }

    /**
     * 执行表达式并返回数值与完整算式。
     *
     * @return array{result?: float, expression?: string, error?: string}
     */
    private function calculate(?string $expression): array
    {
        try {
            $result = $this->evaluator->evaluate((string) $expression);
        } catch (DivisionByZeroError) {
            return ['error' => 'division_by_zero'];
        } catch (OverflowException) {
            return ['error' => 'calculation_overflow'];
        } catch (InvalidArgumentException) {
            return ['error' => 'expression_invalid'];
        }

        $formattedResult = $this->formatNumber($result);

        return [
            'result' => (float) $formattedResult,
            'expression' => trim((string) $expression).' = '.$formattedResult,
        ];
    }

    /**
     * 将计算结果格式化为便于模型引用的数字文本。
     */
    private function formatNumber(float $number): string
    {
        if ($number === floor($number) && abs($number) < 1e15) {
            return sprintf('%.0f', $number);
        }

        return sprintf('%.15g', $number);
    }
}
