<?php

use App\Services\AiChat\AiChatToolStreamPayloadBuilder;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

test('工具流式载荷包含调用参数结果和可读名称', function () {
    $tool = Tool::make('lookup_order')
        ->addProperty(new ToolProperty('order_id', PropertyType::STRING, required: true))
        ->setCallable(static fn (?string $order_id): array => [
            'order_id' => $order_id,
            'status' => '已发货',
        ]);
    $tool->setInputs(['order_id' => 'ORDER-1']);
    $builder = app(AiChatToolStreamPayloadBuilder::class);

    $call = $builder->call($tool, ['lookup_order' => '订单系统 / lookup_order']);
    $tool->execute();
    $result = $builder->result($tool, ['lookup_order' => '订单系统 / lookup_order']);

    expect($call)->toBe([
        'type' => 'tool_call',
        'tool' => 'lookup_order',
        'args' => '{"order_id":"ORDER-1"}',
        'tool_display' => '订单系统 / lookup_order',
    ])->and($result)->toMatchArray([
        'type' => 'tool_result',
        'tool' => 'lookup_order',
        'tool_display' => '订单系统 / lookup_order',
    ])->and(json_decode($result['content'], true, flags: JSON_THROW_ON_ERROR))->toBe([
        'order_id' => 'ORDER-1',
        'status' => '已发货',
    ]);
});
