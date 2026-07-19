<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

/** Constants oficiais (CDN, chave "duel_*"): 1 stamina por ataque (max_duel_stamina
 *  observado e 10, um custo fixo de 20 -- resquicio do codigo antigo -- nunca cabia). */
const DUEL_STAMINA_COST_PER_ATTACK = 1;
const DUEL_SINGLE_ATTACK_PREMIUM_AMOUNT = 2;

return function (array $params): array {
    $char = Character::loadByUser((int)($params['user_id'] ?? 0));
    $row = Db::row('SELECT active_duel_id, duel_stamina FROM `character` WHERE id = ?', [$char->id()]);
    // Sem essas travas o client conseguia empilhar duelos (o active_duel_id
    // anterior ficava orfao, nunca reivindicado) ou duelar com stamina 0 --
    // os codigos de erro (errStartDuelActiveDuelFound/errRemoveDuelStaminaNotEnough)
    // ja existem no client, so faltava o server checar.
    if ((int)($row['active_duel_id'] ?? 0) !== 0) {
        throw new GameError('errStartDuelActiveDuelFound');
    }

    $useStamina = (int)($row['duel_stamina'] ?? 0) >= DUEL_STAMINA_COST_PER_ATTACK;
    if (!$useStamina) {
        // Sem stamina: o botao de ataque vira "pagar com premium" (use_premium=true,
        // mesmo custo fixo que o client mostra via get_duel_single_attack_premium_amount).
        // Sem isso, jogador sem stamina ficava travado ate regenerar, mesmo mandando
        // querendo pagar.
        $usePremium = filter_var($params['use_premium'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$usePremium) {
            throw new GameError('errRemoveDuelStaminaNotEnough');
        }
        $debited = Db::exec(
            'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
            [DUEL_SINGLE_ATTACK_PREMIUM_AMOUNT, $char->userId(), DUEL_SINGLE_ATTACK_PREMIUM_AMOUNT]
        );
        if ($debited !== 1) {
            throw new GameError('errRemovePremiumCurrencyNotEnough');
        }
    }

    $opponentId = (int)($params['character_id'] ?? 0);
    if ($opponentId <= 0 || $opponentId === $char->id()) {
        $opps = Character::duelOpponents($char->id(), [], 1);
        $opponentId = (int)($opps[0]['id'] ?? 0);
    }
    $opp = Character::load($opponentId);
    $now = time();

    // Stats REAIS dos dois personagens (nao mais um array fixo nivel-1) -- batalha
    // tem de refletir o perfil de verdade (base+treinado+equipamento), igual o
    // proprio character overlay mostra. battleProfile('a') nunca aplica a
    // penalidade de -1 (essa e so pro caso sem oponente real, luta de missao).
    $profileA = $char->battleProfile('a');
    $profileB = $opp->battleProfile('a');
    $profileB['profile'] = 'b';
    $rounds = ['rounds' => [['a' => 'a', 'd' => 'b', 'r' => 2, 'v' => 10], ['a' => 'b', 'd' => 'a', 'r' => 2, 'v' => 5], ['a' => 'a', 'd' => 'b', 'r' => 3, 'v' => 30]]];

    Db::exec(
        "INSERT INTO `battle` (ts_creation, profile_a_stats, profile_b_stats, winner, rounds) VALUES (?, ?, ?, 'a', ?)",
        [$now, json_encode($profileA, JSON_UNESCAPED_SLASHES), json_encode($profileB, JSON_UNESCAPED_SLASHES), json_encode($rounds, JSON_UNESCAPED_SLASHES)]
    );
    $battleId = (int)Db::pdo()->lastInsertId();
    Db::exec(
        "INSERT INTO `duel` (ts_creation, battle_id, character_a_id, character_b_id, character_a_status, character_b_status, character_a_rewards, character_b_rewards, unread)
         VALUES (?, ?, ?, ?, 1, 1, ?, ?, 'true')",
        [$now, $battleId, $char->id(), $opponentId, '{"coins":5,"honor":10}', '{"coins":1,"honor":-1}']
    );
    $duelId = (int)Db::pdo()->lastInsertId();
    // active_duel_id PERSISTIDO (nao so na resposta): sem coluna real, o campo
    // vazava o valor da captura original (skelletonx, duelo de outra conta) em
    // toda resposta que reconstroi 'character' via accountState (praticamente
    // qualquer poll no meio do duelo) -- get_activeDuel() do client compara
    // _duel.get_id()==active_duel_id, entao o duelo "sumia" assim que o id errado
    // voltava. Mesmo padrao ja corrigido pra active_league_fight_id.
    $staminaCost = $useStamina ? DUEL_STAMINA_COST_PER_ATTACK : 0;
    Db::exec('UPDATE `character` SET duel_stamina = GREATEST(0, duel_stamina - ?), ts_last_duel = ?, active_duel_id = ? WHERE id = ?', [$staminaCost, $now, $duelId, $char->id()]);

    $data = Live::template('startDuel');
    $char = Character::load($char->id());
    if (isset($data['user'])) $data['user'] = $char->overlayUser($data['user']);
    if (isset($data['character'])) {
        $data['character'] = $char->overlayCharacter($data['character']);
    }
    $data['duel'] = Live::shapeLike($data['duel'] ?? [], Db::row('SELECT * FROM `duel` WHERE id = ?', [$duelId]) ?? []);
    $data['battle'] = Live::shapeLike($data['battle'] ?? [], Db::row('SELECT * FROM `battle` WHERE id = ?', [$battleId]) ?? []);
    $data['opponent'] = Live::requestedCharacter($opponentId, $data['opponent'] ?? []);
    $data['opponent_inventory'] = Live::inventoryForCharacter($opponentId, $data['opponent_inventory'] ?? []);
    $data['opponent_inventory_items'] = Live::itemsForCharacter($opponentId, $data['opponent_inventory_items'][0] ?? []);
    if (isset($data['inventory'])) $data['inventory'] = $char->inventoryData($data['inventory']);
    return $data;
};
