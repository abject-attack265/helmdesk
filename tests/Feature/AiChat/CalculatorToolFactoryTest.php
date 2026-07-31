<?php

use App\Services\AiChat\CalculatorToolFactory;

/**
 * 执行一次计算器工具调用并解析结果。
 *
 * @return array<string, mixed>
 */
function calculateWithAiTool(string $expression): array
{
    $tool = app(CalculatorToolFactory::class)->build();
    $tool->setInputs([
        'expression' => $expression,
    ]);
    $tool->execute();

    return json_decode($tool->getResult(), true, flags: JSON_THROW_ON_ERROR);
}

test('计算器一次完成常用数学表达式', function (string $input, int|float $result, string $expression) {
    expect(calculateWithAiTool($input))->toBe([
        'result' => $result,
        'expression' => $expression,
    ]);
})->with([
    '运算优先级' => ['2+3*4', 14, '2+3*4 = 14'],
    '括号' => ['(2+3)*4', 20, '(2+3)*4 = 20'],
    '连续幂运算' => ['2^(3^2)', 512, '2^(3^2) = 512'],
    '科学计数法' => ['1.5e3/2', 750, '1.5e3/2 = 750'],
    '浮点误差收敛' => ['0.1+0.2', 0.3, '0.1+0.2 = 0.3'],
    '余数' => ['10%3', 1, '10%3 = 1'],
    '常用函数' => ['sqrt(81)+pow(2,3)', 17, 'sqrt(81)+pow(2,3) = 17'],
    '平均值' => ['avg(2,4,9)', 5, 'avg(2,4,9) = 5'],
    '三角函数' => ['sin(pi/2)', 1, 'sin(pi/2) = 1'],
    '业务算式' => ['3232424*32323-44223', 104481596729, '3232424*32323-44223 = 104481596729'],
]);

test('计算器明确返回除零和非法表达式错误', function () {
    expect(calculateWithAiTool('12/0'))->toBe([
        'error' => 'division_by_zero',
    ])->and(calculateWithAiTool('system("date")'))->toBe([
        'error' => 'expression_invalid',
    ])->and(calculateWithAiTool('unknown+1'))->toBe([
        'error' => 'expression_invalid',
    ]);
});
