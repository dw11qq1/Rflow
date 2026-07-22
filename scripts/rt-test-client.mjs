import WebSocket from 'ws';
import crypto from 'node:crypto';
import fs from 'node:fs';
import os from 'node:os';

const RECEIVED_FILE = `${os.tmpdir()}/rt_received.txt`;

const slug = process.argv[2];
const channel = `presence-board.${slug}`;
const SECRET = 'reflow_secret';
const KEY = 'reflow_key';

const ws = new WebSocket('ws://127.0.0.1:6001');
let socketId = null;

ws.on('open', () => console.log('OPEN'));
ws.on('error', (e) => console.log('WSERR', e.message));
ws.on('message', (raw) => {
    const m = JSON.parse(raw);
    if (m.event === 'pusher:connection_established') {
        socketId = m.data.socket_id;
        const channelData = JSON.stringify({ id: 1, name: 'Tester' });
        const sig = crypto
            .createHmac('sha256', SECRET)
            .update(`${socketId}:${channel}:${channelData}`)
            .digest('hex');
        ws.send(
            JSON.stringify({
                event: 'pusher:subscribe',
                data: { channel, auth: `${KEY}:${sig}`, channel_data: channelData },
            }),
        );
        console.log('SUBSCRIBED', channel);
    }
    if (m.event === 'pusher:subscription_succeeded') {
        console.log('SUCCEEDED', JSON.stringify(m.data).slice(0, 90));
    }
    if (m.event === 'pusher:member_added') {
        console.log('MEMBER_ADDED');
    }
    if (m.event === 'board.updated') {
        console.log('RECEIVED board.updated on', m.channel);
        fs.writeFileSync(RECEIVED_FILE, 'YES');
    }
});

setTimeout(() => {
    console.log('DONE');
    process.exit(0);
}, 9000);
