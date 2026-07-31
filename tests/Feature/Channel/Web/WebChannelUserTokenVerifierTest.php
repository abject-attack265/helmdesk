<?php

use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Services\Channel\WebChannelUserTokenVerifier;
use Illuminate\Support\Facades\Log;

/**
 * 校验 WebChannelUserTokenVerifier 的纯验签行为与失败日志分级：
 * 过期等生命周期类失败按 debug 记，签名/格式类配置错误按 warn 记。
 */
function makeWebChannel(string $secret = 'unit-secret-supersecret-xxxxxxxxxx'): Channel
{
    $channel = new Channel;
    $channel->id = '01kvaaxckvsr0tp5fs167zkxye';
    $channel->code = 'wch_unit_test';
    $channel->type = ChannelType::Web;
    $channel->settings = ChannelWebSettingsData::defaults([
        'user_token_secret' => $secret,
    ]);

    return $channel;
}

/** 使用 HS256 签发测试 token。 */
function signToken(string $secret, array $claims): string
{
    $base64Url = static fn (string $input): string => rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    $headerB64 = $base64Url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payloadB64 = $base64Url(json_encode($claims));
    $signature = hash_hmac('sha256', $headerB64.'.'.$payloadB64, $secret, true);

    return $headerB64.'.'.$payloadB64.'.'.$base64Url($signature);
}

test('有效 token 解析出标准化身份字段', function () {
    $secret = 'unit-secret-supersecret-xxxxxxxxxx';
    $channel = makeWebChannel($secret);
    $token = signToken($secret, [
        'sub' => 'crm:user_1',
        'name' => '张三',
        'email' => 'Zhang@Example.com',
        'exp' => time() + 3600,
    ]);

    $identity = app(WebChannelUserTokenVerifier::class)->verify($channel, $token);

    expect($identity)->not->toBeNull()
        ->and($identity['external_id'])->toBe('crm:user_1')
        ->and($identity['name'])->toBe('张三')
        ->and($identity['email'])->toBe('zhang@example.com');
});

test('过期 token 按 debug 记日志而非 warn', function () {
    $secret = 'unit-secret-supersecret-xxxxxxxxxx';
    $channel = makeWebChannel($secret);
    $token = signToken($secret, ['sub' => 'crm:user_1', 'exp' => time() - 3600]);

    $log = Log::spy();

    $identity = app(WebChannelUserTokenVerifier::class)->verify($channel, $token);

    expect($identity)->toBeNull();
    $log->shouldHaveReceived('debug')->once();
    $log->shouldNotHaveReceived('warning');
});

test('签名错误按 warn 记日志', function () {
    $channel = makeWebChannel('unit-secret-supersecret-xxxxxxxxxx');
    $token = signToken('a-different-secret-yyyyyyyyyyyyyyy', ['sub' => 'crm:user_1', 'exp' => time() + 3600]);

    $log = Log::spy();

    $identity = app(WebChannelUserTokenVerifier::class)->verify($channel, $token);

    expect($identity)->toBeNull();
    $log->shouldHaveReceived('warning')->once();
    $log->shouldNotHaveReceived('debug');
});

test('非数字时间声明按 warn 记日志并拒绝 token', function () {
    $secret = 'unit-secret-supersecret-xxxxxxxxxx';
    $channel = makeWebChannel($secret);
    $token = signToken($secret, ['sub' => 'crm:user_1', 'exp' => 'tomorrow']);

    $log = Log::spy();

    $identity = app(WebChannelUserTokenVerifier::class)->verify($channel, $token);

    expect($identity)->toBeNull();
    $log->shouldHaveReceived('warning')->once();
    $log->shouldNotHaveReceived('debug');
});
