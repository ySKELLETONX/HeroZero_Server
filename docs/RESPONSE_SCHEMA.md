# Schema de resposta do request.php — campos de nivel raiz (classe `ta` no HeroZero.min.js)

Extraido via regex em `ta.prototype.get_*` de `server/public/js/HeroZero.min.js`
(build html5_257/252). Lista COMPLETA de todo campo que o client sabe interpretar na
raiz de `data` de QUALQUER resposta do `request.php` (o wrapper `ta` e reusado por toda
`JsonActionRemoteRequest`, entao isso vale pra TODAS as actions, nao so o boot).

- **tipo**: `Int`/`String`/`Boolean`/`StringVector`/`TypedObject` = primitivo/map cru;
  `DO` = wrapper de objeto unico (classe indicada); `ArrayOfDO` = colecao (`od`) de DOs.
- **validado**: `x` = ja apareceu numa resposta real capturada (`tools/capture/*.json`
  ou no HAR de boot); vazio = o client sabe ler mas nunca vimos o server oficial mandar —
  risco de feature so coberta por `stateEcho.php` (passthrough generico) no nosso Router,
  nunca validada contra dado real oficial.

## Hardening contra o crash null -> `.iterator()`

Os 96 campos "nunca vistos" caem no `stateEcho.php` (passthrough) e por isso podem
sair com shape incorreta. O risco concreto e o padrao documentado em
`memory/guild-response-key-null-iterator-pattern`: o cliente le o campo por nome
exato e, se vier **presente com `null`** em vez de colecao/mapa vazio, quebra em
`.iterator()`.

`app/HeroZero/ResponseSchema.php` (gerado desta tabela por
`tools/gen_response_schema.php`) roda em `Response::ok()` — choke point de TODA
action — e coage qualquer campo presente-mas-`null` ao container vazio correto:
`[]` para as 59 colecoes (ArrayOfDO/StringVector/Array) e `{}` para os 24 mapas
(TypedObject). Nao injeta campos ausentes (presenca = feature ativa) nem altera
valores ja preenchidos. Assim, mesmo os campos ainda nao validados contra captura
real ficam **crash-safe**: o pior caso vira "feature vazia", nao um travamento.

Validar de fato (coluna `validado`) ainda exige capturar sessoes oficiais que
toquem cada feature; o hardening apenas garante que a ausencia de validacao nao
derruba o cliente.

