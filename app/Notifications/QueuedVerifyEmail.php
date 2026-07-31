<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * 将邮箱验证通知放入队列发送。
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->queue = 'notifications';
    }
}
