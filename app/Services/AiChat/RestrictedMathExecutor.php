<?php

namespace App\Services\AiChat;

use NXP\MathExecutor;

/**
 * 为 AI 计算器提供受控的数学运算符、函数和常量。
 */
class RestrictedMathExecutor extends MathExecutor
{
    /**
     * 仅开放数值计算需要的运算符。
     *
     * @return array<string, array{callable, int, bool}>
     */
    protected function defaultOperators(): array
    {
        return array_intersect_key(parent::defaultOperators(), array_flip([
            '+',
            '-',
            'uPos',
            'uNeg',
            '*',
            '/',
            '^',
            '%',
        ]));
    }

    /**
     * 仅开放常用且结果确定的数学函数。
     *
     * @return array<string, callable>
     */
    protected function defaultFunctions(): array
    {
        return array_intersect_key(parent::defaultFunctions(), array_flip([
            'abs',
            'sqrt',
            'round',
            'floor',
            'ceil',
            'min',
            'max',
            'avg',
            'sin',
            'cos',
            'tan',
            'asin',
            'acos',
            'atan',
            'atan2',
            'deg2rad',
            'rad2deg',
            'log',
            'log10',
            'exp',
            'pow',
            'fmod',
            'hypot',
        ]));
    }

    /**
     * 提供圆周率和自然常数。
     *
     * @return array<string, float>
     */
    protected function defaultVars(): array
    {
        return [
            'pi' => M_PI,
            'e' => M_E,
        ];
    }
}
