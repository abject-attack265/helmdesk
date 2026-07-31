<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * 将密码重置通知放入队列发送。
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;

    /**
     * 创建带重置令牌的队列通知。
     */
    public function __construct(#[\SensitiveParameter] string $token)
    {
        parent::__construct($token);

        $this->queue = 'notifications';
    }
}
