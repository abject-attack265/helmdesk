<?php

/**
 * 生产邮件通道切 AWS SES 后的可用性验证。
 */

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\SesTransport;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('ses driver 能用 AWS 凭据构建真实 SES 传输', function () {
    config()->set('services.ses', [
        'key' => 'test-access-key',
        'secret' => 'test-secret-key',
        'region' => 'us-east-1',
    ]);

    // 解析 ses mailer 会实例化 AWS SDK SesClient，SDK 缺失或 driver 未接好都会在此失败。
    $transport = Mail::mailer('ses')->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(SesTransport::class);
});

test('MAIL_MAILER=ses 时默认邮件通道即为 SES', function () {
    config()->set('mail.default', 'ses');
    config()->set('services.ses', [
        'key' => 'test-access-key',
        'secret' => 'test-secret-key',
        'region' => 'us-east-1',
    ]);

    expect(Mail::mailer()->getSymfonyTransport())->toBeInstanceOf(SesTransport::class);
});

test('找回密码邮件渲染出带重置链接的可投递内容', function () {
    $user = User::factory()->create();

    $mail = (new QueuedResetPassword('reset-token'))->toMail($user);
    $rendered = (string) $mail->render();

    expect($mail->actionUrl)->toContain('reset-token');
    expect($rendered)->toContain(e($mail->actionUrl));
});

test('邮箱验证邮件渲染出带验证链接的可投递内容', function () {
    $user = User::factory()->unverified()->create();

    $mail = (new QueuedVerifyEmail)->toMail($user);
    $rendered = (string) $mail->render();

    expect($mail->actionUrl)->toContain('/email/verify/');
    expect($rendered)->toContain(e($mail->actionUrl));
});
