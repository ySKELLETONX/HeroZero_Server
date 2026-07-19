# Hero Zero — Servidor Emulado (Engenharia Reversa)

[English](README.md) | **Português (BR)**

Projeto de engenharia reversa do jogo **Hero Zero** (Playata GmbH), construindo um
servidor privado compatível com o cliente HTML5 oficial.

> ⚠️ Fins educacionais / servidor privado. O cliente e todos os assets do jogo são
> propriedade da Playata GmbH. Não redistribua assets originais.

![Hero Zero rodando no servidor local](docs/GAME.png)

## Visão geral

- **Servidor do jogo**: Laravel (PHP 8.3) implementando o protocolo HTTP oficial —
  `POST /request.php` com actions em form-urlencoded, envelope JSON `{ data, error }`
  e assinatura `auth = md5(action + salt + user_id)`.
- **Actions**: ~89 actions do cliente implementadas (login, missões, duelos,
  treino, guildas e chat de guilda, eventos, cassino, esconderijo, loja, boosters,
  ligas...).
- **Painel admin**: app Laravel separado para contas, inventário, missões, eventos,
  guildas e mensagens de broadcast.
- **Banco de dados**: MySQL 8.4 (Docker) com dados de seed.
- **Cliente desktop**: o cliente Steam/NW.js dá boot contra o servidor via
  `steam.php` (o jogo em si vem do CDN oficial).

## Estrutura do repositório

```
HeroZero/
├── server-laravel/     # Servidor do jogo (Laravel, porta 8000)
│   ├── actions/        # Um handler por action do cliente (89 arquivos)
│   └── data/           # Fixtures de resposta capturadas do jogo real
├── admin-laravel/      # Painel admin (Laravel, porta 8001)
├── docs/
│   ├── PROTOCOL.md         # Protocolo HTTP decodificado (auth, login, actions)
│   ├── RESPONSE_SCHEMA.md  # 185 campos raiz de resposta mapeados
│   ├── SERVER_DESIGN.md    # Arquitetura do servidor
│   ├── DESKTOP_CLIENT.md   # Boot do cliente Steam/NW.js
│   └── PRODUCTION_AUDIT.md # Cobertura, testes, bloqueadores de release
└── tools/              # Scripts de RE (extração de HAR, geração de fixtures)
```

## Como rodar

Requisitos: PHP 8.3+, Composer, Docker (MySQL 8.4).

```bash
# Banco (MySQL 8.4 na porta 3308, database "herozero")
docker start herozero-db   # ou crie seu próprio container e rode o seed

# Servidor do jogo
cd server-laravel
composer install
php artisan serve --port=8000

# Painel admin
cd admin-laravel
composer install
php artisan serve --port=8001
```

Depois abra **http://127.0.0.1:8000/** no navegador (mantenha a aba em primeiro
plano — o preloader do cliente usa `requestAnimationFrame`, que os navegadores
congelam em abas de background).

Testar a API direto:

```bash
curl -X POST http://127.0.0.1:8000/request.php \
  -d "action=initEnvironment&user_id=0&auth=$(php -r 'echo md5("initEnvironmentGN1al3510");')"
```

## Notas de engenharia reversa

- O cliente é Haxe/OpenFL com nomes de classe preservados (~2.858 classes
  `com.playata.*`).
- Fluxo de boot: `initEnvironment → loginUser/autoLoginUser → initGame`.
- Constants oficiais extraídas do CDN (`constants_json.data`, zlib), incluindo a
  curva de XP oficial.
- Tráfego de sessão real capturado via HAR, incluindo o handshake do socket.io.
- Schema de resposta: 185 campos raiz mapeados em `docs/RESPONSE_SCHEMA.md`
  (89 validados contra capturas).

## Status

- [x] Protocolo HTTP totalmente decodificado e implementado
- [x] Login, personagem, missões, treino, duelos, guildas (+ chat), eventos, cassino, loja
- [x] Progressão de zona/story dungeon com curva de XP oficial
- [x] Painel admin
- [x] Boot do cliente desktop (Steam)
- [ ] Protocolo do socket em tempo real (handshake mapeado, eventos de gameplay pendentes)
- [ ] Campos de resposta ainda não validados (ver `docs/RESPONSE_SCHEMA.md`)
