<?php

use App\Events\Reception\InstanceReceptionUpdated;
use App\Events\Reception\VisitorConversationUpdated;
use App\Services\Realtime\MercurePublisher;
use Illuminate\Broadcasting\BroadcastManager;

test('Mercure 广播器发布接待收件箱私有主题事件', function () {
    config(['broadcasting.default' => 'mercure']);
    $updates = [];
    app()->instance(MercurePublisher::class, new MercurePublisher(
        function (string $topic, string $event, array $payload, bool $private) use (&$updates): string {
            $updates[] = compact('topic', 'event', 'payload', 'private');

            return 'event-1';
        },
    ));
    app(BroadcastManager::class)->forgetDrivers();

    event(new InstanceReceptionUpdated(['event' => 'test']));

    expect($updates)->toBe([[
        'topic' => 'urn:helmdesk:reception:inbox',
        'event' => 'reception',
        'payload' => ['event' => 'test', 'socket' => null],
        'private' => true,
    ]]);
});

test('Mercure 广播器发布访客会话公开主题', function () {
    config(['broadcasting.default' => 'mercure']);
    $updates = [];
    app()->instance(MercurePublisher::class, new MercurePublisher(
        function (string $topic, string $event, array $payload, bool $private) use (&$updates): string {
            $updates[] = compact('topic', 'event', 'payload', 'private');

            return 'event-1';
        },
    ));
    app(BroadcastManager::class)->forgetDrivers();

    event(new VisitorConversationUpdated('conversation-1', ['event' => 'test']));

    expect($updates)->toBe([[
        'topic' => 'urn:helmdesk:reception:conversation:conversation-1',
        'event' => 'reception',
        'payload' => ['event' => 'test', 'socket' => null],
        'private' => false,
    ]]);
});
