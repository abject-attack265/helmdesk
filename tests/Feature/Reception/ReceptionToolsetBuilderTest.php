<?php

use App\Data\Integration\IntegrationToolSourceRuntimeData;
use App\Data\Reception\Runtime\ReceptionRuntimeData;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationTransport;
use App\Services\Integration\IntegrationToolBuilder;
use App\Services\KnowledgeBase\KnowledgeSearchToolFactory;
use App\Services\Reception\ReceptionGroundingProbe;
use App\Services\Reception\ReceptionToolsetBuilder;
use NeuronAI\Tools\Tool;

test('接待工具集保留无描述集成工具的 nullable 契约', function () {
    $source = new IntegrationToolSourceRuntimeData(
        id: 'integration-1',
        slug: 'remote',
        name: '远程系统',
        provider: IntegrationProvider::BusinessSystem,
        transport: IntegrationTransport::Http,
        endpoint_url: 'https://example.com',
        credentials: [],
        headers: [],
        timeout_seconds: 30,
        tool_names: ['remote_search'],
    );
    $tool = Tool::make('remote_search');
    $integrationTools = Mockery::mock(IntegrationToolBuilder::class);
    $integrationTools->shouldReceive('buildWithSources')
        ->once()
        ->andReturn([['tool' => $tool, 'source' => $source]]);
    $knowledgeSearchTools = Mockery::mock(KnowledgeSearchToolFactory::class);
    $runtime = new ReceptionRuntimeData(
        available: true,
        integration_tool_sources: [$source],
    );

    $toolset = (new ReceptionToolsetBuilder($knowledgeSearchTools, $integrationTools))->build(
        Tool::make('respond', '回复访客'),
        Tool::make('handoff_to_human', '转人工'),
        $runtime,
        new ReceptionGroundingProbe,
    );

    expect($toolset->event_definitions)->toHaveCount(1)
        ->and($toolset->event_definitions[0]->description)->toBeNull();
});

test('接待工具集跳过保留名称和集成间重名工具', function () {
    $firstSource = new IntegrationToolSourceRuntimeData(
        id: 'integration-1',
        slug: 'first',
        name: '第一个系统',
        provider: IntegrationProvider::BusinessSystem,
        transport: IntegrationTransport::Http,
        endpoint_url: 'https://first.example.com',
        credentials: [],
        headers: [],
        timeout_seconds: 30,
        tool_names: ['respond', 'query_order'],
    );
    $secondSource = new IntegrationToolSourceRuntimeData(
        id: 'integration-2',
        slug: 'second',
        name: '第二个系统',
        provider: IntegrationProvider::BusinessSystem,
        transport: IntegrationTransport::Http,
        endpoint_url: 'https://second.example.com',
        credentials: [],
        headers: [],
        timeout_seconds: 30,
        tool_names: ['query_order'],
    );
    $integrationTools = Mockery::mock(IntegrationToolBuilder::class);
    $integrationTools->shouldReceive('buildWithSources')
        ->once()
        ->andReturn([
            ['tool' => Tool::make('respond'), 'source' => $firstSource],
            ['tool' => Tool::make('query_order'), 'source' => $firstSource],
            ['tool' => Tool::make('query_order'), 'source' => $secondSource],
        ]);
    $runtime = new ReceptionRuntimeData(
        available: true,
        integration_tool_sources: [$firstSource, $secondSource],
    );

    $toolset = (new ReceptionToolsetBuilder(
        Mockery::mock(KnowledgeSearchToolFactory::class),
        $integrationTools,
    ))->build(
        Tool::make('respond', '回复访客'),
        Tool::make('handoff_to_human', '转人工'),
        $runtime,
        new ReceptionGroundingProbe,
    );

    expect(array_map(
        static fn (Tool $tool): string => $tool->getName(),
        $toolset->tools,
    ))->toBe(['respond', 'query_order', 'handoff_to_human'])
        ->and($toolset->event_definitions)->toHaveCount(1)
        ->and($toolset->event_definitions[0]->source_names)->toBe(['第一个系统']);
});
