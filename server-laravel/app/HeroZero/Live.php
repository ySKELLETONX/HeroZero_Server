<?php
declare(strict_types=1);

namespace HeroZero;

/**
 * Helpers para transformar capturas/HAR em contrato de resposta, sem servir estado
 * estatico de outra conta. O template define a shape; o banco define os valores vivos.
 */
final class Live
{
    public static function template(string $action): array
    {
        if (Replay::has($action)) {
            return self::withLocalConstants(self::timeKeys(Replay::data($action)));
        }

        $files = glob(__DIR__ . '/../../../tools/capture/*_' . $action . '.json') ?: [];
        sort($files, SORT_NATURAL);
        // Nem toda captura tem a resposta gravada (HAR truncado): usa a PRIMEIRA
        // com response.data nao-vazio; um template vazio faz o overlay devolver
        // objetos rasos e o cliente crasha em campo obrigatorio ausente.
        foreach ($files as $file) {
            $raw = json_decode((string)file_get_contents($file), true);
            $data = $raw['response']['data'] ?? null;
            if (is_array($data) && $data !== []) {
                return self::withLocalConstants(self::timeKeys($data));
            }
        }
        return self::withLocalConstants(self::timeKeys([]));
    }

    public static function withLocalConstants(array $data): array
    {
        if (!isset($data['constants']) || !is_array($data['constants'])) {
            $data['constants'] = [];
        }
        $boosters = self::localBoosters();
        $data['constants']['boosters'] = $boosters;
        // Alguns getters do client (ex.: Va.get_activeQuestBoosterAmount/
        // get_activeDuelBoosterAmount, usados pelo painel quest_progress) resolvem
        // o id ativo via Si.fromId(), cujo CONSTANTS_KEY e "guild_boosters" -- um
        // catalogo SEPARADO de "boosters" (Xd.fromId, usado pelos getters de
        // stats/work/league). Mesmo id (ex. booster_quest2) gravado pelo
        // buyBooster.php so existe em "boosters", entao esses getters especificos
        // leem null e crasham em get_amount() (mesma classe de bug do
        // [[booster-catalog-null-crash]]). Espelhamos o catalogo inteiro tambem em
        // guild_boosters pra qualquer id que emitirmos resolver nos dois lugares.
        $data['constants']['guild_boosters'] = $boosters;
        return $data;
    }

    /**
     * Catalogo OFICIAL de boosters (constants_json.data do CDN, chave "boosters"):
     * identifier => [type, amount, amount2, duration => [premium(bool), cost]].
     * Fonte unica dos constants servidos E da cobranca do buyBooster — o dialogo
     * do client oferece exatamente estas duracoes; duracao fora do catalogo era
     * o nosso convite a errRequestInvalidParameter (fatal no client).
     */
    public const BOOSTER_CATALOG = [
        'booster_quest1' => [1, 10, 0, [172800 => [false, 206]]],
        'booster_quest2' => [1, 25, 0, [345600 => [false, 2057]]],
        'booster_quest3' => [1, 50, 0, [604800 => [true, 10], 2592000 => [true, 40], 7776000 => [true, 115]]],
        'booster_stats1' => [2, 10, 0, [172800 => [false, 206]]],
        'booster_stats2' => [2, 25, 0, [345600 => [false, 2057]]],
        'booster_stats3' => [2, 50, 0, [604800 => [true, 10], 2592000 => [true, 40], 7776000 => [true, 115]]],
        'booster_work1' => [3, 10, 0, [172800 => [false, 206]]],
        'booster_work2' => [3, 25, 0, [345600 => [false, 2057]]],
        'booster_work3' => [3, 50, 0, [604800 => [true, 10], 2592000 => [true, 40], 7776000 => [true, 115]]],
        'sense_booster' => [4, 1, 0, [3600 => [true, 2], 7200 => [true, 4], 21600 => [true, 12]]],
        'booster_league1' => [5, 60, 0, [345600 => [false, 2057]]],
        'booster_league2' => [5, 120, 0, [604800 => [true, 10], 2592000 => [true, 40], 7776000 => [true, 115]]],
        'multitasking_booster' => [6, 1, 0, [604800 => [true, 9], -1 => [true, 99]]],
        'training_sense_booster' => [7, 2, 3, [3600 => [true, 5], 7200 => [true, 9], 21600 => [true, 19], 1209600 => [true, 99]]],
        'booster_hideout1' => [8, 25, 0, [345600 => [true, 5]]],
        'booster_hideout2' => [8, 50, 0, [604800 => [true, 10], 2592000 => [true, 40], 7776000 => [true, 115]]],
    ];

