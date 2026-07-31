<?php

namespace App\Services\Integration\Providers\Concerns;

use NeuronAI\Tools\ArrayProperty;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use Throwable;

/**
 * 把工具定义里的 JSON Schema（input_schema）装配成 NeuronAI Tool 的共用逻辑：声明属性 + 绑定执行回调。
 *
 * HTTP 业务系统 provider 与进程内 mock provider 共用此装配；二者差异仅在「执行回调如何构造」，故回调由调用方传入。
 */
trait BuildsToolFromSchema
{
    /**
     * 按工具定义构造 NeuronAI Tool：声明 input_schema 中的属性，并绑定调用方给定的执行回调。
     *
     * @param  array{name: string, description: ?string, input_schema: ?array<string, mixed>}  $definition
     */
    protected function buildToolFromSchema(string $name, array $definition, callable $invoker): Tool
    {
        $tool = Tool::make(
            name: $name,
            description: is_string($definition['description'] ?? null) ? $definition['description'] : null,
        );

        $schema = is_array($definition['input_schema'] ?? null) ? $definition['input_schema'] : [];
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];

        foreach ($properties as $propName => $prop) {
            if (! is_string($propName) || ! is_array($prop)) {
                continue;
            }
            $tool->addProperty($this->buildProperty($propName, $prop, in_array($propName, $required, true)));
        }

        $tool->setCallable($invoker);

        return $tool;
    }

    /**
     * 把单个 JSON Schema 属性转成 NeuronAI 属性对象（数组用 ArrayProperty，其余用 ToolProperty）。
     *
     * @param  array<string, mixed>  $prop
     */
    protected function buildProperty(string $name, array $prop, bool $required): ToolProperty|ArrayProperty
    {
        $type = $this->resolvePropertyType($prop['type'] ?? null);
        $description = is_string($prop['description'] ?? null) ? $prop['description'] : null;

        if ($type === PropertyType::ARRAY) {
            return new ArrayProperty(
                name: $name,
                description: $description,
                required: $required,
                items: new ToolProperty(
                    name: 'item',
                    type: $this->resolvePropertyType($prop['items']['type'] ?? null),
                ),
            );
        }

        $enum = is_array($prop['enum'] ?? null) ? array_values($prop['enum']) : [];

        return new ToolProperty(
            name: $name,
            type: $type,
            description: $description,
            required: $required,
            enum: $enum,
        );
    }

    /**
     * 把 JSON Schema 的 type 解析成 NeuronAI PropertyType；无法识别（远端业务系统给了 datetime/int 等非标准类型）时退回 STRING。
     *
     * 与本 trait 其余字段的 is_string/is_array 防御一致：单个属性类型不合法不应让整套工具装配抛错、整组工具失效。
     */
    private function resolvePropertyType(mixed $type): PropertyType
    {
        if (! is_string($type) && ! is_array($type)) {
            return PropertyType::STRING;
        }

        try {
            return PropertyType::fromSchema($type);
        } catch (Throwable) {
            return PropertyType::STRING;
        }
    }
}