| campo | tipo | classe DO | validado |
|---|---|---|---|
| `abo_bonus_claimed` | Boolean |  | [x] |
| `abo_bonus_days_left` | Int |  | [x] |
| `ad_settings` | TypedObject |  | [ ] |
| `advertisment_info` | TypedObject |  | [x] |
| `all_loaded` | Boolean |  | [ ] |
| `all_sidekicks` | ArrayOfDO | lf | [ ] |
| `alternative` | String |  | [ ] |
| `available` | Boolean |  | [ ] |
| `battle` | DO | lw | [x] |
| `battles` | ArrayOfDO | lw | [ ] |
| `bonus_info` | DO | jga | [ ] |
| `casino_identifier` | String |  | [x] |
| `casino_reward_quality` | Int |  | [ ] |
| `casino_reward_type` | Int |  | [ ] |
| `casino_slot1` | Int |  | [ ] |
| `casino_slot2` | Int |  | [ ] |
| `casino_slot3` | Int |  | [ ] |
| `character` | DO | $a | [x] |
| `character_guild_inventory` | DO | Tp | [x] |
| `chests` | ArrayOfDO | y0 | [x] |
| `collected_goals` | TypedObject |  | [x] |
| `collected_item_pattern` | TypedObject |  | [x] |
| `collected_optical_changes` | TypedObject |  | [x] |
| `completed_story_dungeon_steps` | StringVector |  | [x] |
| `constants` | TypedObject |  | [x] |
| `constants_override` | ArrayOfDO | V$ | [x] |
| `current_goal_values` | TypedObject |  | [x] |
| `current_guild_competition_identifier` | String |  | [ ] |
| `current_item_pattern_values` | TypedObject |  | [x] |
| `daily_bonus_reward` | DO | nw | [ ] |
| `daily_bonus_reward_data` | TypedObject |  | [x] |
| `daily_bonus_rewards` | ArrayOfDO | nw | [ ] |
| `daily_login_bonus_rewards` | DO | W$ | [ ] |
| `decrease_counter` | Boolean |  | [ ] |
| `draw_event` | DO | QA | [ ] |
| `dungeon_hardmode_emblems` | Int |  | [x] |
| `dungeon_quest` | DO | Ls | [ ] |
| `dungeon_quests` | ArrayOfDO | Ls | [ ] |
| `dungeons` | ArrayOfDO | Tu | [ ] |
| `event_hub_entries` | ArrayOfDO | JC | [ ] |
| `expired_boosters` | StringVector |  | [ ] |
| `expired_guild_boosters` | StringVector |  | [x] |
| `extendedConfig` | TypedObject |  | [x] |
| `finished_guild_battle_defense` | DO | In | [ ] |
| `finished_guild_dungeon_battle` | DO | xo | [ ] |
| `friend_data` | ArrayOfDO | Sj | [x] |
| `from_character_name` | String |  | [ ] |
| `global_tournament_end_timestamp` | Int |  | [x] |
| `goal_item_ids` | TypedObject |  | [ ] |
| `guild` | DO | Ac | [x] |
| `guild_battle_entries` | ArrayOfDO | wo | [ ] |
| `guild_battle_guilds` | ArrayOfDO | Ac | [ ] |
| `guild_chat_message` | TypedObject |  | [ ] |
| `guild_competition_data` | DO | qw | [x] |
| `guild_competition_tournament_end_timestamp` | Int |  | [ ] |
| `guild_dungeon_battle` | DO | xo | [ ] |
| `guild_dungeon_battle_rewards` | ArrayOfDO | RA | [ ] |
| `guild_gem_production_level` | Int |  | [x] |
| `guild_history_battle` | DO | In | [ ] |
| `guild_history_battles` | ArrayOfDO | np | [ ] |
| `guild_leader_vote` | DO | Uu | [ ] |
| `guild_log` | TypedObject |  | [x] |
| `guild_members` | ArrayOfDO | Nd | [x] |
| `guildbook_objectives` | ArrayOfDO | ok | [x] |
| `guilds_with_rewards` | Int |  | [ ] |
| `has_initialized_unbinding` | Boolean |  | [x] |
| `hidden_object_event_quest` | DO | Mq | [ ] |
| `hideout` | DO | dh | [x] |
| `hideout_battle` | DO | pp | [x] |
| `hideout_history_battles` | ArrayOfDO | qp | [x] |
| `hideout_opponent` | DO | c6 | [x] |
| `hideout_room` | DO | hl | [x] |
| `hideout_rooms` | ArrayOfDO | hl | [x] |
| `inactive_sidekick` | DO | lf | [ ] |
| `ingame_notifications` | TypedObject |  | [ ] |
| `inventory` | DO | wf | [x] |
| `item` | DO | ch | [ ] |
| `item_improvements` | ArrayOfDO | xx | [ ] |
| `items` | ArrayOfDO | ch | [x] |
| `last_payment_confirmed` | Boolean |  | [ ] |
| `last_sync` | String |  | [x] |
| `leaderboard_characters` | ArrayOfDO | $f | [x] |
| `leaderboard_guild_competitions` | ArrayOfDO | Ms | [ ] |
| `leaderboard_hideouts` | ArrayOfDO | $f | [x] |
| `leaderboard_solo_guild_competitions` | ArrayOfDO | yo | [ ] |
| `league_division_change` | String |  | [ ] |
| `league_locked` | Boolean |  | [x] |
| `league_opponents` | ArrayOfDO | x0 | [ ] |
| `league_season_end_timestamp` | Int |  | [x] |
| `legendary_dungeon` | DO | rw | [ ] |
| `legendary_dungeon_run` | DO | tm | [ ] |
| `local_notification_settings` | TypedObject |  | [x] |
| `login_count` | Int |  | [x] |
| `max_characters` | Int |  | [x] |
| `max_guild_competitions` | Int |  | [ ] |
| `max_guilds` | Int |  | [x] |
| `max_hideouts` | Int |  | [x] |
| `max_solo_guild_competitions` | Int |  | [ ] |
| `max_spendable_amount` | Int |  | [ ] |
| `messages` | StringVector |  | [ ] |
| `messages_ignored_character_info` | ArrayOfDO | SA | [ ] |
| `missed_duel` | DO | Yy | [ ] |
| `missed_duel_data` | ArrayOfDO | sw | [ ] |
| `missed_duel_opponents` | ArrayOfDO | Gl | [ ] |
| `missed_duels` | Int |  | [x] |
| `missed_hideout_attacks` | Int |  | [x] |
| `missed_league_fight` | DO | Zy | [ ] |
| `missed_league_fight_data` | ArrayOfDO | kga | [ ] |
| `missed_league_fight_opponents` | ArrayOfDO | Gl | [ ] |
| `missed_league_fights` | Int |  | [x] |
| `new_guild_log_entries` | Int |  | [x] |
| `new_version` | Boolean |  | [x] |
| `news` | TypedObject |  | [x] |
| `next_guild_competition_data` | DO | qw | [ ] |
| `one_time_tutorials` | ArrayOfDO | Hr | [x] |
| `opponent_inventory` | DO | wf | [x] |
| `opponent_inventory_items` | ArrayOfDO | ch | [x] |
| `opponents` | ArrayOfDO | Zm | [x] |
| `outfit` | DO | tw | [ ] |
| `owned_items` | ArrayOfDO | d6 | [x] |
| `payment_id` | Int |  | [ ] |
| `pending_guild_battle_defense` | DO | In | [ ] |
| `pending_guild_dungeon_battle` | DO | xo | [ ] |
| `private_conversation` | DO | Gt | [ ] |
| `private_conversation_messages` | ArrayOfDO | Ns | [ ] |
| `private_conversations` | ArrayOfDO | Gt | [ ] |
| `quest` | DO | zo | [x] |
| `quests` | ArrayOfDO | zo | [x] |
| `reactivation_code` | String |  | [ ] |
| `redirect_url` | String |  | [ ] |
| `requested_character_collected_goals` | TypedObject |  | [x] |
| `requested_character_current_goal_values` | TypedObject |  | [x] |
| `requested_hideout` | DO | dh | [x] |
| `requested_hideout_optical_changes` | TypedObject |  | [x] |
| `requested_hideout_rooms` | ArrayOfDO | hl | [x] |
| `reskill_enabled` | Boolean |  | [x] |
| `resource_request` | DO | zx | [ ] |
| `resource_request_friends` | ArrayOfDO | Sj | [x] |
| `reward_box` | DO | uw | [ ] |
| `reward_boxes` | ArrayOfDO | uw | [ ] |
| `rewards` | StringVector |  | [ ] |
| `saved_seconds` | Int |  | [x] |
| `season` | String |  | [x] |
| `season_progress` | DO | Wu | [x] |
| `season_rewards` | ArrayOfDO | Xu | [x] |
| `sections` | ArrayOfDO | w0 | [ ] |
| `server_system_message_teaser_info` | String |  | [ ] |
| `server_system_messages` | ArrayOfDO | lga | [ ] |
| `show_advertisment` | Boolean |  | [x] |
| `show_preroll_advertisment` | Boolean |  | [x] |
| `sidekick` | DO | lf | [ ] |
| `sidekick_item_skills` | ArrayOfDO | IT | [ ] |
| `sidekicks` | ArrayOfDO | lf | [x] |
| `speedserver_expired` | Boolean |  | [ ] |
| `speedserver_rewards` | ArrayOfDO | f6 | [ ] |
| `story_dungeon_lookup` | DO | bF | [x] |
| `story_dungeon_step` | DO | Os | [x] |
| `story_dungeon_steps` | ArrayOfDO | Os | [x] |
| `stream_id` | Int |  | [ ] |
| `streams_info` | TypedObject |  | [x] |
| `surveys` | ArrayOfDO | KC | [ ] |
| `sync_states` | TypedObject |  | [x] |
| `text` | TypedObject |  | [x] |
| `titles` | ArrayOfDO | h6 | [x] |
| `tournament_end_timestamp` | Int |  | [x] |
| `tournament_guild_competition_reward` | DO | Qna | [ ] |
| `tournament_league_reward` | DO | JT | [ ] |
| `tournament_rewards` | DO | MH | [ ] |
| `tower_event_data` | TypedObject |  | [x] |
| `training` | DO | rp | [x] |
| `training_quest` | DO | Nq | [x] |
| `trainings` | ArrayOfDO | rp | [ ] |
| `treasure_event` | DO | Lq | [x] |
| `url_speedserver_leaderboard` | String |  | [ ] |
| `user` | DO | mga | [x] |
| `user_voucher` | DO | TA | [ ] |
| `user_vouchers` | ArrayOfDO | TA | [ ] |
| `video_advertisment_id` | Int |  | [x] |
| `voucher_rewards` | TypedObject |  | [ ] |
| `worldboss_attack` | DO | Ht | [ ] |
| `worldboss_event_character_data` | Array |  | [x] |
| `worldboss_events` | ArrayOfDO | Up | [ ] |
| `worldboss_previous_attackers` | StringVector |  | [ ] |
| `worldboss_reward` | DO | Zu | [ ] |
| `worldboss_rewards` | ArrayOfDO | Zu | [ ] |