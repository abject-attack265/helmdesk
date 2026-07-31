<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 默认邮件发送器
    |--------------------------------------------------------------------------
    |
    | 未显式指定发送器时，所有邮件都使用此配置。
    | 其它发送器统一在 mailers 数组中定义。
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | 邮件发送器配置
    |--------------------------------------------------------------------------
    |
    | 当前配置 smtp、sendmail、ses、log、array 和 failover 发送器。
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | 全局发件人
    |--------------------------------------------------------------------------
    |
    | 未单独指定发件人时，所有邮件使用此名称和地址。
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

];
