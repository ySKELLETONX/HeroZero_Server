# Design do nosso servidor (cliente HTML5, build 257)

Objetivo: servidor próprio para o **cliente HTML5 atualizado** (`HeroZero.min.js`),
usando o **HeroZServer (Owryn/xReveres)** como referência de arquitetura/lógica e a
**captura HAR** (`tools/capture/`) como verdade do formato exato das respostas.

## Por que não usar o HeroZServer direto
Ele é feito para o **cliente Flash (SWF) via Ruffle** (`client_version=flash_123`,
CDN `hz-static`). Nós queremos o **cliente HTML5** (build 257, CDN `hz-static-2`),
que é uma versão de protocolo mais nova. A *lógica de jogo* é a mesma (os nomes de
campo do banco batem com a nossa RE do `DOCharacter`); muda o *boot/envelope* e alguns
campos novos.

## Arquitetura (adaptada do HeroZServer)

Pipeline por request (ver `Core::start` do HeroZServer):

```
request.php (entrada)
  → define constantes, autoload, Core::start(), echo Req::pack()

Core::start():
  1. Req::init, Config::init
  2. valida client_version (o NOSSO = build HTML5 257, não flash)
  3. valida auth = md5(action + "GN1al351" + user_id)   ← específico do HTML5
  4. DB::init, Cache::init
  5. tryLoadPlayer(): Player::findBySSID(user_id, user_session_id)
  6. action: se exige login e não há player → erro
  7. roteia action → request/<action>.req.php  (classe \Request\<action>)
  8. $request->__request($player)
  9. $player->__endRequest(); salva records "sujos"; sync socket
```

### Envelope de resposta (`Req::pack`) — igual ao nosso
```json
{ "data": { ...campos..., "server_time": <int>, "time_correction": <float> },
  "error": "" }
```

### Auth
- **HTML5** manda `auth = md5(action + "GN1al351" + user_id)` (nosso `Protocol::verifyAuth`)
  **e** `user_id` + `user_session_id` (sessão). Validar os dois.
- HeroZServer (Flash) só usa sessão; o gate dele é `client_version`.
- Senha (ref. HeroZServer): `sha1(SALT + md5(pass) + pass)`.

### Ações que NÃO exigem login
`initEnvironment`, `initGame`, `loginUser`, `autoLoginUser`, `registerUser`,
`createCharacter`, `checkCharacterName`, `gameReportError`, `resetUserPassword`.

## Fluxo de boot (do nosso capture HTML5)
1. `initEnvironment` → `{ version_check:"ok", extendedConfig{75}, textures[2], time_correction, server_time }`
   **version_check e textures são obrigatórios** (senão o preloader do cliente trava).
2. `autoLoginUser` (sessão salva) → **jogo inteiro** (68 chaves: constants, user, character…)
   ou `loginUser` (email/senha) → cria sessão.
3. `initGame` → `{ constants{128}, extendedConfig{75}, leaderboard_server_selection_data{19}, … }`

Estruturas exatas: `tools/capture/*.json` + `docs/DOCharacter.fields.txt`.

## Estrutura de pastas proposta (nosso `server/`)
```
server/
  request.php              # entrada (já existe)
  lib/
    Core.php               # pipeline (novo, ref. HeroZServer)
    Config.php             # config central (novo)
    DB.php                 # PDO + query builder (novo)
    Protocol.php           # auth md5 + salt (já existe)
    Response.php / Req.php  # envelope (já existe)
    Router.php             # action → handler (já existe; evoluir p/ classes)
    Replay.php             # modo replay a partir do capture (já existe)
  request/                 # 1 arquivo por ação (padrão HeroZServer)
    initEnvironment.req.php, loginUser.req.php, createCharacter.req.php, ...
  class/                   # modelos: Player, Character, Guild, ... (ref. HeroZServer)
  data/                    # fixtures do capture (modo replay)
```

## Bloqueador aberto: boot do cliente HTML5 (~60%)
O cliente HTML5 (lime/OpenFL) trava em ~60% na aba de automação (background) porque o
preloader do lime depende de `requestAnimationFrame`, que o Chrome congela em abas
ocultas. **O cliente Flash via Ruffle bootou na MESMA aba** (Ruffle não usa rAF do
mesmo jeito). Hipótese forte: **em navegador em primeiro plano o HTML5 passa dos 60%.**
→ **Teste decisivo:** abrir `http://localhost:8000/` (nosso replay) numa aba em
primeiro plano e ver se avança. Isso confirma se o caminho HTML5 é viável antes de
investir na reescrita.

## Roadmap
1. [ ] Confirmar boot do cliente HTML5 em foreground (destrava tudo).
2. [ ] Reestruturar `server/` para o pipeline Core/Config/DB + `request/*.req.php`.
3. [ ] Persistência: adaptar o schema do HeroZServer (`hzpriv.sql`) ao nosso modelo.
4. [ ] Portar a lógica das ações (quest/duel/training/...) do HeroZServer p/ o envelope HTML5.
5. [ ] Socket (tempo real) — depois do fluxo HTTP.
```
