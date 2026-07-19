# Hero Zero — Protocolo (Engenharia Reversa)

> Extraído de `HeroZero.min.js` (build 252). Cliente é **Haxe + OpenFL/Lime** compilado
> com Google Closure. Os nomes de classe `com.playata.*` estão **preservados**.

## Arquitetura de servidores

| Peça | URL (mundo br31) | Papel |
|------|------------------|-------|
| API HTTP | `https://br31.herozerogame.com/request.php` | Ações do jogo (síncrono, request/response) |
| Socket | `https://sa1a-sock1.herozerogame.com` | Tempo real (push, PvP, chat) |
| CDN | `https://hz-static-2.akamaized.net/` | Cliente JS + assets |

**Cliente é HTML5** (lime/OpenFL), embutido em `HeroZero.min.js` (o runtime `lime`
vai junto). O embed é:

```js
lime.embed("HeroZero.min", "appClient", 1120, 755,
  { rootPath: "https://hz-static-2.akamaized.net/assets/html5",
    parameters: clientVars });
```

`clientVars` real capturado da página de embarque (`tools/1 nova mensagem – Hero Zero.html`,
mundo **br31**, sessão de convidado não logado):

```js
{ applicationTitle: "Hero Zero",
  urlPublic:        "https://br31.herozerogame.com/",
  urlRequestServer: "https://br31.herozerogame.com/request.php",
  urlSocketServer:  "https://sa1a-sock1.herozerogame.com",
  urlCDN:           "https://hz-static-2.akamaized.net/",
  userId: "0", userSessionId: "0",          // 0/0 = ainda não logado
  testMode: "false", debugRunTests: "false",
  registrationSource: "ref=;subid=;lp=;", startupParams: "", ssoInfo: "",
  platform: "standalone", server_id: "br31", default_locale: "pt_BR",
  uniqueId: "br311783825603",               // = server_id + timestamp
  blockRegistration: "false", isFriendbarSupported: "true" }
```

> Para conectar o cliente oficial ao nosso servidor: servir esta página localmente,
> trocar `urlRequestServer` → `http://127.0.0.1:8000/request.php` e carregar o
> `HeroZero.min.js` local. Assets de arte ficam no CDN (`rootPath`) — o cliente
> **boota e faz o fluxo de request.php** mesmo sem espelhar os assets.
>
> Cópia local completa disponível em `tools/1 nova mensagem – Hero Zero_files/`
> (HeroZero.min.js, jQuery, GSAP TweenMax, `standalone.js` = versão minificada do
> `info2.txt`).

## Protocolo HTTP — `request.php`

- **Método:** POST
- **Content-Type:** `application/x-www-form-urlencoded`
- **Encoding:** cada campo do request vira uma variável de form. Valor `null` → `0`.
  (`FlashUriData.load` → `requestData.get_asURLVariables()`)

### Campos SEMPRE presentes (`JsonActionRemoteRequest.addRequestParams`)

| Campo | Origem | Observação |
|-------|--------|------------|
| `action` | nome da ação | ex.: `login`, `syncTowerEvent`, ... |
| `user_id` | `config.userId` | "0" quando não logado |
| `user_session_id` | sessão | "0" quando não logado |
| `client_version` | `config.clientVersion` | definido em runtime |
| `build_number` | constante | **252** |
| `auth` | assinatura | **`md5(action + "GN1al351" + user_id)`** |
| `rct` | serverConnection.sessionConnectionType | int |
| `keep_active` | constante | `1` (true) |
| `device_id` | `deviceInfo.uniqueId` | se ausente |
| `device_type` | plataforma | se ausente |
| ...+ | dados específicos da ação | |

### ⚠️ Assinatura (`auth`)

```
auth = md5( action + "GN1al351" + user_id )
```

Salt secreto embutido no cliente: **`GN1al351`**.
`CryptoUtil.md5Hash(x) = MD5(x)` (MD5 padrão).

### Resposta (`onRequestCompleted`)

JSON:
```json
{ "data": { ... }, "error": "" }
```

- `error == ""`  → sucesso; usar `data`.
- `error != ""`  → string de erro; `data` ignorado.
- Falha de rede → `response_errorIO`.
- JSON inválido → `errorInvalidResponseFormat`.

O cliente valida que ambos os campos `data` e `error` existem, senão dá erro.

## Config padrão do cliente (defaultConfig)

```
game_id: "hero"
auth (build key): "q2iAElu"     (não é o salt do request; é chave de build)
urlPlatformRequest: https://www.herozerogame.com/platform/requestData.php
build_number: 252
```

## Classes-chave (para aprofundar)