    /** Catalogo OFICIAL de guild boosters (constants "guild_boosters", shape igual). */
    public const GUILD_BOOSTER_CATALOG = [
        'guild_booster_training1' => [1, 4, 0, [604800 => [false, 30000]]],
        'guild_booster_training2' => [1, 7, 0, [604800 => [true, 69], 1209600 => [true, 130], 2592000 => [true, 265]]],
        'guild_booster_quest1' => [2, 10, 0, [604800 => [false, 30000]]],
        'guild_booster_quest2' => [2, 20, 0, [604800 => [true, 69], 1209600 => [true, 130], 2592000 => [true, 265]]],
        'guild_booster_duel1' => [3, 10, 0, [604800 => [false, 30000]]],
        'guild_booster_duel2' => [3, 20, 0, [604800 => [true, 69], 1209600 => [true, 130], 2592000 => [true, 265]]],
    ];

    /**
     * Resolve custo de um booster: [premium(bool), custo] ou null se o par
     * id/duracao nao esta a venda.
     */
    public static function boosterCost(string $id, int $duration): ?array
    {
        $entry = self::BOOSTER_CATALOG[$id] ?? self::GUILD_BOOSTER_CATALOG[$id] ?? null;
        return $entry === null ? null : ($entry[3][$duration] ?? null);
    }

    /**
     * Monta a shape de constants a partir dos catalogos oficiais acima.
     * Os DOIS catalogos vao nas DUAS chaves (boosters e guild_boosters) porque o
     * client resolve ids por catalogos separados (Xd.fromId "boosters", Si.fromId
     * "guild_boosters") e qualquer id ativo sem entrada quebra tooltip/painel
     * (ver [[booster-catalog-null-crash]]).
     */
    private static function localBoosters(): array
    {
        $out = [];
        foreach (self::BOOSTER_CATALOG + self::GUILD_BOOSTER_CATALOG as $id => [$type, $amount, $amount2, $durations]) {
            $durMap = [];
            foreach ($durations as $dur => [$premium, $cost]) {
                $durMap[$dur] = ['boosterDuration' => $dur, 'premium' => $premium, 'cost' => $cost];
            }
            $out[$id] = [
                'identifier' => $id,
                'type' => $type,
                'amount' => $amount,
                'amount2' => $amount2,
                'default_duration' => array_key_first($durations),
                'duration' => $durMap,
            ];
        }
        return $out;
    }

    public static function timeKeys(array $data): array
    {
        $data['server_time'] = time();
        $data['time_correction'] = 0;
        return $data;
    }

