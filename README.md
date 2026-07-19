# Hero Zero — Servidor Emulado (Engenharia Reversa)

Projeto de engenharia reversa do jogo **Hero Zero** (Playata GmbH) para criar um
servidor privado compatível com o cliente oficial.

> ⚠️ Fins educacionais / servidor privado. O cliente e assets são propriedade da
> Playata GmbH. Não distribua assets originais.

## Estrutura

```
HeroZero/
├── docs/
│   └── PROTOCOL.md        # Protocolo decodificado (HTTP, auth, login, actions)
├── tools/
│   ├── HeroZero.min.js    # Cliente oficial baixado (build 252) — p/ análise
│   ├── allclasses.txt     # 2858 classes com.playata.* extraídas
│   └── info.txt           # Página de embarque original (clientVars, URLs)
└── server/                # Servidor emulado em PHP
    ├── request.php        # Entrada (equivalente ao request.php original)
    ├── lib/
    │   ├── Protocol.php    # Salt, build number, verificação de auth
    │   ├── Response.php    # Envelope { data, error }
    │   └── Router.php      # Roteia action -> handler
    └── actions/
        ├── initEnvironment.php
        ├── autoLoginUser.php
        ├── loginUser.php
        └── initGame.php
```

## Estado atual

See `docs/PRODUCTION_AUDIT.md` for the current HAR coverage, production controls,
test results and remaining release blockers.

- [x] Cliente obtido e analisado (Haxe/OpenFL, nomes de classe preservados)
- [x] Protocolo HTTP decodificado (POST form-urlencoded, envelope JSON)
- [x] Assinatura `auth = md5(action + "GN1al351" + user_id)` descoberta
- [x] Fluxo de boot/login mapeado (initEnvironment → login → initGame)
- [x] Esqueleto do servidor PHP (roteador + auth + envelope)
- [x] Servidor rodando e testado (PHP 8.0 do XAMPP, todas as actions respondem)
- [x] Envelope de `loginUser`/`autoLoginUser` decodificado (`{ user, user_geo_location }`)
- [x] `DOUser` (10 campos) e `DOCharacter` (103 campos) mapeados — ver `docs/DOCharacter.fields.txt`
- [ ] `initGame` completo (constants, missions, inventory, world, guild...) — **RE em andamento**
- [ ] Estrutura de `extendedConfig`/`textures` do `initEnvironment`
- [ ] Protocolo do socket (tempo real)
- [ ] Persistência (banco de dados)

## Como rodar (PHP do XAMPP em `I:\Xampp\php`)

O servidor serve **a página de embarque local + o cliente + a API** na mesma origem
(sem CORS). Suba com o router:

```powershell
cd I:\SKELLETONX\HeroZero\server
I:\Xampp\php\php.exe -S 127.0.0.1:8000 -t public router.php
```

Depois abra **http://127.0.0.1:8000/** no navegador (Chrome/Edge), em uma aba em
**primeiro plano** (o preloader do cliente usa `requestAnimationFrame`, que o
navegador congela em abas de background).

- Página de embarque local: `server/public/index.html` (aponta `urlRequestServer`
  para `/request.php`; assets de arte vêm do CDN oficial da Akamai).
- Cliente + libs: `server/public/js/` (cópia local do build 252).
- API: `POST /request.php` → roteada pelo `router.php` para `server/request.php`.

Testar a API direto (sem cliente):

```bash
# auth = md5(action + "GN1al351" + user_id)
curl -X POST http://127.0.0.1:8000/request.php \
  -d "action=initEnvironment&user_id=0&auth=$(php -r 'echo md5("initEnvironmentGN1al3510");')"
```

### Estado do boot do cliente
O cliente **embarca e carrega todos os assets** (splash do Hero Zero aparece). O
preloader ainda **empaca em ~60%** antes de chamar `initEnvironment` — sob
investigação (provável estado de boot que depende de um valor de `clientVars` ou de
compositing WebGL real). A API já responde corretamente ao fluxo esperado.

## Próximos passos

1. Capturar tráfego real de uma sessão (validar formato exato das respostas).
2. Preencher `extendedConfig`/`textures` do `initEnvironment`.
3. Mapear a estrutura de `data` do `loginUser`/`initGame` (data-objects DO*).
4. Decodificar o protocolo do socket.
