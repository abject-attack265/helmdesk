<?php

use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Enums\AiProviderProtocol;
use App\Services\AiRuntime\AiHttpClientFactory;
use App\Services\AiRuntime\LenientHistoryTrimmer;
use App\Services\Reception\ReceptionAgentFactory;
use App\Services\Reception\ReceptionProviderFactory;
use NeuronAI\Chat\History\InMemoryChatHistory;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\UserMessage;

test('宽松裁剪器保留非常规顺序与连续同角色历史', function () {
    // 以 assistant 消息开头
    $assistantFirst = new InMemoryChatHistory(trimmer: new LenientHistoryTrimmer);
    $assistantFirst->addMessage(new AssistantMessage('您好，请问有什么可以帮您？'));
    $assistantFirst->addMessage(new UserMessage('你是谁'));
    expect($assistantFirst->getMessages())->toHaveCount(2);

    // 连续同角色消息
    $consecutive = new InMemoryChatHistory(trimmer: new LenientHistoryTrimmer);
    $consecutive->addMessage(new UserMessage('在吗'));
    $consecutive->addMessage(new UserMessage('有人吗'));
    $consecutive->addMessage(new AssistantMessage('在的'));
    $consecutive->addMessage(new AssistantMessage('请讲'));
    expect($consecutive->getMessages())->toHaveCount(4);

    // 工厂组装的 agent 历史接受 AI 欢迎语开头的会话
    $agent = (new ReceptionAgentFactory(new ReceptionProviderFactory(new AiHttpClientFactory)))->make(new RuntimeModelCandidateData(
        protocol: AiProviderProtocol::OpenAI,
        credentials: ['key' => 'k'],
        model_id: 'm1',
        supports_image_input: false,
        supports_video_input: false,
    ), 'sp');
    $agent->getChatHistory()
        ->addMessage(new AssistantMessage('您好，请问有什么可以帮您？'))
        ->addMessage(new UserMessage('你是谁'));
    expect($agent->getChatHistory()->getMessages())->toHaveCount(2);
});