    public static function overlayAccount(array $data, int $userId): array
    {
        try {
            $char = Character::loadByUser($userId);
        } catch (GameError $e) {
            return $data;
        }
        if (isset($data['user']) && is_array($data['user'])) {
            $data['user'] = $char->overlayUser($data['user']);
        }
        if (isset($data['character']) && is_array($data['character'])) {
            $data['character'] = $char->overlayCharacter($data['character']);
        }
        if (isset($data['inventory']) && is_array($data['inventory'])) {
            $data['inventory'] = $char->inventoryData($data['inventory']);
        }
        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = $char->itemsData($data['items'][0] ?? []);
        }
        if (isset($data['quests']) && is_array($data['quests'])) {
            $data['quests'] = $char->questsData($data['quests'][0] ?? []);
        }
        if (isset($data['trainings']) && is_array($data['trainings'])) {
            $data = self::attachTrainingState($data, $char);
        } elseif (array_key_exists('training_quests', $data)) {
            // template com training_quests de outra conta sem trainings: ids
            // estranhos no mapa do cliente (ou merge em get_training() null).
            unset($data['training_quests']);
        }
        return $data;
    }

    /**
     * Anexa o bloco de treino CONSISTENTE (`trainings` + `training_quests`).
     * O cliente html5_257 recria o mapa de trainings com quests VAZIOS sempre que
     * `trainings` vem numa resposta (refreshTrainings) e so repopula os quests se
     * `training_quests` vier na MESMA resposta. `trainings` sem `training_quests`
     * com treino ativo deixa o pool apontando para quests inexistentes e o proximo
     * refresh do painel training_progress crasha (h[pool_id] -> undefined ->
     * jF.refresh: "reading 'get_isFightQuest' of undefined"). Inversamente,
     * `training_quests` sem treino ativo crasha o merge (get_training() null),
     * por isso a chave e removida quando nao ha treino.
     */
    public static function attachTrainingState(array $data, Character $char): array
    {
        $tpl = $data['trainings'][0] ?? (Replay::data('autoLoginUser')['trainings'][0] ?? []);
        $data['trainings'] = $char->trainingsData(is_array($tpl) ? $tpl : []);
        $quests = $char->trainingQuestsData();
        if ($quests !== []) {
            $data['training_quests'] = $quests;
        } else {
            unset($data['training_quests']);
        }
        return $data;
    }

    /**
     * `event_quest`: o convite vem do template SEM `id` (o cliente usa
     * !hasData("id") como get_isUnassigned). Quando a conta ja aceitou
     * (character.event_quest_id != 0), o objeto PRECISA carregar esse id na
     * MESMA resposta: get_eventQuest() localiza a quest por get_id(), e
     * DataObject sem o campo lanca "unknown field key=id" no painel de quests.
     * Chamar DEPOIS de overlayCharacter (le event_quest_id ja resolvido).
     */
    public static function attachEventQuestState(array $data): array
    {
        $tpl = $data['event_quest'] ?? (self::template('autoLoginUser')['event_quest'] ?? null);
        if (!is_array($tpl)) {
            return $data;
        }
        $eqId = (int)($data['character']['event_quest_id'] ?? 0);
        if ($eqId !== 0) {
            // Shape ASSIGNED completa do DOEventQuest (Gr): o painel le status
            // (get_isFinished = status==2) e campos de reward SEM guarda de
            // hasData; qualquer um ausente derruba o painel de quests.
            $tpl += [
                'id' => $eqId,
                'status' => 1,
                'rewards' => '',
                'reward_item1_id' => 0,
                'reward_item1_rewards' => '',
                'reward_item2_id' => 0,
                'reward_item2_rewards' => '',
                'reward_item3_id' => 0,
                'reward_item3_rewards' => '',
            ];
            $tpl['id'] = $eqId;
        }
        $data['event_quest'] = $tpl;
        return $data;
    }

    /**
     * `treasure_event`: mesmo contrato do [[event_quest]] — o convite do template
     * NAO tem `id` (get_isUnassigned = !hasData("id")); quando a conta aceitou
     * (character.treasure_event_id != 0, recomputado no overlay a partir do blob
     * em collected_item_pattern), o objeto precisa da shape ASSIGNED completa do
     * DOEventTreasure (Lq) — todos os campos lidos sem guarda de hasData.
     */
    public static function attachTreasureEventState(array $data, Character $char): array
    {
        $tpl = $data['treasure_event'] ?? (self::template('autoLoginUser')['treasure_event'] ?? null);
        if (!is_array($tpl)) {
            return $data;
        }
        if ((int)($data['character']['treasure_event_id'] ?? 0) !== 0) {
            $state = $char->ensureTreasureEventState();
            $tpl += [
                'id' => 1,
                'status' => 1,
                'event_tokens' => (int)($state['tokens'] ?? 0),
                'current_level' => (int)($state['level'] ?? 1),
                'event_reveal_items' => 0,
                'ts_reveal_item_collected' => 0,
                'rewards' => '',
                'collected_rewards' => '',
                'levels' => '',
            ];
        }
        $data['treasure_event'] = $tpl;
        return $data;
    }

    /**
     * Dungeon de historia: devolve completed_story_dungeon_steps reais e, se ha
     * passo ativo, story_dungeon_lookup + story_dungeon_step (shape da captura).
     * No boot, chamar DEPOIS do esvaziamento de ACCOUNT_STATE_KEYS (que zera
     * completed_story_dungeon_steps) para o valor real prevalecer.
     */
    public static function attachStoryDungeonState(array $data, Character $char): array
    {
        $state = $char->storyDungeonState();
        $data['completed_story_dungeon_steps'] = array_values($state['completed']);
        $active = $state['active'] ?? null;
        if (is_array($active)) {
            $index = (int)$active['index'];
            $step = (int)$active['step'];
            $stepId = $char->storyDungeonStepId($index, $step);
            $data['story_dungeon_lookup'] = [
                'id' => $char->id(),
                'story_dungeon_step_ids' => json_encode([$stepId]),
                'story_dungeon_steps' => json_encode([(string)$step => 1]),
            ];
            $data['story_dungeon_step'] = [
                'id' => $stepId,
                'character_id' => $char->id(),
                'story_dungeon_index' => $index,
                'step_index' => $step,
                'status' => (int)$active['status'],
                'repeat' => false,
                'points_collected' => 0,
                'ts_complete' => 0,
                'ts_last_attack' => 0,
                'battle_ids' => '',
                'rewards' => '',
            ];
        }
        return $data;
    }

    public static function currentUserId(array $params): int
    {
        return (int)($params['existing_user_id'] ?? $params['user_id'] ?? 0);
    }

    public static function accountState(int $userId, array $data = []): array
    {
        $char = Character::loadByUser($userId);
        $boot = self::template('autoLoginUser');

        $data['user'] = $char->overlayUser($data['user'] ?? $boot['user'] ?? []);
        $data['character'] = $char->overlayCharacter($data['character'] ?? $boot['character'] ?? []);
        $data['inventory'] = $char->inventoryData($data['inventory'] ?? $boot['inventory'] ?? []);
        $data['items'] = $char->itemsData(($data['items'][0] ?? null) ?: ($boot['items'][0] ?? []));
        $data['quests'] = $char->questsData(($data['quests'][0] ?? null) ?: ($boot['quests'][0] ?? []));
        $data['trainings'] = [($data['trainings'][0] ?? null) ?: ($boot['trainings'][0] ?? [])];
        // O client so chama refreshNews() (que instancia character._news) quando a
        // resposta tem hasData("news"); sem isso _news fica null pra sempre e
        // qualquer refreshTitleBar (get_news().get_hasUnreadNews()) crasha.
        $data['news'] = $data['news'] ?? $boot['news'] ?? [];
        $data = self::attachTrainingState($data, $char);
        $data = self::attachEventQuestState($data);
        $data = self::attachTreasureEventState($data, $char);
        $data = self::attachStoryDungeonState($data, $char);

        $guild = self::guildForUser($userId);
        if ($guild !== null) {
            $data['guild'] = self::shapeGuild($guild);
            $data['guild_members'] = self::guildMembers((int)$guild['id']);
            // Quando a versao muda, o cliente dispara getGuildLog no proximo poll
            // (e assim o chat chega aos outros membros).
            $data['sync_states'] = ['guild' . (int)$guild['id'] => GuildChat::version((int)$guild['id'])];
        }

        $optical = Character::emptyLike($boot['current_optical_changes'] ?? []);
        $optical['id'] = $char->id();
        $optical['character_id'] = $char->id();
        $optical['available_chests'] = '[]';
        $optical['active_options'] = '[]';
        $optical['unlocked_options'] = '{}';
        $optical['use_for_character'] = false;
        $optical['use_for_quest'] = false;
        $optical['use_for_duel'] = false;
        $optical['use_for_league'] = false;
        $data['current_optical_changes'] = $optical;

        $data['server_time'] = time();
        $data['time_correction'] = 0;
        return $data;
    }

    public static function guildForUser(int $userId): ?array
    {
        return Db::row(
            'SELECT g.* FROM `guild` g
              JOIN `character` c ON c.guild_id = g.id
             WHERE c.user_id = ?
             LIMIT 1',
            [$userId]
        );
    }

    public static function shapeGuild(array $guild): array
    {
        foreach ($guild as $key => $value) {
            if (is_numeric($value) && $key !== 'name' && $key !== 'description' && $key !== 'note' && $key !== 'forum_page' && !str_ends_with($key, '_id')) {
                $guild[$key] = (int)$value;
            }
        }
        $guild['id'] = (int)$guild['id'];
        $guild['initiator_character_id'] = (int)$guild['initiator_character_id'];
        $guild['leader_character_id'] = (int)$guild['leader_character_id'];
        $guild['accept_members'] = (bool)$guild['accept_members'];
        $guild['auto_joins'] = (int)$guild['auto_joins'];
        $guild['use_missiles_attack'] = (bool)$guild['use_missiles_attack'];
        $guild['use_missiles_defense'] = (bool)$guild['use_missiles_defense'];
        $guild['use_missiles_dungeon'] = (bool)$guild['use_missiles_dungeon'];
        $guild['use_auto_joins_attack'] = (bool)$guild['use_auto_joins_attack'];
        $guild['use_auto_joins_defense'] = (bool)$guild['use_auto_joins_defense'];
        $guild['use_auto_joins_dungeon'] = (bool)$guild['use_auto_joins_dungeon'];
        $guild['locale'] = $guild['locale'] ?? 'pt_BR';
        $guild['ts_last_synergy_calculation'] = (int)($guild['ts_last_synergy_calculation'] ?? 0);
        $guild['ts_next_synergy_calculation'] = (int)($guild['ts_next_synergy_calculation'] ?? 0);
        // The HTML5 client reads this complete application/tactics block whenever
        // the guild panel opens, even when no recruitment rules were configured.
        $guild['pending_leader_vote_id'] = (int)($guild['pending_leader_vote_id'] ?? 0);
        $guild['min_apply_level'] = (int)($guild['min_apply_level'] ?? 0);
        $guild['min_apply_honor'] = (int)($guild['min_apply_honor'] ?? 0);
        $guild['min_apply_hideout_level'] = (int)($guild['min_apply_hideout_level'] ?? 0);
        $guild['min_apply_gem_level'] = (int)($guild['min_apply_gem_level'] ?? 0);
        $guild['apply_open'] = (bool)($guild['apply_open'] ?? true);
        $guild['apply_open_mail'] = (bool)($guild['apply_open_mail'] ?? true);
        $guild['guild_battle_tactics_attack_order'] = (int)($guild['guild_battle_tactics_attack_order'] ?? 0);
        $guild['guild_battle_tactics_attack_tactic'] = (int)($guild['guild_battle_tactics_attack_tactic'] ?? 0);
        $guild['guild_battle_tactics_defense_order'] = (int)($guild['guild_battle_tactics_defense_order'] ?? 0);
        $guild['guild_battle_tactics_defense_tactic'] = (int)($guild['guild_battle_tactics_defense_tactic'] ?? 0);
        $guild['officer_note'] = (string)($guild['officer_note'] ?? '');
        $guild['pending_guild_battle_attack_id'] = (int)($guild['pending_guild_battle_attack_id'] ?? 0);
        $guild['pending_guild_battle_defense_id'] = (int)($guild['pending_guild_battle_defense_id'] ?? 0);
        $guild['pending_guild_dungeon_battle_attack_id'] = (int)($guild['pending_guild_dungeon_battle_attack_id'] ?? 0);
        $guild['battles_fought'] = (int)($guild['battles_fought'] ?? 0);
        $guild['unlocked_rewards'] = (string)($guild['unlocked_rewards'] ?? '[]');
        $guild['guild_competition_reward_boost_factor'] = (int)($guild['guild_competition_reward_boost_factor'] ?? 0);
        $guild['ts_guild_competition_reward_boost_expires'] = (int)($guild['ts_guild_competition_reward_boost_expires'] ?? 0);
        $guild['guildbook_objectives_renewed_today'] = (int)($guild['guildbook_objectives_renewed_today'] ?? 0);
        $guild['synergy_energy'] = (int)($guild['synergy_energy'] ?? 0);
        $guild['synergy_energy_storage'] = (int)($guild['synergy_energy_storage'] ?? 0);
        $guild['synergy_energy_storage_temp'] = (int)($guild['synergy_energy_storage_temp'] ?? 0);
        $guild['synergy_energy_storage_max'] = (int)($guild['synergy_energy_storage_max'] ?? 0);
        $guild['active_synergy_energy'] = (int)($guild['active_synergy_energy'] ?? 0);
        $guild['synergy_upgrades'] = (string)($guild['synergy_upgrades'] ?? '{}');
        // Pontos de stat da guilda são DERIVADOS do honor (não um pool armazenado):
        // total ganho = honor / HONOR_PER_STAT_POINT; disponível = total - já gastos.
        // Sem isto, toda guilda ficava travada em 0 (improveGuildStat sempre falhava).
        $guild['stat_points_available'] = self::guildStatPointsAvailable($guild);
        return $guild;
    }

    /** Valores-base das 4 stats de guilda (estado de uma guilda recém-criada). */
    public const GUILD_STAT_BASES = [
        'stat_guild_capacity'                   => 10,
        'stat_character_base_stats_boost'       => 1,
        'stat_quest_xp_reward_boost'            => 1,
        'stat_quest_game_currency_reward_boost' => 1,
    ];

    /** Honor necessário por ponto de stat de guilda (curva simples, até termos a da CDN). */
    public const GUILD_HONOR_PER_STAT_POINT = 1000;

    /** Total de pontos que a guilda já ganhou, a partir do seu honor acumulado. */
    public static function guildStatPointsFromHonor(int $honor): int
    {
        return intdiv(max(0, $honor), self::GUILD_HONOR_PER_STAT_POINT);
    }

    /** Pontos já gastos = soma dos incrementos acima do valor-base de cada stat. */
    public static function guildStatPointsSpent(array $guild): int
    {
        $spent = 0;
        foreach (self::GUILD_STAT_BASES as $col => $base) {
            $spent += max(0, (int)($guild[$col] ?? $base) - $base);
        }
        return $spent;
    }

    /** Pontos disponíveis para gastar agora = ganhos (do honor) - já gastos. */
    public static function guildStatPointsAvailable(array $guild): int
    {
        $total = self::guildStatPointsFromHonor((int)($guild['honor'] ?? 0));
        return max(0, $total - self::guildStatPointsSpent($guild));
    }

    public static function guildMembers(int $guildId): array
    {
        $rows = Db::rows(
            'SELECT id, user_id, name, gender, level, guild_rank, ts_guild_joined,
                    ts_last_action, guild_donated_game_currency, guild_donated_premium_currency,
                    stat_base_stamina, stat_trained_stamina,
                    stat_base_strength, stat_trained_strength,
                    stat_base_critical_rating, stat_trained_critical_rating,
                    stat_base_dodge_rating, stat_trained_dodge_rating
               FROM `character`
              WHERE guild_id = ?
              ORDER BY guild_rank DESC, level DESC, id ASC',
            [$guildId]
        );
        $members = [];
        foreach ($rows as $row) {
            $members[] = [
                'id' => (int)$row['id'],
                'user_id' => (int)$row['user_id'],
                'server_id' => 'local',
                'name' => (string)$row['name'],
                'gender' => (string)$row['gender'],
                'level' => (int)$row['level'],
                'guild_rank' => (int)$row['guild_rank'],
                'ts_guild_joined' => (int)$row['ts_guild_joined'],
                'ts_last_online' => (int)$row['ts_last_action'],
                'last_action' => (int)$row['ts_last_action'],
                'online_status' => 2,
                'game_currency_donation' => (int)$row['guild_donated_game_currency'],
                'premium_currency_donation' => (int)$row['guild_donated_premium_currency'],
                'guild_competition_points_gathered' => 0,
                'guild_competition_point_fractions_gathered' => 0,
                'stat_total_stamina' => (int)$row['stat_base_stamina'] + (int)$row['stat_trained_stamina'],
                'stat_total_strength' => (int)$row['stat_base_strength'] + (int)$row['stat_trained_strength'],
                'stat_total_critical_rating' => (int)$row['stat_base_critical_rating'] + (int)$row['stat_trained_critical_rating'],
                'stat_total_dodge_rating' => (int)$row['stat_base_dodge_rating'] + (int)$row['stat_trained_dodge_rating'],
                'auto_joins' => 0,
                'use_auto_joins_attack' => true,
                'use_auto_joins_defense' => true,
                'use_auto_joins_dungeon' => true,
                'missiles' => 0,
                'use_missiles_attack' => true,
                'use_missiles_defense' => true,
                'use_missiles_dungeon' => true,
                'medikits' => 0,
                'use_medikits_attack' => false,
                'use_medikits_defense' => false,
                'use_medikits_dungeon' => false,
                'weapon_oil' => 0,
                'use_weapon_oil_attack' => false,
                'use_weapon_oil_defense' => false,
                'use_weapon_oil_dungeon' => false,
                'hideout_rooms' => [],
            ];
        }
        return $members;
    }

    public static function leaderboardCharacters(array $tpl, string $sort = 'honor', int $limit = 30): array
    {
        $sortSql = match ($sort) {
            'level' => '`level` DESC, `xp` DESC',
            'league' => '`league_points` DESC, `honor` DESC',
            default => '`honor` DESC, `level` DESC',
        };
        $rows = Db::rows("SELECT * FROM `character` WHERE user_id <> 0 ORDER BY {$sortSql}, id LIMIT " . (int)$limit);
        $out = [];
        $rank = 1;
        foreach ($rows as $r) {
            $value = $sort === 'level' ? (int)$r['level'] : ($sort === 'league' ? (int)$r['league_points'] : (int)$r['honor']);
            $item = [
                'server_id' => 'local',
                'rank' => $rank,
                'r' => $rank,
                'id' => (int)$r['id'],
                'character_id' => (int)$r['id'],
                'name' => (string)$r['name'],
                'locale' => 'pt_BR',
                'guild_id' => (int)$r['guild_id'],
                'guild_name' => '',
                'gender' => (string)$r['gender'],
                'level' => (int)$r['level'],
                'league_points' => (int)$r['league_points'],
                'league_group_id' => (int)$r['league_group_id'],
                'honor' => (int)$r['honor'],
                'value' => $value,
                'online_status' => 2,
                'hideout_level' => 1,
                'hideout_points' => 0,
                'dungeon_hardmode_emblems' => 0,
                'attacked_count' => 0,
                'max_attack_count' => 3,
            ];
            $out[] = self::shapeLike($tpl, $item);
            $rank++;
        }
        return $out;
    }

    /**
     * Sorteia 3 oponentes de liga (NPCs user_id=0), persiste a lista em
     * character.league_opponents (+ ts do refresh) e devolve na shape que o client
     * espera: { opponent, opponent_inventory, opponent_inventory_items }.
     * Fonte unica usada por getLeagueOpponents/refreshLeagueOpponents/startLeagueFight
     * (leagueMissed.php) e enterLeagueDivision.php — assim a lista salva no char e a
     * ecoada na resposta nunca divergem.
     */
    public static function leagueOpponents(int $cid): array
    {
        $rows = Db::rows('SELECT id FROM `character` WHERE user_id = 0 AND id <> ? ORDER BY RAND() LIMIT 3', [$cid]);
        $ids = array_map(static fn(array $r): int => (int)$r['id'], $rows);
        Db::exec('UPDATE `character` SET league_opponents = ?, ts_last_league_opponents_refresh = ? WHERE id = ?',
            [implode(',', $ids), time(), $cid]);
        // Template raso ([]) = opponent sem name/gender/level/league_points -> tela da
        // liga com cards vazios e risco de crash (mesmo aviso do getCharacter.php).
        $boot = self::template('autoLoginUser');
        $tplChar = $boot['character'] ?? [];
        $tplInv  = $boot['inventory'] ?? [];
        $tplItem = $boot['items'][0] ?? [];
        $list = [];
        foreach ($ids as $oid) {
            $list[] = [
                'opponent' => self::requestedCharacter($oid, $tplChar),
                'opponent_inventory' => self::inventoryForCharacter($oid, $tplInv),
                'opponent_inventory_items' => self::itemsForCharacter($oid, $tplItem),
            ];
        }
        return $list;
    }

    public static function requestedCharacter(int $characterId, array $tplChar): array
    {
        $char = Character::load($characterId);
        $data = $char->overlayCharacter($tplChar);
        $data['server_id'] = 'local';
        $data['online_status'] = 2;
        return $data;
    }

    public static function inventoryForCharacter(int $characterId, array $tplInv): array
    {
        return Character::load($characterId)->inventoryData($tplInv);
    }

    public static function itemsForCharacter(int $characterId, array $tplItem): array
    {
        return Character::load($characterId)->itemsData($tplItem);
    }

    public static function shapeLike(array $tpl, array $values): array
    {
        $out = $tpl;
        foreach ($values as $k => $v) {
            if (array_key_exists($k, $out)) {
                $out[$k] = self::castLike($out[$k], $v);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private static function castLike($like, $val)
    {
        if (is_bool($like)) return (bool)$val;
        if (is_int($like)) return (int)$val;
        if (is_float($like)) return (float)$val;
        return $val === null ? '' : (string)$val;
    }
}
