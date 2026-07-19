# Cliente Desktop (Steam / NW.js) — Engenharia Reversa

Análise do executável **`Hero Zero.exe`** (`Hero Zero - Client Desktop/`) e o que ele
acrescenta sobre o build web já mapeado (`server/public/js/HeroZero.min.js`).

## TL;DR

O `.exe` **não contém a lógica do jogo**. É uma casca [NW.js](https://nwjs.io/)
(Chromium + Node) que, no arranque, baixa a configuração de boot de um endpoint
`steam.php`, injeta os scripts do jogo a partir da CDN e embute o runtime
Haxe/OpenFL (`lime.embed`). O jogo em si é o mesmo build da versão web.

Consequência prática: para melhorar **nosso** servidor, o cliente desktop
contribui com **uma única peça nova** — o contrato de boot `steam.php` (a web monta
esse config inline; o desktop busca por HTTP). Essa peça foi implementada
(`server/steam.php`) e o `.exe` já pode bootar contra o nosso servidor.

## Empacotamento

| Componente | Papel |
|---|---|
| `nw.dll` (108 MB), `node.dll`, `libGLESv2.dll`, `d3dcompiler_47.dll` | Runtime NW.js/Chromium/ANGLE |
| `package.json` | Manifesto NW.js: `main: index.html`, janela 1120×755, id `com.playata.herozero.steam` |
| `index.html` | Página hospedeira; carrega GSAP + socket.io + `desktop.js` + `main.js` (módulo ES) |
| `assets/js/main.js` | **Sequência de boot** (fetch steam.php → inject → lime.embed) |
| `assets/js/desktop.js` | Utilidades chamadas pelo jogo: tema claro/escuro, HD textures, cookies |
| `assets/js/fetch-inject.js` | Injeta scripts/estilos baixados no DOM |
| `lib/greenworks-win32.node`, `steam_api.dll`, `sdkencryptedappticket.dll` | Integração Steam (achievements, ticket de sessão) via [greenworks](https://github.com/greenheartgames/greenworks) |
| `assets/` | Efeitos locais (`.pex`, `.aie`), sons, spine; **a arte do jogo vem da CDN**, não daqui |

## Sequência de boot (o achado central)

Em `assets/js/main.js`:

```js
var url = 'https://s1.herozerogame.com';           // servidor de boot (oficial)
fetch(url + "/steam.php")
    .then(r => r.json())
    .then(data => {
        config = data;
        config.clientVars.urlCDN = config.urlCDN;  // repassa CDN ao runtime
        return fetchInject(config.scripts);        // injeta libs + jogo
    })
    .then(() => {
        if (HDTextures) config.clientVars["textureResolution"] = 945;
        window.lime.embed(
            config.clientName, 'appClient', gameWidth, gameHeight,
            { height: gameHeight, rootPath: config.root, parameters: config.clientVars }
        );
    });
```

### Contrato de `steam.php` (reconstruído a partir do uso em `main.js`)

```jsonc
{
  "scripts":    ["<url>", "..."],   // scripts a injetar (libs OpenFL/GSAP + jogo)
  "clientVars": { /* ... */ },      // parâmetros do runtime (mesma shape do web)
  "urlCDN":     "https://.../",     // base do CDN; vira clientVars.urlCDN
  "root":       "https://.../assets/html5",  // rootPath dos assets
  "clientName": "HeroZero.min"      // módulo lime a embutir (nome do build na CDN)
}
```

`clientName` ser dinâmico é como a Playata entrega **builds novos sem atualizar o
`.exe`**: o executável é estável; o jogo e sua versão vêm da CDN a cada boot.

### Diferença vs. o build web

| Aspecto | Web (`server/public/index.html`) | Desktop (`.exe`) |
|---|---|---|
| Origem do config | `clientVars` **inline** no HTML | **fetch** `steam.php` → JSON |
| Origem do jogo | `<script src="/js/HeroZero.min.js">` fixo | `fetchInject(config.scripts)` dinâmico |
| Nome do módulo | `"HeroZero.min"` fixo | `config.clientName` da CDN |
| Plataforma | `platform: "standalone"` | Steam (greenworks; ticket p/ SSO) |
| HD textures | — | cookie `hd_textures` → `textureResolution: 945` |
| Janelas externas | — | `new-win-policy` abre fórum/suporte no browser |

Fora isso, **o protocolo do jogo é idêntico** ao já documentado em
`docs/PROTOCOL.md` e `docs/RESPONSE_SCHEMA.md`: `POST /request.php`,
`auth = md5(action + "GN1al351" + user_id)`, envelope `{ data, error }`. Toda a
cobertura de actions do nosso servidor vale para o desktop sem mudanças.

## Autenticação Steam

O desktop pode logar via Steam em vez de user/senha:

- `greenworks` obtém um **encrypted app ticket** (`sdkencryptedappticket.dll`).
- O ticket iria em `clientVars.ssoInfo` / no fluxo `registerUserSSO` (`platform: "steam"`).
- Classe relevante no build: `com.playata.framework.platform.settings.SteamPlatformSettings`.

**Não conseguimos validar** esse ticket fora da rede da Playata (exige as chaves
da Steam Web API do app). Por isso o nosso `steam.php` usa `platform: "standalone"`
e reaproveita o caminho de login já emulado (`autoLoginUser` por `userId`/`sessionId`,
ou `registerUserSSO` como convidado). Ver `server/actions/registerUserSSO.php`.

## O que foi implementado para o nosso servidor

1. **`server/steam.php`** — responde o contrato de boot acima. Monta `clientVars`
   com a mesma shape do `index.html` web, aponta `urlRequestServer` para a origem
   da requisição e lista os scripts locais (jQuery, `standalone.js`, `HeroZero.min.js`).
   Aceita `?uid=&sid=` opcionais (default: convidado skelletonx `25328`).
2. **`server/router.php`** — nova rota `GET /steam.php`.
3. **`assets/js/main.js` (do `.exe`)** — `url` de boot agora aponta para
   `http://127.0.0.1:8000` (original comentado; sobrescrevível via env `HZ_BOOT_URL`).

## Como rodar o `.exe` real contra o nosso servidor

```powershell
# 1) suba o servidor
cd I:\SKELLETONX\HeroZero\server
I:\Xampp\php\php.exe -S 127.0.0.1:8000 -t public router.php

# 2) rode o executável (main.js já aponta p/ 127.0.0.1:8000)
cd "I:\SKELLETONX\HeroZero\Hero Zero - Client Desktop"
& ".\Hero Zero.exe"
```

Para apontar para outro host sem reeditar `main.js`: `HZ_BOOT_URL=http://IP:PORTA`.

Teste do endpoint isolado:

```bash
curl -s http://127.0.0.1:8000/steam.php | php -r 'print_r(json_decode(file_get_contents("php://stdin"),true));'
```

## Limitações conhecidas

- **CORS/CSP**: os scripts do jogo são servidos pela nossa origem, então não há
  CORS. A arte ainda vem do CDN oficial da Akamai (não redistribuímos assets).
- **Socket**: `urlSocketServer` vazio — tempo real ainda não emulado (igual ao web).
- **SSO Steam real**: não validável off-network (ver acima).
- **Preloader ~60%**: mesmo sintoma investigado no boot web (ver README) — provável
  estado de boot dependente de compositing WebGL/valor de `clientVars`.
