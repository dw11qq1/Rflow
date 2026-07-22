<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | 本应用使用 Soketi（Pusher 协议兼容）作为 WebSocket 服务。
    | 因沙箱无法安装 pusher/pusher-php-server，这里用自定义 SoketiBroadcaster
    | 通过 Guzzle 直接调用 Soketi 的 REST API 完成广播，无需任何额外 PHP 依赖。
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    'connections' => [
        'soketi' => [
            'driver' => 'soketi',
            'app_id' => env('SOKETI_APP_ID', 'reflow'),
            'key' => env('SOKETI_APP_KEY', 'reflow_key'),
            'secret' => env('SOKETI_APP_SECRET', 'reflow_secret'),
            'host' => env('SOKETI_HOST', '127.0.0.1'),
            'port' => env('SOKETI_PORT', 6001),
            'scheme' => env('SOKETI_SCHEME', 'http'),
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],
];
