<?php
declare(strict_types=1);

/**
 * action: autoLoginUser  (relogin com sessao salva)
 * Monta o boot a partir do banco:
 *  - `character`: TODOS os campos que sao coluna vem do banco (identidade inclusa);
 *  - `user`: id/premium do banco;
 *  - estruturas de CONTA (inventario, itens, quests, duelos, metas...) sao esvaziadas
 *    preservando a shape -> conta nova nasce limpa e nao herda dados de outra conta.
 * A config GLOBAL (constants, items, campanhas, ...) permanece do template.
 * TODO(RE): quando as tabelas de inventario/quests forem populadas, ler do banco em vez de esvaziar.
 */

use HeroZero\Character;
use HeroZero\GameError;
use HeroZero\Live;
use HeroZero\Replay;
use HeroZero\Session;

/**
 * Estruturas de conta que ainda NAO tem tabela mapeada -> zeradas preservando a shape
 * (conta nova nasce limpa e nao herda nada de outra conta).
 * `inventory`, `items` e `quests` NAO estao aqui: sao lidos do banco (ver abaixo).
 */
const ACCOUNT_STATE_KEYS = [
    'bank_inventory', 'owned_items', 'battle', 'battles', 'duel', 'opponent',
    'opponent_inventory', 'opponent_inventory_items', 'sidekicks',
    'daily_bonus_lookup', 'current_goal_values', 'collected_goals',
    'current_item_pattern_values', 'collected_item_pattern',
    'collected_optical_changes', 'missed_duels', 'missed_league_fights',
    'missed_hideout_attacks', 'new_guild_log_entries',
    'character_guild_inventory', 'completed_story_dungeon_steps',
    'dungeon_hardmode_emblems', 'season_progress',
];

return function (array $params): array {
    $userId = (int)($params['existing_user_id'] ?? $params['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new GameError('errLoginInvalidSession');
    }

    // Boot só é permitido com a sessão correta desta conta (emitida no login/registro).
    // No-op p/ usuário sem sessão no banco ou com HZ_STRICT_SESSION=0 (debug).
    Session::assertMatches($params);

    // Rotaciona a sessão (como no jogo oficial): este boot adota a sessão NOVA
    // (o cliente lê user.session_id da resposta); qualquer outro navegador na
    // mesma conta cai no próximo poll com errLoginInvalidSession.
    Session::rotate($userId);

    $data = Live::withLocalConstants(Replay::data('autoLoginUser'));   // template de boot (config global) + server_time

    // templates de tipo (antes de qualquer esvaziamento) p/ tipar o que vem do banco.
    $tplInv   = $data['inventory'] ?? [];
    $tplItem  = $data['items'][0]  ?? [];
    $tplQuest = $data['quests'][0] ?? [];
    $tplTraining = $data['trainings'][0] ?? [];

    try {
        $char = Character::loadByUser($userId);
        if (isset($data['character'])) $data['character'] = $char->overlayCharacter($data['character']);
        if (isset($data['user']))      $data['user']      = $char->overlayUser($data['user']);

        // estado real do banco (conta nova = vazio; skelletonx = semeado).
        $data['inventory'] = $char->inventoryData($tplInv);
        $data['items']     = $char->itemsData($tplItem);
        $data['quests']    = $char->questsData($tplQuest);
        $data['trainings'] = [$tplTraining];
        $data = Live::attachTrainingState($data, $char);
        $data = Live::attachEventQuestState($data);
        $data = Live::attachTreasureEventState($data, $char);
        $guild = Live::guildForUser($userId);
        if ($guild !== null) {
            $data['guild'] = Live::shapeGuild($guild);
            $data['guild_members'] = Live::guildMembers((int)$guild['id']);
        }

        // demais estruturas de conta ainda sem tabela: zeradas preservando a shape.
        foreach (ACCOUNT_STATE_KEYS as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = Character::emptyLike($data[$k]);
            }
        }

        // Depois do esvaziamento: progresso real da dungeon de historia
        // (completed_story_dungeon_steps esta em ACCOUNT_STATE_KEYS).
        $data = Live::attachStoryDungeonState($data, $char);

        // season_progress TEM tabela (season_progress): se a conta ja ativou uma
        // season, devolvemos o progresso real por cima do zerado acima.
        if (array_key_exists('season_progress', $data)) {
            $season = \HeroZero\Db::row('SELECT * FROM `season_progress` WHERE character_id = ?', [$char->id()]);
            if ($season !== null) {
                $data['season_progress'] = [
                    'id' => (int)$season['id'],
                    'season_id' => (int)$season['season_id'],
                    'character_id' => $char->id(),
                    'status' => (int)$season['status'],
                    'ts_created' => (int)$season['ts_created'],
                    'ts_start' => (int)$season['ts_start'],
                    'ts_end' => (int)$season['ts_end'],
                    'identifier' => (string)$season['identifier'],
                    'season_points' => (int)$season['season_points'],
                    'premium_unlocked' => (bool)$season['premium_unlocked'],
                    'restarted' => (bool)$season['restarted'],
                ];
            }
        }

        // O cliente de optical changes assume que available_chests decodifica para
        // array e chama .length nele. String vazia vira null e quebra a loja de donuts.
        if (array_key_exists('current_optical_changes', $data) && is_array($data['current_optical_changes'])) {
            $data['current_optical_changes'] = Character::emptyLike($data['current_optical_changes']);
            $data['current_optical_changes']['id'] = $char->id();
            $data['current_optical_changes']['character_id'] = $char->id();
            $data['current_optical_changes']['available_chests'] = '[]';
            $data['current_optical_changes']['active_options'] = '[]';
            $data['current_optical_changes']['unlocked_options'] = '{}';
            $data['current_optical_changes']['use_for_character'] = false;
            $data['current_optical_changes']['use_for_quest'] = false;
            $data['current_optical_changes']['use_for_duel'] = false;
            $data['current_optical_changes']['use_for_league'] = false;
        }

        // OBS: nao omitimos `quests` vazio. Toda conta tem quests iniciais reais
        // (Character::seedStarterQuests), entao a lista nunca e vazia e o cliente monta
        // `_quests` corretamente. quests=[] quebraria o boot; quests ausente deixaria
        // `_quests` null e quebraria o painel de Quests. A shape valida e ter >=1 quest.
    } catch (GameError $e) {
        // usuario ainda nao existe no banco -> template puro (nao trava debug).
    }
    return $data;
};
