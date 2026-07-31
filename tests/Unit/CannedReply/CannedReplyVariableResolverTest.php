<?php

use App\Data\CannedReply\CannedReplyRenderContextData;
use App\Services\CannedReply\CannedReplyVariableResolver;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->resolver = new CannedReplyVariableResolver;
    $this->context = new CannedReplyRenderContextData(
        app_name: 'Helmdesk',
        teammate_name: '张客服',
        contact_name: '李四',
        contact_email: 'lisi@example.com',
        contact_primary_phone: '138-0000-0000',
        conversation_id: '01HXYZ',
        conversation_subject: '退款咨询',
    );
});

test('render 解析静态变量并对缺值产生 warning', function () {
    $full = $this->resolver->render(
        '你好 {{contact.name}}（{{contact.email}}），我是 {{teammate.name}}，'
        .'代表 {{app.name}} 处理你的会话 {{conversation.subject}} (id: {{conversation.id}})。',
        $this->context,
    );
    expect($full['content'])->toBe('你好 李四（lisi@example.com），我是 张客服，代表 Helmdesk 处理你的会话 退款咨询 (id: 01HXYZ)。')
        ->and($full['warnings'])->toBe([]);

    $unknown = $this->resolver->render('请联系 {{customer.name}} 或 {{contact.name}}。', $this->context);
    expect($unknown['content'])->toBe('请联系 {{customer.name}} 或 李四。');

    $emptyContext = new CannedReplyRenderContextData(
        app_name: 'Helmdesk',
        teammate_name: '张客服',
        contact_name: null,
        contact_email: null,
        contact_primary_phone: null,
        conversation_id: null,
        conversation_subject: null,
    );
    $missing = $this->resolver->render('你好 {{contact.name}}，主题：{{conversation.subject}}', $emptyContext);
    expect($missing['content'])->toBe('你好 {{contact.name}}，主题：{{conversation.subject}}')
        ->and($missing['warnings'])->toHaveCount(2);
});

test('extractTokens 返回模版中实际出现的 token', function () {
    $template = '{{contact.name}} {{contact.email}} {{contact.name}} 静态文本';

    $tokens = $this->resolver->extractTokens($template);

    expect($tokens)->toBe(['{{contact.name}}', '{{contact.email}}']);
});

test('availableTokens 返回全部静态变量列表', function () {
    $tokens = $this->resolver->availableTokens();

    $tokenStrings = array_column($tokens, 'token');
    expect($tokenStrings)->toContain('{{contact.name}}');
    expect($tokenStrings)->toContain('{{contact.email}}');
    expect($tokenStrings)->toContain('{{contact.primary_phone}}');
    expect($tokenStrings)->toContain('{{conversation.subject}}');
    expect($tokenStrings)->toContain('{{conversation.id}}');
    expect($tokenStrings)->toContain('{{teammate.name}}');
    expect($tokenStrings)->toContain('{{app.name}}');
});
