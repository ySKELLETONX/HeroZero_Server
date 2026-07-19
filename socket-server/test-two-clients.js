'use strict';
/* Dois clientes conectados simultaneamente. Valida:
 *   1. push a um user chega SO ao socket daquele user (isolamento)
 *   2. broadcast (push aos dois) chega aos dois (cenario chat de guilda)
 * Sai 0 se tudo passar. */
const http = require('http');
const { WebSocket } = require('ws');

const HOST = '127.0.0.1';
const PORT = process.env.HZ_SOCKET_PORT || 8090;
const TOKEN = process.env.HZ_SOCKET_TOKEN || 'local-dev-token';

const log = (...a) => console.log(...a);
function get(path) {
  return new Promise((res, rej) => http.get({ host: HOST, port: PORT, path }, (r) => {
    let b = ''; r.on('data', (c) => (b += c)); r.on('end', () => res(b)); }).on('error', rej));
}
function post(path, body) {
  return new Promise((res, rej) => {
    const data = JSON.stringify(body);
    const req = http.request({ host: HOST, port: PORT, path, method: 'POST', headers: {
      'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(data), 'X-Push-Token': TOKEN } },
      (r) => { let b = ''; r.on('data', (c) => (b += c)); r.on('end', () => res({ status: r.statusCode, body: b })); });
    req.on('error', rej); req.write(data); req.end();
  });
}

// Cliente que completa o handshake, registra userId e coleta pushes recebidos.
function connectClient(name, userId) {
  return new Promise(async (resolve, reject) => {
    const open = await get('/socket.io/?EIO=2&transport=polling&t=x');
    const sid = JSON.parse(open.match(/^\d+:0(\{.*\})$/)[1]).sid;
    const ws = new WebSocket(`ws://${HOST}:${PORT}/socket.io/?EIO=2&transport=websocket&sid=${sid}`);
    const client = { name, userId, ws, pushes: [], registered: false };
    ws.on('open', () => ws.send('2probe'));
    ws.on('message', (buf) => {
      const d = buf.toString();
      if (d === '3probe') return ws.send('5');
      if (!d.startsWith('42')) return;
      const [ev, payload] = JSON.parse(d.slice(2));
      if (ev === 'requestClientInfo') {
        ws.send('42' + JSON.stringify(['message', { type: 'requestClientInfoResponse',
          data: { game_id: 'hero', server_id: 'local', user_id: userId, session_id: `sess-${userId}` } }]));
      } else if (ev === 'clientRegistered') {
        client.registered = true; log(`  [${name}] registrado como user=${userId}`); resolve(client);
      } else if (ev === 'message' && payload && payload.type) {
        client.pushes.push(payload.type); log(`  [${name}] recebeu push: ${payload.type}`);
      }
    });
    ws.on('error', reject);
  });
}

const wait = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const USER_A = 1001, USER_B = 1002;
  log('Conectando dois clientes...');
  const [a, b] = await Promise.all([connectClient('A', USER_A), connectClient('B', USER_B)]);
  await wait(200);

  const health = JSON.parse(await get('/health'));
  log(`Health: ${health.sockets} sockets, ${health.users} users`);

  let failures = 0;
  const check = (cond, msg) => { log(`${cond ? 'PASS' : 'FAIL'} ${msg}`); if (!cond) failures++; };

  // Teste 1: push so pro user A (ex.: sussurro dirigido)
  log('\n[teste 1] push syncGame -> so user A');
  await post('/push', { user_id: USER_A, type: 'syncGame' });
  await wait(200);
  check(a.pushes.includes('syncGame'), 'A recebeu syncGame');
  check(!b.pushes.includes('syncGame'), 'B NAO recebeu (isolamento por user)');

  // Teste 2: broadcast aos dois (ex.: mensagem publica de guilda)
  log('\n[teste 2] syncGameAndGuild -> A e B (chat de guilda)');
  await post('/push', { user_id: USER_A, type: 'syncGameAndGuild' });
  await post('/push', { user_id: USER_B, type: 'syncGameAndGuild' });
  await wait(200);
  check(a.pushes.includes('syncGameAndGuild'), 'A recebeu syncGameAndGuild');
  check(b.pushes.includes('syncGameAndGuild'), 'B recebeu syncGameAndGuild');

  // Teste 3: push a user sem socket -> delivered 0
  log('\n[teste 3] push a user offline');
  const r = await post('/push', { user_id: 9999, type: 'syncGame' });
  check(JSON.parse(r.body).delivered === 0, 'user offline -> delivered=0');

  a.ws.close(); b.ws.close();
  log(`\n${failures === 0 ? 'TODOS OS TESTES PASSARAM' : failures + ' FALHA(S)'}`);
  process.exit(failures === 0 ? 0 : 1);
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
