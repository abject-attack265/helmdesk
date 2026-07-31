<?php

namespace App\Services\AiChat;

use DivisionByZeroError;
use InvalidArgumentException;
use NXP\Exception\DivisionByZeroException;
use NXP\Exception\MathExecutorException;
use OverflowException;
use ValueError;

/**
 * 校验并计算受控的数学表达式。
 */
class CalculatorExpressionEvaluator
{
    private const int MAX_EXPRESSION_LENGTH = 500;

    /**
     * 注入只开放数值能力的数学表达式执行器。
     */
    public function __construct(
        private readonly RestrictedMathExecutor $executor,
    ) {}

    /**
     * 计算包含运算符、函数和数学常量的表达式。
     */
    public function evaluate(string $expression): float
    {
        $expression = strtr(trim($expression), [
            '×' => '*',
            '÷' => '/',
            '−' => '-',
        ]);
        $expression = preg_replace(
            '/(?<![\d.])(\d+)([eE][+-]?\d+)/',
            '$1.0$2',
            $expression,
        );

        if ($expression === '' || mb_strlen($expression) > self::MAX_EXPRESSION_LENGTH) {
            throw new InvalidArgumentException('计算表达式为空或过长。');
        }

        if (preg_match('/\A[0-9A-Za-z_+\-*\/%^().,\s]+\z/', $expression) !== 1) {
            throw new InvalidArgumentException('计算表达式包含不支持的字符。');
        }

        try {
            $result = $this->executor->execute($expression, false);
        } catch (DivisionByZeroException $exception) {
            throw new DivisionByZeroError('计算表达式不能除以零。', previous: $exception);
        } catch (MathExecutorException|ValueError $exception) {
            throw new InvalidArgumentException('计算表达式无法解析。', previous: $exception);
        }

        if (! is_int($result) && ! is_float($result)) {
            throw new InvalidArgumentException('计算表达式未返回数值。');
        }

        return $this->finite((float) $result);
    }

    /**
     * 拒绝无法表示为有限浮点数的计算结果。
     */
    private function finite(float $number): float
    {
        if (! is_finite($number)) {
            throw new OverflowException('计算结果超出可表示范围。');
        }

        return $number;
    }
}
