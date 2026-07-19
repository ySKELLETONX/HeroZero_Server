'use strict';
/* Simula o cliente oficial (Engine.IO v2/Socket.IO v2) e valida o fluxo completo:
 * handshake polling -> upgrade WS -> registro -> push. Sai 0 se tudo passar. */
const http = require('http');
const { WebSocket } = require('ws');

const HOST = '127.0.0.1';
const PORT = process.env.HZ_SOCKET_PORT || 8090;
const TOKEN = process.env.HZ_SOCKET_TOKEN || 'local-dev-token';
const USER_ID = 25328;
const steps = [];
const pass = (m) => { steps.push(`ok  ${m}`); };
const fail = (m) => { console.log(steps.join('\n')); console.error(`FAIL ${m}`); process.exit(1); };

function get(path) {
  return new Promise((resolve, reject) => {
    http.get({ host: HOST, port: PORT, path }, (res) => {
      let b = ''; res.on('data', (c) => (b += c)); res.on('end', () => resolve(b));
    }).on('error', reject);
  });
}
function post(path, body) {
  return new Promise((resolve, reject) => {
    const data = JSON.stringify(body);
    const req = http.request({ host: HOST, port: PORT, path, method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(data), 'X-Push-Token': TOKEN } },
      (res) => { let b = ''; res.on('data', (c) => (b += c)); res.on('end', () => resolve({ status: res.statusCode, body: b })); });
    req.on('error', reject); req.write(data); req.end();
  });
}

(async () => {
  // 1. handshake polling
  const open = await get('/socket.io/?EIO=2&transport=polling&t=abc');
  const m = open.match(/^\d+:0(\{.*\})$/);
  if (!m) fail(`handshake inesperado: ${open}`);
  const info = JSON.parse(m[1]);
  if (!info.sid || !info.upgrades.includes('websocket')) fail('handshake sem sid/upgrades');
  pass(`handshake polling sid=${info.sid}`);

  // 2. upgrade WS
  const ws = new WebSocket(`ws://${HOST}:${PORT}/socket.io/?EIO=2&transport=websocket&sid=${info.sid}`);
  let registered = false, gotPush = false;
  const timeout = setTimeout(() => fail('timeout global'), 5000);

  ws.on('open', () => { pass('ws aberto'); ws.send('2probe'); });
  ws.on('message', async (buf) => {
    const d = buf.toString();
    if (d === '3probe') { pass('3probe recebido'); ws.send('5'); return; }
    if (d === '40') { pass('40 connect ns'); return; }
    if (d.startsWith('42')) {
      const [name, payload] = JSON.parse(d.slice(2));
      if (name === 'requestClientInfo') {
        pass('requestClientInfo recebido');
        ws.send('4' + '2' + JSON.stringify(['message', { type: 'requestClientInfoResponse',
          data: { game_id: 'hero', server_id: 'local', user_id: USER_ID, session_id: 'sess-xyz' } }]));
        return;
      }
      if (name === 'clientRegistered') {
        registered = true; pass('clientRegistered recebido');
        const r = await post('/push', { user_id: USER_ID, type: 'syncGameAndGuild' });
        if (r.status !== 200 || JSON.parse(r.body).delivered !== 1) fail(`push falhou: ${r.status} ${r.body}`);
        pass('push HTTP aceito (delivered=1)');
        return;
      }
      if (name === 'message' && payload && payload.type === 'syncGameAndGuild') {
        gotPush = true; pass('push syncGameAndGuild recebido no socket');
        clearTimeout(timeout); ws.close();
        console.log(steps.join('\n'));
        console.log(registered && gotPush ? '\nTODOS OS PASSOS PASSARAM' : 'INCOMPLETO');
        process.exit(registered && gotPush ? 0 : 1);
      }
    }
  });
  ws.on('error', (e) => fail(`ws erro: ${e.message}`));
})();
