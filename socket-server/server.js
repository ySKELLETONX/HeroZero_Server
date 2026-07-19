'use strict';
/*
 * Hero Zero — servidor de socket em tempo real.
 *
 * Reimplementa o SocketTransportLayer oficial (Engine.IO v2 / Socket.IO v2,
 * exatamente como capturado em reverse/br31.herozerogame.com.har e documentado
 * em docs/PROTOCOL.md). O cliente HTML5/desktop conecta aqui quando
 * clientVars.urlSocketServer aponta pra este processo; se cair, o cliente usa o
 * fallback de polling (getGuildLog/sync_states) — este servidor so adiciona push
 * imediato, nao e bloqueador.
 *
 * Fluxo:
 *   1. GET /socket.io/?EIO=2&transport=polling  -> pacote Engine.IO "open"
 *   2. upgrade WS  GET /socket.io/?EIO=2&transport=websocket&sid=...
 *   3. 2probe -> 3probe -> 5 (upgrade) -> 40 (connect ns "/")
 *   4. server 42["requestClientInfo",{socket_id}]
 *      client 42["message",{type:"requestClientInfoResponse",data:{user_id,session_id,...}}]
 *      server 42["clientRegistered",{}]  -> socket vinculado ao user_id
 *   5. push:  42["message",{type:"syncGame"|"syncGameAndGuild"|"syncFriendBar"}]
 *
 * Push HTTP (so localhost, protegido por token):
 *   POST /push  {"user_id":123,"type":"syncGameAndGuild"}   header X-Push-Token
 */

const http = require('http');
const crypto = require('crypto');
const { WebSocketServer } = require('ws');

const HOST = process.env.HZ_SOCKET_HOST || '127.0.0.1';
const PORT = parseInt(process.env.HZ_SOCKET_PORT || '8090', 10);
const PUSH_TOKEN = process.env.HZ_SOCKET_TOKEN || 'local-dev-token';
const PING_INTERVAL = 25000;
const PING_TIMEOUT = 60000;
const VALID_PUSH_TYPES = new Set(['syncGame', 'syncGameAndGuild', 'syncFriendBar']);

// sid -> { ws, userId, sessionId, alive } ; userId -> Set<sid>
const sockets = new Map();
const byUser = new Map();

const log = (...a) => console.log(new Date().toISOString(), ...a);
const genSid = () => crypto.randomBytes(15).toString('base64').replace(/[+/=]/g, '').slice(0, 20);

// ---- Engine.IO v2 helpers ---------------------------------------------------
// Payload de polling: sequencia de "<len>:<data>" (len = nº de chars UTF-8).
function encodePayload(packets) {
  return packets.map((p) => `${[...p].length}:${p}`).join('');
}
// Frame Engine.IO sobre WS: <tipo EIO><resto>. message=4; ping=2; pong=3; upgrade=5.
const eioMessage = (sioPacket) => `4${sioPacket}`;         // Engine.IO "message"
const sioEvent = (name, obj) => `2${JSON.stringify([name, obj])}`; // Socket.IO "event" (42)

function sendEio(ws, frame) {
  if (ws && ws.readyState === ws.OPEN) ws.send(frame);
}
function sendEvent(ws, name, obj) {
  sendEio(ws, eioMessage(sioEvent(name, obj)));
}

// ---- HTTP server (polling handshake + push) ---------------------------------
const server = http.createServer((req, res) => {
  const url = new URL(req.url, `http://${req.headers.host}`);

  if (url.pathname === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    return res.end(JSON.stringify({ ok: true, sockets: sockets.size, users: byUser.size }));
  }

  // Push interno: Laravel chama isto para empurrar um sync a um user.
  if (url.pathname === '/push' && req.method === 'POST') {
    if (req.headers['x-push-token'] !== PUSH_TOKEN) {
      res.writeHead(403); return res.end('forbidden');
    }
    let body = '';
    req.on('data', (c) => { body += c; if (body.length > 1e4) req.destroy(); });
    req.on('end', () => {
      let payload;
      try { payload = JSON.parse(body || '{}'); } catch { res.writeHead(400); return res.end('bad json'); }
      const userId = parseInt(payload.user_id, 10);
      const type = String(payload.type || '');
      if (!userId || !VALID_PUSH_TYPES.has(type)) { res.writeHead(422); return res.end('bad params'); }
      const delivered = pushToUser(userId, type);
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ delivered }));
    });
    return;
  }

  // Engine.IO v2 polling handshake (transport=polling, sem sid ainda).
  if (url.pathname === '/socket.io/' && (url.searchParams.get('transport') === 'polling')) {
    const sid = genSid();
    sockets.set(sid, { ws: null, userId: 0, sessionId: '', alive: true });
    const open = `0${JSON.stringify({ sid, upgrades: ['websocket'], pingInterval: PING_INTERVAL, pingTimeout: PING_TIMEOUT })}`;
    res.writeHead(200, {
      'Content-Type': 'text/plain; charset=UTF-8',
      'Access-Control-Allow-Origin': req.headers.origin || '*',
      'Access-Control-Allow-Credentials': 'true',
    });
    return res.end(encodePayload([open]));
  }

  res.writeHead(404); res.end('not found');
});