- `com.playata.application.request.JsonActionRemoteRequest` — monta/assina requisições
- `com.playata.application.request.UserActionRequest`
- `com.playata.framework.network.request.http.HTTPTransportLayer` / `HTTPTransportPackage`
- `com.playata.framework.network.request.socket.SocketTransportLayer` — protocolo do socket (A MAPEAR)
- `com.playata.application.request.ApplicationSocketHandler`
- 2858 classes `com.playata.*` no total → `tools/allclasses.txt`

## Página de embarque (wrapper) — `tools/info2.txt`

JS da página que hospeda o cliente (`appClient`). Cola de ads/tracking/pagamento/push,
mas define contratos úteis fora do `request.php`:

### Cookies de sessão (`setSessionCookie`)
- `hero_session_data` = `session_id=<sid>,user_id=<uid>` (host-only, 1 ano)
- `hero_platform_data_<platform>` = `session_id=<sid>,user_id=<uid>,server_id=<sid>`
  (`platform` = `standalone` se vazio; topDomain `herozerogame.com`)
- `hero_friendbar_<platform>` = `show_friendbar=true|false`
- `landing_page` (quando `registrationSource` contém `lp=`)

### Seleção / troca de mundo
- **`POST /requestPlatformSessionData.php`** — endpoint **separado** do `request.php`.
  Params: `mode=html`, `ignore_allowed_locales`, `locale`, + campos hidden do form.
  Retorna **HTML** com a lista de servidores.
- `changeServer(sessionData)`: form posta `existing_user_id` + `existing_user_session_id`
  para a URL do mundo destino (mesmos campos do `autoLoginUser`).
- Conta de convidado: `appClient.callbackRegisterGuestAccount()` (callback JS→cliente).

## Fluxo de boot / login (decodificado)

Sequência na inicialização do cliente:

1. **`initEnvironment`** — 1ª requisição, `data` vazio (`{}`).
   Resposta **real** (capturada, `tools/capture/01_initEnvironment.json`, 1.4 MB):
   ```json
   { "version_check": "ok",
     "extendedConfig": { ...75 chaves de config... },
     "textures": [ {resolution, format:"ktx2", hashes:{...}}, {...} ],
     "time_correction": <float>, "server_time": <int> }
   ```
   - `textures`: **array** de 2 blocos (resolução 630 e 945), cada um com `hashes`
     `arquivo -> {hash, size}` dos atlas de UI/fontes (`ui.ktx2`, `fonts.png`,
     `tower_event.ktx2`, ...). O cliente baixa esses arquivos do CDN por hash.
     **⚠️ Sem esse bloco o preloader trava** — o cliente não carrega a UI.
   - `version_check` deve ser `"ok"` (senão trata como cliente desatualizado).
   Erros tratados: `errRequestMaintenance`, `errRequestOutdatedClientVersion`.

2. Se existe sessão salva → **`autoLoginUser`**:
   ```json
   { "existing_session_id": "...", "existing_user_id": "...",
     "client_id": "<uid>", "app_version": <int> }
   ```
   Senão, login SSO/convidado ou **`loginUser`**.

3. **`loginUser`** (email/senha):
   ```json
   { "email": "...", "password": "...",
     "platform": "", "platform_user_id": "",
     "client_id": "<uid>", "app_version": <int>,
     "device_info": "<json string>" }
   ```
   Resposta de sucesso → `application.onLogin(data, isLoginUser)`.

4. **`initGame`** — carrega o estado do jogo:
   ```json
   { "no_text": true, "locale": "pt_BR" }
   ```

### `device_info` (objeto JSON serializado)
```json
{ "language": "...", "pixelAspectRatio": <n>, "screenDPI": <n>,
  "screenResolutionX": <n>, "screenResolutionY": <n>,
  "touchscreenType": <n>, "os": "...", "version": "..." }
```

## Estrutura das respostas (data-objects `DO*`) — decodificado

Os data-objects não têm campos no construtor: são wrappers sobre um objeto dinâmico,
com getters tipados `getInt("chave")` / `getString(...)` / `getBoolean(...)` /
`getNumber(...)`. **A chave JSON é sempre `snake_case`.**

### Envelope do `loginUser` / `autoLoginUser`

`onLoginResponse → updateData` lê apenas duas chaves de topo:

```json
{ "user": { ...DOUser... },
  "user_geo_location": { ... } }
```

`refreshUser` persiste em cache: `user.id`, `user.session_id`, `user.email`.

#### `user` (DOUser, wrapper `User`)

