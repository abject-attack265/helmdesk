<?php

use App\Http\Middleware\MercureSubscriberCookie;
use App\Services\Realtime\MercurePublisher;
use App\Services\Realtime\MercureTopics;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

test('后台响应写入 Mercure 订阅凭证', function () {
    config(['octane.mercure.subscriber_jwt' => 'test-secret']);
    $request = Request::create('/app/inbox', 'GET', server: ['HTTPS' => 'on']);

    $response = app(MercureSubscriberCookie::class)->handle(
        $request,
        fn () => new Response('ok'),
    );

    $cookie = $response->headers->getCookies()[0];
    $payload = explode('.', $cookie->getValue())[1];
    $claims = json_decode(
        base64_decode(strtr($payload.str_repeat('=', (4 - strlen($payload) % 4) % 4), '-_', '+/')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($cookie->getName())->toBe('mercureAuthorization')
        ->and($cookie->getPath())->toBe('/.well-known/mercure')
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($claims['mercure']['subscribe'])->toBe([
            MercureTopics::receptionInbox(),
            MercureTopics::receptionConversationSelector(),
            MercureTopics::aiChatSelector(),
        ]);
});

test('AI 实时发布失败时向调用方抛出异常', function () {
    $publisher = new MercurePublisher(
        fn (string $_topic, string $_event, array $_payload, bool $_private): never => throw new RuntimeException('mercure unavailable'),
    );

    expect(fn () => $publisher->publish(
        MercureTopics::aiChat('round-1'),
        'message',
        ['type' => 'delta'],
        true,
    ))->toThrow(RuntimeException::class, 'mercure unavailable');
});

test('实时事件直接发布到 Worker 持有的 Mercure Hub', function () {
    $updates = [];
    $publisher = new MercurePublisher(
        function (string $topic, string $event, array $payload, bool $private) use (&$updates): string {
            $updates[] = compact('topic', 'event', 'payload', 'private');

            return 'event-1';
        },
    );

    $publisher->publish(
        MercureTopics::receptionConversation('conversation-1'),
        'message',
        ['id' => 'message-1'],
        true,
    );

    expect($updates)->toBe([[
        'topic' => MercureTopics::receptionConversation('conversation-1'),
        'event' => 'message',
        'payload' => ['id' => 'message-1'],
        'private' => true,
    ]]);
});
