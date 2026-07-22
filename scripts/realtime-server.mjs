/**
 * Reflow 实时协同本地服务（Pusher 协议兼容）。
 *
 * @soketi/soketi 依赖的 uWebSockets.js 仅支持 Node ≤ 18，无法在当前 Node 22 环境运行，
 * 因此提供此零原生依赖（仅 ws）的兼容服务，用于在任意 Node 版本下本地验证实时协同。
 * 它实现本项目用到的协议子集：
 *   - WebSocket：pusher:connection_established / subscribe / unsubscribe / ping-pong
 *   - presence 频道：member_added / member_removed / subscription_succeeded
 *   - REST（与 SoketiBroadcaster 完全一致）：POST /apps/{appId}/events（带 Pusher 标准签名）
 *
 * 与官方 Soketi 二选一即可（配置相同），前端 Echo 无需改动。
 *
 * 运行：node scripts/realtime-server.mjs
 *   或：npm run realtime
 */
import http from 'node:http';
import crypto from 'node:crypto';
import WebSocket from 'ws';

const WebSocketServer = WebSocket.Server;

const APP_ID = process.env.SOKETI_APP_ID || 'reflow';
const APP_KEY = process.env.SOKETI_APP_KEY || 'reflow_key';
const APP_SECRET = process.env.SOKETI_APP_SECRET || 'reflow_secret';
const PORT = Number(process.env.SOKETI_PORT || 6001);
const HOST = process.env.SOKETI_HOST || '127.0.0.1';

const server = http.createServer((req, res) => {
    if (req.method === 'POST' && req.url.startsWith(`/apps/${APP_ID}/events`)) {
        let body = '';
        req.on('data', (c) => (body += c));
        req.on('end', () => handleEvents(req, res, body));
        return;
    }
    if (req.url === '/health' || req.url === '/up') {
        res.writeHead(200);
        res.end('ok');
        return;
    }
    res.writeHead(404);
    res.end('not found');
});

const wss = new WebSocketServer({ server });
const clients = new Map(); // ws -> { socketId, channels: Set<string> }
const channels = new Map(); // channel -> Map<socketId, memberInfo>

const newSocketId = () =>
    crypto.randomBytes(8).toString('hex') + '.' + crypto.randomBytes(6).toString('hex');

function safeJson(str) {
    try {
        return JSON.parse(str);
    } catch {
        return null;
    }
}

function broadcastToChannel(channel, payload, exceptSocketId = null) {
    const subs = channels.get(channel);
    if (!subs) return;
    const raw = JSON.stringify(payload);
    for (const socketId of subs.keys()) {
        if (socketId === exceptSocketId) continue;
        const ws = [...clients.entries()].find(([, c]) => c.socketId === socketId)?.[0];
        if (ws && ws.readyState === 1) ws.send(raw);
    }
}

function leaveChannel(ws, channel) {
    const client = clients.get(ws);
    if (!client) return;
    const subs = channels.get(channel);
    if (!subs) return;
    const socketId = client.socketId;
    const member = subs.get(socketId);
    subs.delete(socketId);
    if (subs.size === 0) channels.delete(channel);
    client.channels.delete(channel);
    if (channel.startsWith('presence') && member) {
        broadcastToChannel(channel, {
            event: 'pusher:member_removed',
            channel,
            // laravel-echo leaving 回调读取 t.info
            data: { id: String(member.id), info: member.info },
        }, socketId);
    }
}