| chave JSON | tipo | getter |
|-----------|------|--------|
| `id` | int | id |
| `session_id` | string | sessionId |
| `email` | string | email |
| `network` | string | platform |
| `locale` | string | locale |
| `registration_source` | string | registrationSource |
| `ts_creation` | int | creationTimestamp |
| `confirmed` | bool | isConfirmed |
| `premium_currency` | int | premiumCurrency |
| `blocked_premium_currency` | int | blockedPremiumCurrency |

### Envelope real do `autoLoginUser` (o jogo INTEIRO — 68 chaves)

Capturado (`tools/capture/03_autoLoginUser.json`). Ao contrário do `loginUser`
(que só devolve `user`), o `autoLoginUser` de uma sessão existente devolve o estado
completo. Chaves de topo principais:

`constants`(128), `user`(22), `character`(147), `inventory`(53),
`bank_inventory`(454), `quests`[3], `battle`, `battles`[1], `items`[22], `duel`,
`opponent`(29), `trainings`[4], `event_quest`, `treasure_event`, `tower_event_data`,
`current_goal_values`(287), `current_item_pattern_values`(59), `daily_bonus_lookup`,
`season_progress`, `streams_info`, `advertisment_info`, `ingame_notifications`,
`local_notification_settings`, `news`, `login_count`, `server_time`, ...

`user` (22 campos) inclui além dos 10 do DOUser: `app_version`, `device_type`,
`settings`(json), `trusted`, `network:"guest"` p/ conta de convidado, etc.

### Envelope real do `initGame`

Capturado (`tools/capture/02_initGame.json`, 1.48 MB):
```json
{ "constants": {128 chaves}, "extendedConfig": {75 chaves},
  "leaderboard_server_selection_data": {19 chaves},
  "time_correction": <float>, "server_time": <int> }
```
O personagem é aplicado via `refreshCharacter(data.character)` (vindo do login).

### Captura de referência
`tools/br30.herozerogame.com.har` (sessão real, mundo br30) → extraída para
`tools/capture/*.json` (52 pares request.php, ver `_index.txt`) via
`tools/extract_har.php`. Fixtures de boot em `server/data/` alimentam o **modo
replay** (`server/lib/Replay.php`): o servidor devolve as respostas reais para o
cliente bootar enquanto a lógica dinâmica não existe.
**Contêm dados da conta de convidado do dono — uso local, não publicar.**

#### `character` (DOCharacter) — **103 campos**

Lista completa em [`DOCharacter.fields.txt`](DOCharacter.fields.txt). Principais:

| chave JSON | tipo | descrição |
|-----------|------|-----------|
| `id` / `user_id` | int | id do personagem / dono |
| `name` / `gender` / `title` | string | identidade |
| `level` / `xp` | int/num | progressão |
| `game_currency` | num | moeda do jogo (coins) |
| `stat_base_stamina` / `stat_base_strength` / `stat_base_critical_rating` / `stat_base_dodge_rating` | int | atributos base |
| `stat_total_stamina` / `stat_total_strength` / `stat_total_critical_rating` / `stat_weapon_damage` | int | atributos totais |
| `quest_energy` / `max_quest_energy` / `active_quest_id` / `current_quest_stage` | int | missões |
| `duel_stamina` / `max_duel_stamina` / `honor` / `active_duel_id` | int | duelos/PvP |
| `training_energy` / `max_training_energy` / `active_training_id` | int | treino |
| `guild_id` | int | guilda |
| `league_points` / `league_stamina` / `max_league_stamina` | int | liga |
| `tutorial_flags` | string | flags de tutorial |
| `show_mask` / `show_cape` | bool | aparência |
| ...(ver arquivo completo — boosters, eventos, dungeon, worldboss, casino, slotmachine) | | |

### Actions catalogadas até agora
`initEnvironment`, `initGame`, `autoLoginUser`, `loginUser`, `loginUserSSO`,
`registerUserSSO`, `loginFriendBar`, `initCampaign`, `initPayment`,
`initConsumableOfferPayment`, `initVideoAdvertisment`, `syncTowerEvent`, ...
(muitas mais nas actions de `towerevent.*` e nos controllers).

## SocketTransportLayer — handshake real (capturado em `reverse/br31.herozerogame.com.har`)

Engine.IO v2 / Socket.IO v2 clássico, sobre `wss://sa1a-sock1.herozerogame.com/socket.io/`.

1. Handshake HTTP polling: `GET /socket.io/?EIO=2&time=<ms>&transport=polling` →
   corpo `0{"sid":"...","upgrades":["websocket"],"pingInterval":25000,"pingTimeout":60000}`
   (prefixo `0` = pacote Engine.IO `open`).
