# Hero Zero — Real-time socket server

Node.js reimplementation of the official **SocketTransportLayer** (Engine.IO v2 /
Socket.IO v2), captured in `reverse/br31.herozerogame.com.har` and documented in
`docs/PROTOCOL.md`. It gives the client immediate push instead of relying only on
HTTP polling.

The socket only carries a lightweight *poke*: the server pushes a `message` with a
`type` the client translates into an HTTP re-sync (`syncGame`, `syncGameAndGuild`,
`syncFriendBar`). All heavy state stays on `request.php`. If the socket is down the
client silently falls back to polling — this is an enhancement, not a hard
dependency.

## Protocol flow

```
1. GET /socket.io/?EIO=2&transport=polling          -> Engine.IO "open" packet (sid)
2. WS upgrade /socket.io/?EIO=2&transport=websocket&sid=...
3. 2probe -> 3probe -> 5 (upgrade) -> 40 (connect namespace "/")
4. server 42["requestClientInfo",{socket_id}]
   client 42["message",{type:"requestClientInfoResponse",data:{user_id,session_id}}]
   server 42["clientRegistered",{}]                  -> socket bound to user_id
5. push  42["message",{type:"syncGameAndGuild"}]     (server -> client)
   keepalive: client "2" (ping) -> server "3" (pong) every 25s
```

## Run

```bash
cd socket-server
npm install
node server.js
```

Environment variables:

| var | default | meaning |
|---|---|---|
| `HZ_SOCKET_HOST`  | `127.0.0.1` | bind host |
| `HZ_SOCKET_PORT`  | `8090`      | bind port |
| `HZ_SOCKET_TOKEN` | `local-dev-token` | shared secret for the `/push` endpoint |

Then point the Laravel game server at it (so the client and the push helper know
the URL):

```bash
# server-laravel/.env  (or exported env)
HZ_SOCKET_URL=http://127.0.0.1:8090          # sent to the client as urlSocketServer
HZ_SOCKET_PUSH_URL=http://127.0.0.1:8090/push # used by app/HeroZero/SocketPush.php
HZ_SOCKET_TOKEN=local-dev-token               # must match the socket server
```

## Internal push API

```
POST /push          header X-Push-Token: <HZ_SOCKET_TOKEN>
body {"user_id":123,"type":"syncGameAndGuild"}
-> 200 {"delivered":<n sockets>}
```

`GET /health` returns `{ok, sockets, users}`.

## Test

`node test-client.js` simulates the official client end-to-end (handshake →
registration → push) and exits non-zero on any failure.
