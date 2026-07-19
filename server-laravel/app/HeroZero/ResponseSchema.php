<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Fonte unica de verdade dos tipos de nivel raiz que o cliente sabe ler (classe `ta`
 * no HeroZero.min.js). GERADO a partir de docs/RESPONSE_SCHEMA.md por
 * tools/gen_response_schema.php — NAO editar a mao; re-gerar se o schema mudar.
 *
 * Uso: ResponseSchema::normalize($data) corrige campos PRESENTES cujo valor veio
 * `null` para o container vazio correto — array JSON `[]` p/ colecoes
 * (ArrayOfDO/StringVector) e objeto `{}` p/ mapas (TypedObject). Isso mata o crash
 * documentado em memory/guild-response-key-null-iterator-pattern: o cliente le o
 * campo por nome exato e, se vier null em vez de colecao vazia, quebra em
 * .iterator().
 *
 * IMPORTANTE: normalize() so age sobre valor === null e NUNCA injeta campos
 * ausentes — presenca de um campo e sinal de feature ativa (missed_duel,
 * worldboss_attack, battle...). Um valor ja preenchido (mesmo assoc) e preservado
 * intacto; corrigir tipo alem de null seria arriscado e fica fora de escopo.
 */
final class ResponseSchema
{
    /** Campos que o cliente le como colecao (ArrayOfDO/StringVector/Array) -> `[]`. */
    private const ARRAY_FIELDS = [
        'all_sidekicks',
        'battles',
        'chests',
        'completed_story_dungeon_steps',
        'constants_override',
        'daily_bonus_rewards',
        'dungeon_quests',
        'dungeons',
        'event_hub_entries',
        'expired_boosters',
        'expired_guild_boosters',
        'friend_data',
        'guild_battle_entries',
        'guild_battle_guilds',
        'guild_dungeon_battle_rewards',
        'guild_history_battles',
        'guild_members',
        'guildbook_objectives',
        'hideout_history_battles',
        'hideout_rooms',
        'item_improvements',
        'items',
        'leaderboard_characters',
        'leaderboard_guild_competitions',
        'leaderboard_hideouts',
        'leaderboard_solo_guild_competitions',
        'league_opponents',
        'messages',
        'messages_ignored_character_info',
        'missed_duel_data',
        'missed_duel_opponents',
        'missed_league_fight_data',
        'missed_league_fight_opponents',
        'one_time_tutorials',
        'opponent_inventory_items',
        'opponents',
        'owned_items',
        'private_conversation_messages',
        'private_conversations',
        'quests',
        'requested_hideout_rooms',
        'resource_request_friends',
        'reward_boxes',
        'rewards',
        'season_rewards',
        'sections',
        'server_system_messages',
        'sidekick_item_skills',
        'sidekicks',
        'speedserver_rewards',
        'story_dungeon_steps',
        'surveys',
        'titles',
        'trainings',
        'user_vouchers',
        'worldboss_event_character_data',
        'worldboss_events',
        'worldboss_previous_attackers',
        'worldboss_rewards',
    ];

    /** Campos que o cliente le como mapa (TypedObject) -> `{}`. */
    private const OBJECT_FIELDS = [
        'ad_settings',
        'advertisment_info',
        'collected_goals',
        'collected_item_pattern',
        'collected_optical_changes',
        'constants',
        'current_goal_values',
        'current_item_pattern_values',
        'daily_bonus_reward_data',
        'extendedConfig',
        'goal_item_ids',
        'guild_chat_message',
        'guild_log',
        'ingame_notifications',
        'local_notification_settings',
        'news',
        'requested_character_collected_goals',
        'requested_character_current_goal_values',
        'requested_hideout_optical_changes',
        'streams_info',
        'sync_states',
        'text',
        'tower_event_data',
        'voucher_rewards',
    ];

    public static function normalize(array $data): array
    {
        foreach (self::ARRAY_FIELDS as $f) {
            if (array_key_exists($f, $data) && $data[$f] === null) {
                $data[$f] = [];
            }
        }
        foreach (self::OBJECT_FIELDS as $f) {
            if (array_key_exists($f, $data) && $data[$f] === null) {
                $data[$f] = new \stdClass();
            }
        }
        return $data;
    }
}