2. Upgrade: `GET wss://.../socket.io/?EIO=2&transport=websocket&sid=<sid>` (101 Switching Protocols).
3. Sobre o WS, frames são `<EIO packet type><Socket.IO packet type>[payload JSON]`:
   - `2probe` (cliente) → `3probe` (servidor) → `5` (cliente confirma upgrade)
   - `40` (servidor: socket.io `connect` OK, namespace `/`)
   - servidor → `42["requestClientInfo",{"socket_id":"<sid>"}]`
   - cliente → `42["message",{"type":"requestClientInfoResponse","data":{"game_id":"hero","server_id":"br31","user_id":<id>,"session_id":"<user_session_id>"}}]`
   - servidor → `42["clientRegistered",{}]` — a partir daqui o socket está vinculado à sessão HTTP
     (mesmo `user_id`/`session_id` usados em `request.php`), e o servidor pode empurrar eventos
     (chat de guilda, notificações) para esse socket específico.
   - keepalive: cliente `2` (ping) → servidor `3` (pong), a cada `pingInterval` (25s).

**Tipos de `message` que o cliente trata** (switch no bundle, logo após responder
`requestClientInfoResponse`) — o socket só carrega uma "poke", o cliente re-sincroniza via HTTP:

- `syncGame` → `set_pendingSyncGame(true)` + `onSynGame()` (re-fetch de estado no próximo poll)
- `syncGameAndGuild` → sync de jogo **e** `getGuildLog` (chat/log da guilda chega aos membros)
- `syncFriendBar` → atualiza a friend bar

**Implementação (`socket-server/`):** reimplementamos esse socket em Node.js (Engine.IO v2 /
Socket.IO v2) — handshake polling → upgrade WS → `requestClientInfo`/`clientRegistered` → registro
`user_id → socket`, com um endpoint interno `POST /push` que o Laravel chama (`app/HeroZero/SocketPush.php`)
para empurrar `syncGame`/`syncGameAndGuild`/`syncFriendBar` a um user. O `sendGuildChatMessage` já
dispara `syncGameAndGuild` aos outros membros da guilda (sussurro → só o destinatário). O cliente
recebe a URL via `clientVars.urlSocketServer` (env `HZ_SOCKET_URL`).

**Fallback preservado (ver `[[guild-chat-protocol]]`):** se o socket estiver desligado (ou
`urlSocketServer` vazio), o cliente cai no polling client-side de `getGuildLog`/`sync_states`
(`syncGame`/`updateGameSession`) — o mesmo caminho que o cliente oficial usa quando o socket está
indisponível. Push é uma melhoria de latência, não bloqueador funcional.

## Schema de resposta — campos de nível raiz

`docs/RESPONSE_SCHEMA.md` — lista COMPLETA (185 campos) de tudo que o client sabe ler na raiz de
`data` de qualquer resposta do `request.php` (classe `ta` no bundle, reusada por toda
`JsonActionRemoteRequest` — vale pra todas as actions, não só o boot). Gerado via regex em
`ta.prototype.get_*` no `HeroZero.min.js`. 89 campos já validados contra captura real; **96 nunca
apareceram em `tools/capture/` nem no HAR** — são a lista de risco: features que o client suporta
mas que no nosso `Router.php` provavelmente caem em `stateEcho.php` (passthrough genérico) sem
nunca terem sido testadas contra o shape real (ex.: `private_conversation*`, `league_opponents`,
`missed_league_fight*`, `sidekick*`, `guild_battle_*`/`guild_history_battle*`, `worldboss_*`,
`legendary_dungeon_run`, `season_rewards`, `speedserver_*`, `surveys`, `voucher_rewards`).

## Próximos passos de RE

1. [ ] Mapear todas as `action` names (login, fight, mission, shop, ...) e seus params
2. [x] Mapear protocolo do **SocketTransportLayer** (handshake + eventos) — ver seção acima
   e **implementado** em `socket-server/` (push real-time de `syncGame*`)
3. [x] Capturar tráfego real de uma sessão de convidado p/ validar formato — `reverse/br31.herozerogame.com.har`
4. [x] Mapear estrutura dos data-objects (`DO*`) de nível raiz — `docs/RESPONSE_SCHEMA.md` (89/185 validados)
5. [ ] Validar os 96 campos "nunca vistos" de `RESPONSE_SCHEMA.md` contra dado real (capturar sessões
   que toquem em ligas, batalhas de guilda, sidekicks, conversas privadas, worldboss etc.)
6. [ ] Descer um nível: mapear os getters de cada DO individual (ex. `$a`=character, `zo`=quest,
   `ch`=item, `Ac`=guild) — hoje só temos o nível raiz mapeado
