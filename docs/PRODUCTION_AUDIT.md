# Server production audit

Audit date: 2026-07-12. Client contract: HTML5 257, build 252.
Reference capture: `tools/br31.herozerogame.com.har`.

## Coverage

- 44 distinct `request.php` actions were found in the HAR.
- All 44 have explicit router entries.
- Core boot, account, character, quests, duels, shop, inventory sale, goals,
  messages, leaderboards, guild, hideout and preferences use live database state.
- `getStandalonePaymentOffers` and `getEventHubEntries` use captured global
  catalog/configuration shapes. Account fields are overlaid from the database.
- The training minigame lifecycle is persisted in the `training` table:
  offer selection, quest progress, quest reward claim, three star claims and completion.

## Automated checks performed

- HAR action coverage: 44/44 mapped.
- API smoke test: 44/44 returned a valid `{data,error}` envelope; no internal I/O errors.
- Guild contract: 69/69 fields read by the build-252 client are present.
- Browser boot: `initEnvironment`, `initGame`, `autoLoginUser`, payment offers and
  guild log loaded without JavaScript errors after the fixes.
- MySQL container reported healthy.
- PHP syntax checks passed for every changed PHP file.
- Invalid request signatures and invalid sessions are rejected.
- Production-mode checks: beta registration and client logging return 404,
  fake payments return `errPaymentNotAvailable`, and unknown actions return
  `errActionNotSupported`.

## Required production environment

Set at least:

```text
HZ_ENV=production
HZ_DB_HOST=<private database host>
HZ_DB_PORT=3306
HZ_DB_NAME=herozero
HZ_DB_USER=<restricted application user>
HZ_DB_PASS=<strong secret>
HZ_ALLOWED_ORIGIN=https://your-game-host.example
HZ_STRICT_AUTH=1
HZ_STRICT_SESSION=1
```

Do not set `HZ_ALLOW_FAKE_PAYMENTS=1` in production. A real payment provider and
verified webhook must credit premium currency; `initPayment` is intentionally
disabled in production until that integration exists.

## Release blockers

This repository is not yet suitable for a public, paid production launch because:

1. A real payment provider/webhook is not implemented.
2. ~~The PHP built-in server is development-only.~~ **Resolved** — `deploy/`
   provides a production stack: Nginx (reverse proxy + TLS via certbot + per-IP
   `limit_req` rate limiting + WebSocket proxy), a dedicated PHP-FPM pool, systemd
   units for the socket server and queue worker, a daily `mysqldump` backup with
   rotation, and an active healthcheck (web+socket+db) with webhook alerting.
   App-layer rate limiting (`throttle:game`/`throttle:auth`) adds defense-in-depth.
   See `deploy/README.md`.
3. Original game assets and trademarks require the appropriate distribution rights.

A private test deployment with payments disabled can use production mode now, and
the infrastructure blocker (#2) is closed. Blockers #1 and #3 remain.
