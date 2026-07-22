<?php

namespace App\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * 自定义 Soketi 广播驱动。
 *
 * 由于沙箱无法安装 pusher/pusher-php-server，这里直接用 Guzzle（框架自带）调用
 * Soketi 的 Pusher 兼容 REST API 完成广播，并用标准 Pusher 签名算法做频道鉴权。
 * 无需任何额外 PHP 依赖，也不依赖队列（事件使用 ShouldBroadcastNow 即时发出）。
 *
 * 复用 Laravel 官方的 UsePusherChannelConventions trait（与 PusherBroadcaster 一致），
 * 提供 isGuardedChannel() / normalizeChannelName()，使频道鉴权逻辑与 Pusher 协议完全对齐。
 */
class SoketiBroadcaster extends Broadcaster
{
    use UsePusherChannelConventions;

    public function __construct(protected array $config = []) {}

    /**
     * 向 Soketi 发送广播。channels 为频道名数组，event 为事件名，payload 为数据。
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        if (empty($channels)) {
            return;
        }

        // Channel 对象需转成字符串（PresenceChannel/PrivateChannel 实现了 __toString），
        // 否则会序列化成一个对象而非频道名，导致服务端按名查找订阅者失败。
        $channels = array_map(fn ($c) => (string) $c, $channels);

        $body = json_encode([
            'name' => $event,
            'channels' => $channels,
            'data' => json_encode($payload),
        ]);

        $path = '/apps/' . $this->config['app_id'] . '/events';
        $query = http_build_query([
            'auth_key' => $this->config['key'],
            'auth_timestamp' => time(),
            'auth_version' => '1.0',
            'body_md5' => md5($body),
        ]);

        // Pusher REST 标准签名：md5( METHOD\nPATH\nQUERY（不含 auth_signature） )
        $signature = md5('POST' . "\n" . $path . "\n" . $query);
        $query .= '&auth_signature=' . $signature;

        $url = $this->config['scheme'] . '://' . $this->config['host'] . ':' . $this->config['port'] . $path . '?' . $query;

        try {
            Http::withHeaders(['Content-Type' => 'application/json'])
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (\Throwable $e) {
            // 广播失败不应阻断主流程（如 Soketi 未启动）
            report($e);
        }
    }

    /**
     * 频道鉴权入口（private / presence 通用）。
     * 复用抽象类的 verifyUserCanAccessChannel：它会调用 routes/channels.php 中
     * 注册的频道回调，得到 $result（presence 为成员信息数组，private 为 true），
     * 再交给 validAuthenticationResponse 生成签名。
     */
    public function auth($request)
    {
        $channelName = $this->normalizeChannelName($request->channel_name);

        if (empty($request->channel_name) ||
            ($this->isGuardedChannel($request->channel_name) &&
            ! $this->retrieveUser($request, $channelName))) {
            throw new AccessDeniedHttpException;
        }

        return $this->verifyUserCanAccessChannel($request, $channelName);
    }

    /**
     * 生成频道鉴权响应（private / presence 通用）。
     * auth = HMAC-SHA256(secret, socket_id:channel[:channel_data])
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $result  频道数据：presence 为成员信息数组，private 为 true
     */
    public function validAuthenticationResponse($request, $result)
    {
        $socketId = $request->input('socket_id');
        $channelName = $request->channel_name;
        $secret = $this->config['secret'];
        $key = $this->config['key'];

        // presence 频道：需要附带 channel_data
        if ($result !== null && $result !== true) {
            $data = json_encode($result);
            $signature = hash_hmac('sha256', $socketId . ':' . $channelName . ':' . $data, $secret);

            return [
                'auth' => $key . ':' . $signature,
                'channel_data' => $data,
            ];
        }

        // private 频道
        $signature = hash_hmac('sha256', $socketId . ':' . $channelName, $secret);

        return ['auth' => $key . ':' . $signature];
    }
}