function handleSubscribe(ws, data) {
    const channel = data.channel;
    if (!channel) return;
    const client = clients.get(ws);
    const socketId = client.socketId;

    // private / presence 频道需校验 Laravel 签发的 auth
    if (channel.startsWith('private') || channel.startsWith('presence')) {
        const [key, sig] = (data.auth || '').split(':');
        let expected;
        if (channel.startsWith('presence') && data.channel_data) {
            expected = crypto
                .createHmac('sha256', APP_SECRET)
                .update(`${socketId}:${channel}:${data.channel_data}`)
                .digest('hex');
        } else {
            expected = crypto
                .createHmac('sha256', APP_SECRET)
                .update(`${socketId}:${channel}`)
                .digest('hex');
        }
        if (key !== APP_KEY || sig !== expected) {
            ws.send(
                JSON.stringify({
                    event: 'pusher:subscription_error',
                    channel,
                    data: { type: 'AuthError', message: 'auth failed' },
                }),
            );
            return;
        }
    }

    client.channels.add(channel);
    if (!channels.has(channel)) channels.set(channel, new Map());
    const memberInfo = data.channel_data ? safeJson(data.channel_data) : null;
    const info = memberInfo ?? { id: socketId };
    channels.get(channel).set(socketId, { id: info.id ?? socketId, info });

    if (channel.startsWith('presence')) {
        const subs = channels.get(channel);
        const members = {};
        for (const [, m] of subs) {
            members[String(m.id)] = m.info;
        }
        ws.send(
            JSON.stringify({
                event: 'pusher:subscription_succeeded',
                channel,
                // laravel-echo v2.4 / pusher-js v8 期望 data.members = { [id]: info }
                data: { members },
            }),
        );
        broadcastToChannel(
            channel,
            {
                event: 'pusher:member_added',
                channel,
                // laravel-echo joining 回调读取 t.info
                data: { id: String(info.id ?? socketId), info },
            },
            socketId,
        );
    } else {
        ws.send(
            JSON.stringify({
                event: 'pusher:subscription_succeeded',
                channel,
                data: {},
            }),
        );
    }
}

function handleEvents(req, res, body) {
    const url = new URL(req.url, 'http://localhost');
    const p = url.searchParams;
    const authKey = p.get('auth_key');
    const ts = p.get('auth_timestamp');
    const ver = p.get('auth_version');
    const bodyMd5 = p.get('body_md5');
    const sig = p.get('auth_signature');

    if (crypto.createHash('md5').update(body).digest('hex') !== bodyMd5) {
        res.writeHead(400);
        res.end('bad body md5');
        return;
    }

    const query = `auth_key=${encodeURIComponent(authKey)}&auth_timestamp=${encodeURIComponent(
        ts,
    )}&auth_version=${encodeURIComponent(ver)}&body_md5=${encodeURIComponent(bodyMd5)}`;
    const expected = crypto
        .createHash('md5')
        .update('POST' + '\n' + `/apps/${APP_ID}/events` + '\n' + query)
        .digest('hex');

    if (authKey !== APP_KEY || sig !== expected) {
        res.writeHead(403);
        res.end('auth failed');
        return;
    }

    let payload;
    try {
        payload = JSON.parse(body);
    } catch {
        res.writeHead(400);
        res.end('bad json');
        return;
    }

    for (const ch of payload.channels || []) {
        broadcastToChannel(ch, {
            event: payload.name,
            channel: ch,
            data: payload.data,
        });
    }

    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ ok: true }));
}

wss.on('connection', (ws) => {
    const socketId = newSocketId();
    clients.set(ws, { socketId, channels: new Set() });

    ws.send(
        JSON.stringify({
            event: 'pusher:connection_established',
            data: { socket_id: socketId, activity_timeout: 120 },
        }),
    );

    ws.on('message', (raw) => {
        let msg;
        try {
            msg = JSON.parse(raw);
        } catch {
            return;
        }
        if (msg.event === 'pusher:ping') {
            ws.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
            return;
        }
        if (msg.event === 'pusher:subscribe') return handleSubscribe(ws, msg.data || {});
        if (msg.event === 'pusher:unsubscribe') {
            leaveChannel(ws, (msg.data || {}).channel);
            return;
        }
    });

    ws.on('close', () => {
        for (const ch of clients.get(ws)?.channels || []) leaveChannel(ws, ch);
        clients.delete(ws);
    });
});

server.listen(PORT, HOST, () => {
    console.log(
        `[Reflow Realtime] Pusher-compatible server on ws://${HOST}:${PORT} (app=${APP_ID})`,
    );
});