// ---- WebSocket upgrade (transport=websocket&sid=...) ------------------------
const wss = new WebSocketServer({ noServer: true });

server.on('upgrade', (req, socket, head) => {
  const url = new URL(req.url, `http://${req.headers.host}`);
  const sid = url.searchParams.get('sid');
  if (url.pathname !== '/socket.io/' || !sid || !sockets.has(sid)) {
    socket.write('HTTP/1.1 400 Bad Request\r\n\r\n'); return socket.destroy();
  }
  wss.handleUpgrade(req, socket, head, (ws) => onWsOpen(ws, sid));
});

function onWsOpen(ws, sid) {
  const entry = sockets.get(sid);
  if (!entry) return ws.close();
  entry.ws = ws;
  entry.alive = true;

  ws.on('message', (buf) => onWsMessage(ws, sid, buf.toString()));
  ws.on('close', () => cleanup(sid));
  ws.on('error', () => cleanup(sid));

  // Timeout de ping: cliente EIO v2 deve mandar "2" a cada pingInterval.
  entry.pingTimer = setInterval(() => {
    if (!entry.alive) { ws.terminate(); return cleanup(sid); }
    entry.alive = false;
  }, PING_INTERVAL + PING_TIMEOUT);
}

function onWsMessage(ws, sid, data) {
  const entry = sockets.get(sid);
  if (!entry) return;
  const type = data.charAt(0);

  if (type === '2') {                     // Engine.IO ping (com ou sem "probe")
    entry.alive = true;
    if (data === '2probe') {              // sondagem de upgrade
      sendEio(ws, '3probe');
    } else {
      sendEio(ws, '3');                   // pong normal
    }
    return;
  }
  if (type === '5') {                     // upgrade confirmado -> conecta ns "/"
    sendEio(ws, '40');
    // Pede a identificacao do cliente (mesma ordem do server oficial).
    sendEvent(ws, 'requestClientInfo', { socket_id: sid });
    return;
  }
  if (type === '4') {                     // Engine.IO "message" = Socket.IO packet
    return onSioPacket(ws, sid, data.slice(1));
  }
  // 1 = close, 6 = noop: ignora.
}

function onSioPacket(ws, sid, packet) {
  const sioType = packet.charAt(0);
  if (sioType !== '2') return;            // so tratamos "event" (42)
  let arr;
  try { arr = JSON.parse(packet.slice(1)); } catch { return; }
  const [name, payload] = Array.isArray(arr) ? arr : [];
  if (name !== 'message' || !payload) return;

  if (payload.type === 'requestClientInfoResponse') {
    const d = payload.data || {};
    registerUser(sid, parseInt(d.user_id, 10) || 0, String(d.session_id || ''));
    sendEvent(ws, 'clientRegistered', {});
    log(`clientRegistered sid=${sid} user=${sockets.get(sid).userId}`);
  }
}

function registerUser(sid, userId, sessionId) {
  const entry = sockets.get(sid);
  if (!entry) return;
  entry.userId = userId;
  entry.sessionId = sessionId;
  if (userId > 0) {
    if (!byUser.has(userId)) byUser.set(userId, new Set());
    byUser.get(userId).add(sid);
  }
}

function pushToUser(userId, type) {
  const set = byUser.get(userId);
  if (!set) return 0;
  let n = 0;
  for (const sid of set) {
    const entry = sockets.get(sid);
    if (entry && entry.ws) { sendEvent(entry.ws, 'message', { type }); n++; }
  }
  if (n) log(`push user=${userId} type=${type} -> ${n} socket(s)`);
  return n;
}

function cleanup(sid) {
  const entry = sockets.get(sid);
  if (!entry) return;
  if (entry.pingTimer) clearInterval(entry.pingTimer);
  if (entry.userId && byUser.has(entry.userId)) {
    byUser.get(entry.userId).delete(sid);
    if (byUser.get(entry.userId).size === 0) byUser.delete(entry.userId);
  }
  sockets.delete(sid);
}

server.listen(PORT, HOST, () => {
  log(`Hero Zero socket server em http://${HOST}:${PORT} (Engine.IO v2 / Socket.IO v2)`);
});
